<?php
require_once '../config/config.php';
requireAuth();

$user = new User();
$userId = $_SESSION['user_id'];
$userInfo = $user->getById($userId);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $data = [
            'nombre' => $_POST['nombre'],
            'apellido' => $_POST['apellido'],
            'email' => $_POST['email']
        ];

        $result = $user->update($userId, $data);
        if ($result['success']) {
            $message = 'Perfil actualizado correctamente.';
            $messageType = 'success';
            $userInfo = $user->getById($userId); // Recargar info
        } else {
            $message = 'Error al actualizar: ' . $result['message'];
            $messageType = 'danger';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $result = $user->changePassword($userId, $_POST['current_password'], $_POST['new_password']);
        if ($result['success']) {
            $message = 'Contraseña cambiada exitosamente.';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $result['message'];
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema de Clasificación Aduanera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-file-invoice"></i>
                Sistema de Clasificación Aduanera
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-home"></i> Inicio
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-6x text-secondary opacity-25"></i>
                    </div>
                    <h3 class="mb-1"><?php echo htmlspecialchars($userInfo['nombre'] . ' ' . $userInfo['apellido']); ?>
                    </h3>
                    <p class="text-muted"><?php echo htmlspecialchars($userInfo['email']); ?></p>
                    <span
                        class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($userInfo['rol']); ?></span>
                    <hr>
                    <div class="text-start small text-secondary">
                        <p class="mb-1"><i class="fas fa-calendar-alt me-2"></i> Miembro desde:
                            <?php echo date('d/m/Y', strtotime($userInfo['fecha_creacion'])); ?></p>
                        <p class="mb-0"><i class="fas fa-clock me-2"></i> Último acceso:
                            <?php echo $userInfo['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($userInfo['ultimo_acceso'])) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Editar Perfil -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-primary"><i class="fas fa-user-edit me-2"></i> Editar Información Personal
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" name="nombre" class="form-control"
                                        value="<?php echo htmlspecialchars($userInfo['nombre']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" name="apellido" class="form-control"
                                        value="<?php echo htmlspecialchars($userInfo['apellido']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($userInfo['email']); ?>" required>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cambiar Contraseña -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-danger"><i class="fas fa-lock me-2"></i> Seguridad: Cambiar Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Contraseña Actual</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" name="new_password" class="form-control" minlength="8"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" minlength="8" required>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-key me-2"></i> Actualizar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <span class="text-white">© <?php echo date('Y'); ?> Sistema de Clasificación Aduanera Inteligente</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>