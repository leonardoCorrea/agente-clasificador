<?php
require_once '../config/config.php';
requireAuth();

$user = new User();
$userInfo = $user->getById($_SESSION['user_id']);

// Paginación
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros básicos (si se envían)
$filters = [];
if (isset($_GET['usuario_id']) && !empty($_GET['usuario_id'])) {
    $filters['usuario_id'] = $_GET['usuario_id'];
}
if (isset($_GET['accion']) && !empty($_GET['accion'])) {
    $filters['accion'] = $_GET['accion'];
}
if (isset($_GET['tabla']) && !empty($_GET['tabla'])) {
    $filters['tabla'] = $_GET['tabla'];
}

// Obtener logs
$logs = AuditLog::getAll($filters, $limit, $offset);
$totalLogs = AuditLog::count($filters);
$totalPages = ceil($totalLogs / $limit);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - Sistema de Clasificación Aduanera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
    <style>
        .json-cell {
            font-family: monospace;
            font-size: 0.85em;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .json-cell:hover {
            white-space: pre-wrap;
            word-break: break-all;
            max-width: none;
            position: relative;
            background: #fff;
            border: 1px solid #ddd;
            padding: 5px;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
    </style>
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
                        <a class="nav-link active" href="audit-log.php">
                            <i class="fas fa-history"></i> Auditoría
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($userInfo['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                            </li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar
                                    Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4 mb-5">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-history text-muted me-2"></i>registro de Auditoría</h2>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Anterior</a>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                            </li>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <p class="text-secondary">Historial de acciones y cambios en el sistema</p>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 text-dark">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Tabla</th>
                                <th>ID Reg.</th>
                                <th>IP</th>
                                <th>Datos Nuevos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No hay registros de auditoría disponibles.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($log['fecha_accion']); ?>
                                        </td>
                                        <td>
                                            <?php if ($log['usuario_id']): ?>
                                                <?php echo htmlspecialchars($log['nombre'] . ' ' . $log['apellido']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sistema/Desconocido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-info text-dark"><?php echo htmlspecialchars($log['accion']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['tabla_afectada']); ?></td>
                                        <td><?php echo htmlspecialchars($log['registro_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td class="json-cell" title="Click para ver detalle">
                                            <?php
                                            // Decodificar solo para formatear si es necesario, pero mostramos el JSON crudo en hover
                                            echo htmlspecialchars($log['datos_nuevos'] ?? '-');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
</body>

</html>