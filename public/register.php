<?php
require_once '../config/config.php';

// Si ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    redirect('/agenteClasificador/private/dashboard.php');
}

$error = '';
$success = '';

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre'] ?? '');
    $apellido = sanitize($_POST['apellido'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } else {
        $auth = new Auth();
        $result = $auth->register($nombre, $apellido, $email, $password);

        if ($result['success']) {
            $success = 'Registro exitoso. Ya puede iniciar sesión.';
            // Limpiar campos
            $nombre = $apellido = $email = '';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Clasificación Aduanera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh; padding: 2rem 0;">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <i class="fas fa-user-plus fa-3x mb-3" style="color: var(--primary-color);"></i>
                        <h3>Crear Cuenta</h3>
                        <p class="text-secondary mb-0">Sistema de Clasificación de Facturas Aduaneras</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success">Ir a Iniciar Sesión</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" placeholder="Juan"
                                            required value="<?php echo htmlspecialchars($nombre ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Apellido</label>
                                        <input type="text" name="apellido" class="form-control" placeholder="Pérez"
                                            required value="<?php echo htmlspecialchars($apellido ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control"
                                        placeholder="correo@ejemplo.com" required
                                        value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres" required>
                                </div>
                                <small class="text-secondary">Debe tener al menos <?php echo PASSWORD_MIN_LENGTH; ?>
                                    caracteres</small>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Confirmar Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password_confirm" class="form-control"
                                        placeholder="Repita la contraseña" required>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    Acepto los términos y condiciones
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-user-plus"></i> Crear Cuenta
                            </button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">
                            ¿Ya tiene cuenta? <a href="login.php">Inicie sesión aquí</a>
                        </p>
                        <p class="mt-2 mb-0">
                            <a href="index.php"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>