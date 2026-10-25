<?php

require_once __DIR__ . '/../config/conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $especialidad = trim($_POST['especialidad'] ?? '');
    $cedula = trim($_POST['cedula_profesional'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if (
        $nombre === '' ||
        $apellidos === '' ||
        $correo === '' ||
        $especialidad === ''
    ) {
        $error = 'Completa todos los campos obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un correo electrónico válido.';
    } else {
        try {
            $conexion->beginTransaction();

            $sqlUsuario = "INSERT INTO usuarios
                (nombre, apellidos, correo, contrasena, rol)
                VALUES (:nombre, :apellidos, :correo, :contrasena, 'medico')";

            $consultaUsuario = $conexion->prepare($sqlUsuario);

            $consultaUsuario->execute([
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':correo' => $correo,
                ':contrasena' => password_hash(
                    bin2hex(random_bytes(16)),
                    PASSWORD_DEFAULT
                ),
            ]);

            $usuarioId = (int) $conexion->lastInsertId();

            $sqlMedico = "INSERT INTO medicos
                (usuario_id, especialidad, cedula_profesional, telefono)
                VALUES (:usuario_id, :especialidad, :cedula, :telefono)";

            $consultaMedico = $conexion->prepare($sqlMedico);

            $consultaMedico->execute([
                ':usuario_id' => $usuarioId,
                ':especialidad' => $especialidad,
                ':cedula' => $cedula !== '' ? $cedula : null,
                ':telefono' => $telefono !== '' ? $telefono : null,
            ]);

            $conexion->commit();

            header('Location: medicos.php?registrado=1');
            exit;
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = 'El correo o la cédula profesional ya están registrados.';
            } else {
                error_log('Error al registrar médico: ' . $e->getMessage());
                $error = 'No fue posible registrar al médico.';
            }
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log('Error inesperado al registrar médico: ' . $e->getMessage());
            $error = 'Ocurrió un error inesperado.';
        }
    }
}

if (isset($_GET['registrado'])) {
    $mensaje = 'Médico registrado correctamente.';
}

$sql = "SELECT
            m.id,
            u.nombre,
            u.apellidos,
            u.correo,
            m.especialidad,
            m.cedula_profesional,
            m.telefono
        FROM medicos m
        INNER JOIN usuarios u ON u.id = m.usuario_id
        ORDER BY m.id DESC";

$medicos = $conexion->query($sql)->fetchAll();

function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de médicos</title>
</head>

<body>

    <h1>Gestión de médicos</h1>

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

    <h2>Registrar médico</h2>

    <form method="POST" action="medicos.php">
        <label for="nombre">Nombre:</label><br>
        <input type="text" id="nombre" name="nombre" maxlength="100" required>
        <br><br>

        <label for="apellidos">Apellidos:</label><br>
        <input type="text" id="apellidos" name="apellidos" maxlength="100" required>
        <br><br>

        <label for="correo">Correo electrónico:</label><br>
        <input type="email" id="correo" name="correo" maxlength="150" required>
        <br><br>

        <label for="especialidad">Especialidad:</label><br>
        <input type="text" id="especialidad" name="especialidad" maxlength="100" required>
        <br><br>

        <label for="cedula_profesional">Cédula profesional:</label><br>
        <input type="text" id="cedula_profesional" name="cedula_profesional" maxlength="50">
        <br><br>

        <label for="telefono">Teléfono:</label><br>
        <input type="tel" id="telefono" name="telefono" maxlength="20">
        <br><br>

        <button type="submit">Registrar médico</button>
    </form>

    <hr>

    <h2>Médicos registrados</h2>

    <?php if (count($medicos) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellidos</th>
                    <th>Correo</th>
                    <th>Especialidad</th>
                    <th>Cédula profesional</th>
                    <th>Teléfono</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicos as $medico): ?>
                    <tr>
                        <td>
                            <?= (int) $medico['id'] ?>
                        </td>
                        <td>
                            <?= escapar($medico['nombre']) ?>
                        </td>
                        <td>
                            <?= escapar($medico['apellidos']) ?>
                        </td>
                        <td>
                            <?= escapar($medico['correo']) ?>
                        </td>
                        <td>
                            <?= escapar($medico['especialidad']) ?>
                        </td>
                        <td>
                            <?= escapar($medico['cedula_profesional'] ?? '') ?>
                        </td>
                        <td>
                            <?= escapar($medico['telefono'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aún no hay médicos registrados.</p>
    <?php endif; ?>

</body>

</html>