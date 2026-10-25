<?php

require_once __DIR__ . '/../config/conexion.php';

$mensaje = '';
$error = '';

function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// Registrar un expediente clínico.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pacienteId = filter_input(
        INPUT_POST,
        'paciente_id',
        FILTER_VALIDATE_INT
    );

    $medicoId = filter_input(
        INPUT_POST,
        'medico_id',
        FILTER_VALIDATE_INT
    );

    $citaId = ($_POST['cita_id'] ?? '') !== ''
        ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT)
        : null;

    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $tratamiento = trim($_POST['tratamiento'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (!$pacienteId || !$medicoId || $diagnostico === '') {
        $error = 'Selecciona un paciente, un médico y escribe el diagnóstico.';
    } elseif ($citaId !== null && $citaId === false) {
        $error = 'La cita seleccionada no es válida.';
    } else {
        try {
            // Verificar que el paciente y el médico existan.
            $verificar = $conexion->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM pacientes WHERE id = :paciente_id)
                        AS paciente_existe,
                    (SELECT COUNT(*) FROM medicos WHERE id = :medico_id)
                        AS medico_existe"
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
                // Si se seleccionó una cita, comprobar su relación.
                if ($citaId !== null) {
                    $verificarCita = $conexion->prepare(
                        "SELECT COUNT(*)
                         FROM citas
                         WHERE id = :cita_id
                           AND paciente_id = :paciente_id
                           AND medico_id = :medico_id"
                    );

                    $verificarCita->execute([
                        ':cita_id' => $citaId,
                        ':paciente_id' => $pacienteId,
                        ':medico_id' => $medicoId,
                    ]);

                    if ((int) $verificarCita->fetchColumn() === 0) {
                        throw new DomainException(
                            'La cita no corresponde al paciente y médico seleccionados.'
                        );
                    }
                }

                $sql = "INSERT INTO expedientes
                    (
                        paciente_id,
                        medico_id,
                        cita_id,
                        diagnostico,
                        tratamiento,
                        observaciones
                    )
                    VALUES
                    (
                        :paciente_id,
                        :medico_id,
                        :cita_id,
                        :diagnostico,
                        :tratamiento,
                        :observaciones
                    )";

                $consulta = $conexion->prepare($sql);

                $consulta->execute([
                    ':paciente_id' => $pacienteId,
                    ':medico_id' => $medicoId,
                    ':cita_id' => $citaId,
                    ':diagnostico' => $diagnostico,
                    ':tratamiento' => $tratamiento !== ''
                        ? $tratamiento
                        : null,
                    ':observaciones' => $observaciones !== ''
                        ? $observaciones
                        : null,
                ]);

                header('Location: expedientes.php?registrado=1');
                exit;
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Error al registrar expediente: ' . $e->getMessage());
            $error = 'No fue posible registrar el expediente.';
        }
    }
}

if (isset($_GET['registrado'])) {
    $mensaje = 'Expediente registrado correctamente.';
}

// Obtener pacientes.
$pacientes = $conexion->query(
    "SELECT p.id, u.nombre, u.apellidos
     FROM pacientes p
     INNER JOIN usuarios u ON u.id = p.usuario_id
     ORDER BY u.apellidos, u.nombre"
)->fetchAll();

// Obtener médicos.
$medicos = $conexion->query(
    "SELECT m.id, u.nombre, u.apellidos, m.especialidad
     FROM medicos m
     INNER JOIN usuarios u ON u.id = m.usuario_id
     ORDER BY u.apellidos, u.nombre"
)->fetchAll();

// Obtener citas para relacionarlas con los expedientes.
$citas = $conexion->query(
    "SELECT
        c.id,
        c.paciente_id,
        c.medico_id,
        c.fecha,
        c.hora,
        up.nombre AS paciente_nombre,
        up.apellidos AS paciente_apellidos,
        um.nombre AS medico_nombre,
        um.apellidos AS medico_apellidos
     FROM citas c
     INNER JOIN pacientes p ON p.id = c.paciente_id
     INNER JOIN usuarios up ON up.id = p.usuario_id
     INNER JOIN medicos m ON m.id = c.medico_id
     INNER JOIN usuarios um ON um.id = m.usuario_id
     ORDER BY c.fecha DESC, c.hora DESC"
)->fetchAll();

// Consultar los expedientes registrados.
$expedientes = $conexion->query(
    "SELECT
        e.id,
        e.diagnostico,
        e.tratamiento,
        e.observaciones,
        e.fecha_registro,
        up.nombre AS paciente_nombre,
        up.apellidos AS paciente_apellidos,
        um.nombre AS medico_nombre,
        um.apellidos AS medico_apellidos,
        c.fecha AS cita_fecha
     FROM expedientes e
     INNER JOIN pacientes p ON p.id = e.paciente_id
     INNER JOIN usuarios up ON up.id = p.usuario_id
     INNER JOIN medicos m ON m.id = e.medico_id
     INNER JOIN usuarios um ON um.id = m.usuario_id
     LEFT JOIN citas c ON c.id = e.cita_id
     ORDER BY e.fecha_registro DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expedientes médicos</title>
</head>

<body>

    <h1>Gestión de expedientes médicos</h1>

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

    <h2>Registrar expediente</h2>

    <?php if (count($pacientes) === 0 || count($medicos) === 0): ?>
        <p>Primero debes registrar al menos un paciente y un médico.</p>
    <?php else: ?>

        <form method="POST" action="expedientes.php">

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

            <label for="cita_id">Cita relacionada (opcional):</label><br>
            <select id="cita_id" name="cita_id">
                <option value="">Sin cita relacionada</option>

                <?php foreach ($citas as $cita): ?>
                    <option value="<?= (int) $cita['id'] ?>" data-paciente="<?= (int) $cita['paciente_id'] ?>"
                        data-medico="<?= (int) $cita['medico_id'] ?>">
                        <?= escapar(
                            '#' . $cita['id'] . ' - ' .
                            $cita['fecha'] . ' ' .
                            substr($cita['hora'], 0, 5) . ' - ' .
                            $cita['paciente_nombre'] . ' ' .
                            $cita['paciente_apellidos'] . ' / Dr. ' .
                            $cita['medico_nombre'] . ' ' .
                            $cita['medico_apellidos']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <label for="diagnostico">Diagnóstico:</label><br>
            <textarea id="diagnostico" name="diagnostico" rows="4" required></textarea>
            <br><br>

            <label for="tratamiento">Tratamiento:</label><br>
            <textarea id="tratamiento" name="tratamiento" rows="4"></textarea>
            <br><br>

            <label for="observaciones">Observaciones:</label><br>
            <textarea id="observaciones" name="observaciones" rows="4"></textarea>
            <br><br>

            <button type="submit">Guardar expediente</button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>Expedientes registrados</h2>

    <?php if (count($expedientes) > 0): ?>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Fecha de cita</th>
                    <th>Diagnóstico</th>
                    <th>Tratamiento</th>
                    <th>Observaciones</th>
                    <th>Fecha de registro</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($expedientes as $expediente): ?>
                    <tr>
                        <td>
                            <?= (int) $expediente['id'] ?>
                        </td>
                        <td>
                            <?= escapar(
                                $expediente['paciente_nombre'] . ' ' .
                                $expediente['paciente_apellidos']
                            ) ?>
                        </td>
                        <td>
                            <?= escapar(
                                $expediente['medico_nombre'] . ' ' .
                                $expediente['medico_apellidos']
                            ) ?>
                        </td>
                        <td>
                            <?= escapar($expediente['cita_fecha'] ?? 'Sin cita') ?>
                        </td>
                        <td>
                            <?= escapar($expediente['diagnostico']) ?>
                        </td>
                        <td>
                            <?= escapar($expediente['tratamiento'] ?? '') ?>
                        </td>
                        <td>
                            <?= escapar($expediente['observaciones'] ?? '') ?>
                        </td>
                        <td>
                            <?= escapar($expediente['fecha_registro']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>Aún no hay expedientes registrados.</p>
    <?php endif; ?>

</body>

</html>