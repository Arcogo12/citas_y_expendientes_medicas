<?php

require_once __DIR__ . '/../config/conexion.php';

$mensaje = '';
$error = '';

function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// Registrar una nueva cita.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $medicoId = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');

    $fechaValida = false;
    $fechaObjeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

    if ($fechaObjeto !== false) {
        $fechaValida = $fechaObjeto->format('Y-m-d') === $fecha;
    }

    $horaValida = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;

    if (
        !$pacienteId ||
        !$medicoId ||
        !$fechaValida ||
        !$horaValida
    ) {
        $error = 'Verifica el paciente, el médico, la fecha y la hora.';
    } elseif ($fecha < date('Y-m-d')) {
        $error = 'No se pueden registrar citas en fechas pasadas.';
    } else {
        try {
            // Confirmar que el paciente y el médico existan.
            $verificar = $conexion->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM pacientes WHERE id = :paciente_id) AS paciente_existe,
                    (SELECT COUNT(*) FROM medicos WHERE id = :medico_id) AS medico_existe"
            );

            $verificar->execute([
                ':paciente_id' => $pacienteId,
                ':medico_id' => $medicoId,
            ]);

            $existencias = $verificar->fetch();

            if (
                (int) $existencias['paciente_existe'] === 0 ||
                (int) $existencias['medico_existe'] === 0
            ) {
                $error = 'El paciente o el médico seleccionado no existe.';
            } else {
                $sql = "INSERT INTO citas
                    (paciente_id, medico_id, fecha, hora, motivo)
                    VALUES
                    (:paciente_id, :medico_id, :fecha, :hora, :motivo)";

                $consulta = $conexion->prepare($sql);

                $consulta->execute([
                    ':paciente_id' => $pacienteId,
                    ':medico_id' => $medicoId,
                    ':fecha' => $fecha,
                    ':hora' => $hora . ':00',
                    ':motivo' => $motivo !== '' ? $motivo : null,
                ]);

                header('Location: citas.php?registrada=1');
                exit;
            }
        } catch (PDOException $e) {
            // MySQL usa SQLSTATE 23000 para violaciones de restricciones.
            // El código MySQL 1062 identifica una clave duplicada.
            if (
                $e->getCode() === '23000' &&
                isset($e->errorInfo[1]) &&
                (int) $e->errorInfo[1] === 1062
            ) {
                $error = 'Este médico ya tiene una cita en esa fecha y hora.';
            } else {
                error_log('Error al registrar cita: ' . $e->getMessage());
                $error = 'No fue posible registrar la cita.';
            }
        }
    }
}

if (isset($_GET['registrada'])) {
    $mensaje = 'Cita registrada correctamente.';
}

// Obtener pacientes para el formulario.
$pacientes = $conexion->query(
    "SELECT p.id, u.nombre, u.apellidos
     FROM pacientes p
     INNER JOIN usuarios u ON u.id = p.usuario_id
     ORDER BY u.apellidos, u.nombre"
)->fetchAll();

// Obtener médicos para el formulario.
$medicos = $conexion->query(
    "SELECT m.id, u.nombre, u.apellidos, m.especialidad
     FROM medicos m
     INNER JOIN usuarios u ON u.id = m.usuario_id
     ORDER BY u.apellidos, u.nombre"
)->fetchAll();

// Consultar las citas registradas.
$sqlCitas = "SELECT
                c.id,
                c.fecha,
                c.hora,
                c.motivo,
                c.estado,
                up.nombre AS paciente_nombre,
                up.apellidos AS paciente_apellidos,
                um.nombre AS medico_nombre,
                um.apellidos AS medico_apellidos,
                m.especialidad
            FROM citas c
            INNER JOIN pacientes p ON p.id = c.paciente_id
            INNER JOIN usuarios up ON up.id = p.usuario_id
            INNER JOIN medicos m ON m.id = c.medico_id
            INNER JOIN usuarios um ON um.id = m.usuario_id
            ORDER BY c.fecha DESC, c.hora DESC";

$citas = $conexion->query($sqlCitas)->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de citas médicas</title>
</head>

<body>

    <h1>Gestión de citas médicas</h1>

    <?php if ($mensaje !== ''): ?>
        <p style="color: green;">
            <?= escapar($mensaje) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p style="color: red;">
            <?= escapar($error) ?>
        </p>
    <?php endif; ?>

    <h2>Registrar una cita</h2>

    <?php if (count($pacientes) === 0 || count($medicos) === 0): ?>
        <p>
            Primero debes registrar al menos un paciente y un médico.
        </p>
    <?php else: ?>

        <form method="POST" action="citas.php">

            <label for="paciente_id">Paciente:</label><br>
            <select id="paciente_id" name="paciente_id" required>
                <option value="">Selecciona un paciente</option>
                <?php foreach ($pacientes as $paciente): ?>
                    <option value="<?= (int) $paciente['id'] ?>">
                        <?= escapar(
                            $paciente['nombre'] . ' ' . $paciente['apellidos']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <label for="medico_id">Médico:</label><br>
            <select id="medico_id" name="medico_id" required>
                <option value="">Selecciona un médico</option>
                <?php foreach ($medicos as $medico): ?>
                    <option value="<?= (int) $medico['id'] ?>">
                        <?= escapar(
                            $medico['nombre'] . ' ' .
                            $medico['apellidos'] . ' - ' .
                            $medico['especialidad']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <label for="fecha">Fecha:</label><br>
            <input type="date" id="fecha" name="fecha" min="<?= date('Y-m-d') ?>" required>
            <br><br>

            <label for="hora">Hora:</label><br>
            <input type="time" id="hora" name="hora" required>
            <br><br>

            <label for="motivo">Motivo de la consulta:</label><br>
            <textarea id="motivo" name="motivo" maxlength="255" rows="3"></textarea>
            <br><br>

            <button type="submit">Registrar cita</button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>Citas registradas</h2>

    <?php if (count($citas) > 0): ?>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td>
                            <?= (int) $cita['id'] ?>
                        </td>
                        <td>
                            <?= escapar(
                                $cita['paciente_nombre'] . ' ' .
                                $cita['paciente_apellidos']
                            ) ?>
                        </td>
                        <td>
                            <?= escapar(
                                $cita['medico_nombre'] . ' ' .
                                $cita['medico_apellidos']
                            ) ?>
                        </td>
                        <td>
                            <?= escapar($cita['especialidad']) ?>
                        </td>
                        <td>
                            <?= escapar($cita['fecha']) ?>
                        </td>
                        <td>
                            <?= escapar(substr($cita['hora'], 0, 5)) ?>
                        </td>
                        <td>
                            <?= escapar($cita['motivo'] ?? '') ?>
                        </td>
                        <td>
                            <?= escapar($cita['estado']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>Aún no hay citas registradas.</p>
    <?php endif; ?>

</body>

</html>