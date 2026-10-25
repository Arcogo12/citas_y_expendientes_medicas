<?php

require_once __DIR__ . '/../config/conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fechaNacimiento = $_POST['fecha_nacimiento'] ?? '';

    if ($nombre === '' || $apellidos === '' || $correo === '') {
        $error = 'Nombre, apellidos y correo son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un correo electrónico válido.';
    } else {
        try {
            $conexion->beginTransaction();

            $sqlUsuario = "INSERT INTO usuarios
                (nombre, apellidos, correo, contrasena, rol)
                VALUES (:nombre, :apellidos, :correo, :contrasena, 'paciente')";

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

            $sqlPaciente = "INSERT INTO pacientes
                (usuario_id, fecha_nacimiento, telefono)
                VALUES (:usuario_id, :fecha_nacimiento, :telefono)";

            $consultaPaciente = $conexion->prepare($sqlPaciente);

            $consultaPaciente->execute([
                ':usuario_id' => $usuarioId,
                ':fecha_nacimiento' => $fechaNacimiento !== ''
                    ? $fechaNacimiento
                    : null,
                ':telefono' => $telefono !== ''
                    ? $telefono
                    : null,
            ]);

            $conexion->commit();

            header('Location: pacientes.php?registrado=1');
            exit;
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = 'Ese correo electrónico ya está registrado.';
            } else {
                error_log('Error al registrar paciente: ' . $e->getMessage());
                $error = 'No fue posible registrar al paciente.';
            }
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log('Error inesperado al registrar paciente: ' . $e->getMessage());
            $error = 'Ocurrió un error inesperado.';
        }
    }
}

if (isset($_GET['registrado'])) {
    $mensaje = 'Paciente registrado correctamente.';
}

$sql = "SELECT
            p.id,
            u.nombre,
            u.apellidos,
            u.correo,
            p.telefono,
            p.fecha_nacimiento
        FROM pacientes p
        INNER JOIN usuarios u ON u.id = p.usuario_id
        ORDER BY p.id DESC";

$consulta = $conexion->query($sql);
$pacientes = $consulta->fetchAll();

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
    <title>Gestión de pacientes</title>
</head>

<body>

    <h1>Gestión de pacientes</h1>

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

    <h2>Registrar paciente</h2>

    <form method="POST" action="pacientes.php">
        <label for="nombre">Nombre:</label><br>
        <input type="text" id="nombre" name="nombre" maxlength="100" required>
        <br><br>

        <label for="apellidos">Apellidos:</label><br>
        <input type="text" id="apellidos" name="apellidos" maxlength="100" required>
        <br><br>

        <label for="correo">Correo electrónico:</label><br>
        <input type="email" id="correo" name="correo" maxlength="150" required>
        <br><br>

        <label for="telefono">Teléfono:</label><br>
        <input type="tel" id="telefono" name="telefono" maxlength="20">
        <br><br>

        <label for="fecha_nacimiento">Fecha de nacimiento:</label><br>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento">
        <br><br>

        <button type="submit">Registrar paciente</button>
    </form>

    <hr>

    <h2>Pacientes registrados</h2>

    <?php if (count($pacientes) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellidos</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Fecha de nacimiento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $paciente): ?>
                    <tr>
                        <td><?= (int) $paciente['id'] ?></td>
                        <td><?= escapar($paciente['nombre']) ?></td>
                        <td><?= escapar($paciente['apellidos']) ?></td>
                        <td><?= escapar($paciente['correo']) ?></td>
                        <td><?= escapar($paciente['telefono'] ?? '') ?></td>
                        <td><?= escapar($paciente['fecha_nacimiento'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aún no hay pacientes registrados.</p>
    <?php endif; ?>

</body>

</html>