<?php
require_once '../config/config.php';
requireAuth();

$user = new User();
$userInfo = $user->getById($_SESSION['user_id']);
$stats = $user->getStats($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Clasificación Aduanera</title>
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="audit-log.php">
                            <i class="fas fa-history"></i> Auditoría
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing.php">
                            <i class="fas fa-file-invoice-dollar"></i> Facturación
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="duaDropdown" role="button"
                            data-bs-toggle="dropdown">
                            <i class="fas fa-passport"></i> Aduanas / DUA
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="catalog.php"><i class="fas fa-book"></i> Catálogo SAC</a>
                            </li>
                            <li><a class="dropdown-item" href="dua-list.php"><i class="fas fa-list"></i> Mis DUAs</a>
                            </li>
                            <li><a class="dropdown-item" href="dua-create.php"><i class="fas fa-plus-circle"></i>
                                    Generar DUA</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($userInfo['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                            </li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Configuración</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar
                                    Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Bienvenida -->
        <div class="row mb-4">
            <div class="col-12">
                <h1>Bienvenido, <?php echo htmlspecialchars($userInfo['nombre'] . ' ' . $userInfo['apellido']); ?></h1>
                <p class="lead text-secondary">Panel de control del sistema de clasificación de facturas aduaneras</p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row row-cols-1 row-cols-md-5 mb-5 g-3">
            <div class="col">
                <div class="card text-center h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="fas fa-file-invoice fa-2x mb-2" style="color: var(--primary-color);"></i>
                        <h3><?php echo $stats['total_facturas'] ?? 0; ?></h3>
                        <p class="text-secondary small mb-0">Total Facturas</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="fas fa-list-ol fa-2x mb-2 text-dark"></i>
                        <h3><?php echo $stats['total_lineas'] ?? 0; ?></h3>
                        <p class="text-secondary small mb-0">Líneas Identificadas</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="fas fa-list fa-2x mb-2" style="color: var(--info-color);"></i>
                        <h3><?php echo $stats['items_clasificados'] ?? 0; ?></h3>
                        <p class="text-secondary small mb-0">Líneas Clasificadas</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x mb-2" style="color: var(--warning-color);"></i>
                        <h3><?php echo $stats['lineas_pendientes'] ?? 0; ?></h3>
                        <p class="text-secondary small mb-0">Líneas Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x mb-2" style="color: var(--success-color);"></i>
                        <h3><?php echo $stats['lineas_aprobadas'] ?? 0; ?></h3>
                        <p class="text-secondary small mb-0">Líneas Aprobadas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tres Botones Principales -->
        <div class="row mb-5">
            <div class="col-12 mb-4">
                <h2 class="text-gradient">Módulos Principales</h2>
                <p class="text-secondary">Seleccione el módulo con el que desea trabajar</p>
            </div>

            <div class="col-md-4">
                <a href="ocr-upload.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <h3 class="dashboard-card-title">1. Reconocimiento de Factura (OCR)</h3>
                        <p class="dashboard-card-description">
                            Cargue facturas en PDF o imagen y extraiga automáticamente toda la información mediante OCR
                            inteligente.
                        </p>
                        <div class="mt-3">
                            <span class="badge badge-primary">Paso 1</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="auto-digitize.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon"
                            style="background: linear-gradient(135deg, var(--success-color) 0%, var(--secondary-dark) 100%);">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <h3 class="dashboard-card-title">2. Digitación Automática</h3>
                        <p class="dashboard-card-description">
                            Formularios auto-completados con datos del OCR. Edite manualmente si es necesario y guarde
                            en la base de datos.
                        </p>
                        <div class="mt-3">
                            <span class="badge badge-success">Paso 2</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="ai-classify.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon"
                            style="background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3 class="dashboard-card-title">3. Clasificación Inteligente</h3>
                        <p class="dashboard-card-description">
                            Clasificación automática con IA. Obtenga sugerencias de códigos arancelarios con
                            explicaciones detalladas.
                        </p>
                        <div class="mt-3">
                            <span class="badge badge-warning">Paso 3</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Módulos Adicionales -->
        <div class="row">
            <div class="col-12 mb-4">
                <h3>Módulos Adicionales</h3>
            </div>

            <div class="col-md-6">
                <a href="review-approve.php" class="text-decoration-none">
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-check-double fa-3x" style="color: var(--info-color);"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Revisión y Aprobación</h5>
                                <p class="text-secondary mb-0">Revise y apruebe las clasificaciones finales</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6">
                <a href="audit-log.php" class="text-decoration-none">
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-history fa-3x" style="color: var(--secondary-color);"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Registro de Auditoría</h5>
                                <p class="text-secondary mb-0">Consulte el historial completo de operaciones</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6">
                <a href="dua-list.php" class="text-decoration-none">
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-passport fa-3x" style="color: var(--primary-color);"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Gestión de DUAs</h5>
                                <p class="text-secondary mb-0">Ver, generar y finalizar Declaraciones Únicas Aduaneras
                                </p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <a href="billing.php" class="text-decoration-none">
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-file-invoice-dollar fa-3x" style="color: var(--success-color);"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Facturación</h5>
                                <p class="text-secondary mb-0">Consulte costos y detalles de facturación mensual</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <?php if ($userInfo['rol'] === 'admin'): ?>
                <div class="col-md-6 mb-4">
                    <a href="admin-reset.php" class="text-decoration-none">
                        <div class="card mb-3 border-danger">
                            <div class="card-body d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-radiation fa-3x text-danger"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 text-danger">Reset del Sistema</h5>
                                    <p class="text-secondary mb-0">Limpieza total de datos para producción</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Guía Rápida -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-question-circle"></i> Guía Rápida de Uso</h4>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li class="mb-2">
                                <strong>Cargue su factura</strong> en el módulo de OCR (PDF o imagen)
                            </li>
                            <li class="mb-2">
                                <strong>Revise los datos extraídos</strong> en el módulo de digitación automática
                            </li>
                            <li class="mb-2">
                                <strong>Clasifique los ítems</strong> usando el módulo de IA
                            </li>
                            <li class="mb-2">
                                <strong>Revise y apruebe</strong> las clasificaciones sugeridas
                            </li>
                            <li>
                                <strong>Consulte la auditoría</strong> para ver el historial completo
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <span class="text-white">© <?php echo date('Y'); ?> Sistema de Clasificación Aduanera Inteligente - Todos
                los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>

</html>