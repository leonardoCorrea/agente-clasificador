<?php
require_once '../config/config.php';
requireAuth();

$billing = new Billing();
$user = new User();
$currentUser = $user->getById($_SESSION['user_id']);
$isAdmin = ($currentUser['rol'] === 'admin');

// Manejar actualización de configuración (Solo Admin)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_config') {
    $targetUserId = (int) $_POST['target_user_id'];
    $data = [
        'costo_fijo' => $_POST['costo_fijo'],
        'costo_linea_reconocida' => $_POST['costo_linea_reconocida'],
        'costo_linea_clasificada' => $_POST['costo_linea_clasificada'],
        'moneda' => $_POST['moneda']
    ];
    $result = $billing->setConfig($targetUserId, $data);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'danger';
}

// Variables para vista de usuario/admin
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Por defecto el usuario actual. Si es admin y pide otro usuario, cambiamos.
$selectedUserId = $_SESSION['user_id'];
if ($isAdmin && isset($_GET['user_id'])) {
    $selectedUserId = (int) $_GET['user_id'];
}

// Datos para Admin
$users = [];
if ($isAdmin) {
    $users = $billing->getUsersForAdmin();
}

// Datos de factura (si hay usuario seleccionado)
$billData = null;
if ($selectedUserId) {
    $billData = $billing->calculateBill($selectedUserId, $selectedMonth, $selectedYear);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - Sistema de Clasificación Aduanera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-file-invoice me-2"></i>
                Sistema de Clasificación
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-sm btn-light">
                    <i class="fas fa-home"></i> Inicio
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold"><i class="fas fa-file-invoice-dollar text-success"></i> Gestión de Facturación</h2>
                <p class="text-secondary">
                    <?php if ($isAdmin): ?>
                        Administración de costos y visualización de consumos.
                    <?php else: ?>
                        Detalle de consumos y costos mensuales.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <!-- VISTA DE ADMINISTRADOR -->
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="fas fa-users-cog me-2"></i> Panel de Administración de Clientes
                    </h5>
                    <span class="badge bg-primary rounded-pill">Solo Admin</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Cliente</th>
                                    <th>Costo Fijo</th>
                                    <th>P. Línea</th>
                                    <th>P. Clase</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <?php
                                    $config = $billing->getConfig($u['id']);
                                    ?>
                                    <tr class="<?php echo ($selectedUserId == $u['id']) ? 'table-active' : ''; ?>">
                                        <td class="ps-4">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                        </td>
                                        <td><?php echo $config['moneda'] . ' ' . number_format($config['costo_fijo'], 2); ?>
                                        </td>
                                        <td><?php echo number_format($config['costo_linea_reconocida'], 2); ?></td>
                                        <td><?php echo number_format($config['costo_linea_clasificada'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                                                data-bs-target="#configModal<?php echo $u['id']; ?>">
                                                <i class="fas fa-edit"></i> Configurar
                                            </button>
                                            <a href="billing.php?user_id=<?php echo $u['id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                                                class="btn btn-sm <?php echo ($selectedUserId == $u['id']) ? 'btn-success' : 'btn-outline-success'; ?>">
                                                <i class="fas fa-eye"></i> Ver Factura
                                            </a>

                                            <!-- Modal Configuración -->
                                            <div class="modal fade" id="configModal<?php echo $u['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Configurar Costos:
                                                                    <?php echo htmlspecialchars($u['nombre']); ?>
                                                                </h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="update_config">
                                                                <input type="hidden" name="target_user_id"
                                                                    value="<?php echo $u['id']; ?>">
                                                                <!-- ... (rest of modal fields same as before) ... -->

                                                                <!-- Reusing previous modal content implicitly, avoiding duplicating lines in tool call excessively if not needed, but here we replace the block -->
                                                                <div class="mb-3">
                                                                    <label class="form-label">Moneda</label>
                                                                    <select name="moneda" class="form-select">
                                                                        <option value="USD" <?php echo $config['moneda'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                                                                        <option value="EUR" <?php echo $config['moneda'] === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                                                        <option value="MXN" <?php echo $config['moneda'] === 'MXN' ? 'selected' : ''; ?>>MXN</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Costo Fijo Mensual</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text">$</span>
                                                                        <input type="number" step="0.01" name="costo_fijo"
                                                                            class="form-control"
                                                                            value="<?php echo $config['costo_fijo']; ?>"
                                                                            required>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Costo por Línea Reconocida
                                                                        (OCR)</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text">$</span>
                                                                        <input type="number" step="0.01"
                                                                            name="costo_linea_reconocida" class="form-control"
                                                                            value="<?php echo $config['costo_linea_reconocida']; ?>"
                                                                            required>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Costo por Línea
                                                                        Clasificada</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text">$</span>
                                                                        <input type="number" step="0.01"
                                                                            name="costo_linea_clasificada" class="form-control"
                                                                            value="<?php echo $config['costo_linea_clasificada']; ?>"
                                                                            required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-primary">Guardar
                                                                    Cambios</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- VISTA DE DETALLE DE FACTURA (Común) -->
        <?php if ($selectedUserId && $billData): ?>
            <div class="row align-items-center mb-4">
                <div class="col-md-6">
                    <h4 class="mb-0">Resumen del Periodo</h4>
                    <p class="text-secondary mb-0">
                        <?php if ($selectedUserId == $_SESSION['user_id']): ?>
                            <span class="badge bg-success">Mi Facturación Personal</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Viendo Cliente ID: <?php echo $selectedUserId; ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <form action="" method="GET" class="d-inline-flex align-items-center bg-white p-2 rounded shadow-sm">
                        <?php if ($isAdmin): ?>
                            <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
                        <?php endif; ?>

                        <select name="month" class="form-select border-0 bg-transparent text-end fw-bold"
                            style="width: auto;" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select border-0 bg-transparent text-end fw-bold ms-2"
                            style="width: auto;" onchange="this.form.submit()">
                            <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>

            <div class="row">
                <!-- TARJETA TOTAL -->
                <div class="col-md-4 mb-4">
                    <div class="card bg-primary text-white h-100 shadow border-0 gradient-primary">
                        <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                            <h6 class="text-white-50 text-uppercase mb-2">Total a Pagar</h6>
                            <h1 class="display-4 fw-bold mb-0">
                                <?php echo $billData['currency'] . ' ' . number_format($billData['total'], 2); ?>
                            </h1>
                            <p class="mt-2 mb-0 opacity-75">
                                Periodo: <?php echo date('M Y', strtotime($billData['period']['startDate'])); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- TABLA DETALLE -->
                <div class="col-md-8 mb-4">
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-secondary">Desglose de Servicios</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Concepto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unit.</th>
                                        <th class="text-end pe-4">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($billData['details'] as $key => $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['label']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $item['quantity']; ?>
                                                <?php if ($key === 'classified_lines' && isset($billData['debug_info']['classified_all_time'])): ?>
                                                    <?php if ($billData['debug_info']['classified_all_time'] > $item['quantity']): ?>
                                                        <br><small class="text-muted"
                                                            title="Total Histórico: <?php echo $billData['debug_info']['classified_all_time']; ?>">(Hist:
                                                            <?php echo $billData['debug_info']['classified_all_time']; ?>)</small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($item['unit_price'], 2); ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold">
                                                <?php echo number_format($item['total'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold py-3">TOTAL
                                            (<?php echo $billData['currency']; ?>):</td>
                                        <td class="text-end pe-4 fw-bold py-3 text-primary">
                                            <?php echo number_format($billData['total'], 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFO ADICIONAL -->
            <div class="alert alert-info border-0 shadow-sm">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="fas fa-info-circle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold">Nota sobre la facturación</h6>
                        <p class="mb-0 small">
                            Los costos se calculan en base a la actividad registrada en el sistema.
                            Las "Líneas Reconocidas" corresponden a todos los ítems extraídos de facturas cargadas en este
                            mes.
                            Las "Líneas Reconocidas" corresponden a todos los ítems extraídos de facturas cargadas en este
                            mes.
                            Las "Líneas Clasificadas (IA)" son aquellos ítems que han sido procesados por la
                            Inteligencia Artificial en este mes (Dashboard > Items Clasificados).
                        </p>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2a5298 100%) !important;
        }
    </style>
</body>

</html>