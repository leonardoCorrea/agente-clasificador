<?php
require_once '../config/config.php';
requireAuth();

$user = new User();
$userInfo = $user->getById($_SESSION['user_id']);

// Seguridad: Solo Admin
if ($userInfo['rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$message = null;
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmation']) && $_POST['confirmation'] === 'RESET-PRODUCCION') {
        $sysAdmin = new SystemAdmin();
        $result = $sysAdmin->factoryReset();

        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    } else {
        $message = 'La frase de confirmación es incorrecta.';
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseteo de Sistema - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom shadow-sm bg-danger text-white navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>
                Zona Administrativa
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-danger shadow-lg">
                    <div class="card-header bg-danger text-white py-3">
                        <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Restaurar Valores de Fábrica
                        </h4>
                    </div>
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <span class="fa-stack fa-3x text-danger mb-3">
                                <i class="fas fa-circle fa-stack-2x opacity-25"></i>
                                <i class="fas fa-trash-alt fa-stack-1x"></i>
                            </span>
                            <h3 class="text-secondary fw-bold">¡ADVERTENCIA!</h3>
                            <p class="lead text-dark">Esta acción es destructiva e irreversible.</p>
                        </div>

                        <div class="alert alert-warning border-start border-4 border-warning">
                            <p class="mb-2 fw-bold">Al proceder, se eliminará permanentemente:</p>
                            <ul class="mb-0 small">
                                <li>Todas las facturas cargadas en el sistema.</li>
                                <li>Todos los archivos PDF e imágenes adjuntos.</li>
                                <li>Todos los resultados de OCR y clasificaciones de IA.</li>
                                <li>Todo el historial de items y aprobaciones.</li>
                                <li>El registro de auditoría completo.</li>
                            </ul>
                            <div class="mt-3 pt-2 border-top border-warning-subtle">
                                <p class="mb-0 fw-bold text-success"><i class="fas fa-check-circle me-1"></i> Se
                                    conservarán:</p>
                                <ul class="mb-0 small text-success">
                                    <li>Cuentas de usuario y contraseñas.</li>
                                    <li>Configuraciones del sistema y facturación.</li>
                                </ul>
                            </div>
                        </div>

                        <form method="POST" class="mt-4"
                            onsubmit="return confirm('¿Está ABSOLUTAMENTE SEGURO de que desea borrar todos los datos?');">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Para confirmar, escriba <span
                                        class="badge bg-light text-danger border border-danger user-select-all">RESET-PRODUCCION</span>
                                    a continuación:</label>
                                <input type="text" name="confirmation"
                                    class="form-control form-control-lg text-center fw-bold text-danger border-danger"
                                    required placeholder="Escriba la frase de confirmación">
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-danger btn-lg py-3">
                                    <i class="fas fa-radiation me-2"></i> EJECUTAR LIMPIEZA
                                </button>
                            </div>
                        </form>

                    </div>
                    <div class="card-footer bg-light text-center text-muted small py-3">
                        Utilice esta opción solo cuando desee preparar el sistema para un entorno de producción limpio o
                        reiniciar pruebas.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>