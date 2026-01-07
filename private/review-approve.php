<?php
require_once '../config/config.php';
requireAuth();

$aiService = new AIClassificationService();
$itemManager = new InvoiceItem();
$invoice = new Invoice();

$userId = $_SESSION['user_id'];
$invoiceId = isset($_GET['factura_id']) ? (int) $_GET['factura_id'] : 0;
$factura = null;
$items = [];

// 1. Obtener lista de facturas para el selector (Últimas 50)
$invoices = $invoice->getAll(['usuario_id' => $userId], 50);

// 2. Si hay ID de factura, procesar lógica de esa factura
if ($invoiceId) {
    $factura = $invoice->getById($invoiceId);

    // Verificar que la factura pertenezca al usuario
    if (!$factura || $factura['usuario_id'] != $userId) {
        $factura = null;
        $invoiceId = 0;
        $error = "Factura no encontrada o no autorizada.";
    } else {
        // Manejar aprobación POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'approve') {
                $itemId = (int) $_POST['item_id'];
                $codigoFinal = $_POST['codigo_final'];
                $modificado = isset($_POST['es_modificado']);

                $aiService->approveClassification($itemId, $codigoFinal, $modificado);
                // Recargar para mostrar cambios
                header("Location: review-approve.php?factura_id=" . $invoiceId);
                exit;
            }
        }

        // Obtener líneas de la factura con sus estados (Unificado)
        $db = Database::getInstance();
        $sql = "SELECT i.*, 
                       r.codigo_arancelario_sugerido as codigo_sugerido, 
                       r.descripcion_codigo, 
                       r.explicacion, 
                       r.confianza, 
                       r.id as resultado_ia_id,
                       c.id as approved_id, 
                       c.codigo_arancelario as approved_code, 
                       c.fecha_aprobacion
                FROM items_factura i
                LEFT JOIN (
                    SELECT r1.* FROM resultados_ia r1
                    INNER JOIN (
                        SELECT item_factura_id, MAX(id) as max_id 
                        FROM resultados_ia 
                        GROUP BY item_factura_id
                    ) r2 ON r1.id = r2.max_id
                ) r ON i.id = r.item_factura_id
                LEFT JOIN clasificacion_final c ON i.id = c.item_factura_id
                WHERE i.factura_id = ?
                ORDER BY i.numero_linea ASC";

        $items = $db->query($sql, [$invoiceId]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisión y Aprobación - Sistema de Clasificación Aduanera</title>
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

        <!-- HEADER DE LA PÁGINA -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold"><i class="fas fa-check-double text-primary"></i> Revisión y Aprobación</h2>
                <p class="text-secondary mb-0">Auditoría final de líneas y asignación de códigos arancelarios.</p>
            </div>

            <?php if ($invoiceId): ?>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="review-approve.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-exchange-alt"></i> Cambiar Factura
                    </a>
                    <a href="ai-classify.php?factura_id=<?php echo $invoiceId; ?>" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Ir a Clasificación IA
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger shadow-sm mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- VISTA DE SELECCIÓN (SI NO HAY FACTURA) -->
        <?php if (!$invoiceId): ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <div class="bg-primary bg-opacity-10 d-inline-flex p-4 rounded-circle text-primary">
                            <i class="fas fa-search-dollar fa-3x"></i>
                        </div>
                    </div>
                    <h3 class="mb-3 fw-bold">Seleccionar Factura</h3>
                    <p class="text-muted mb-4 mx-auto" style="max-width: 600px;">
                        Seleccione una factura procesada para revisar sus líneas, ver sugerencias de la IA y aprobar las
                        clasificaciones finales.
                    </p>

                    <form action="" method="GET" class="d-inline-flex gap-2 justify-content-center flex-wrap"
                        style="max-width: 600px; width: 100%; margin: 0 auto;">
                        <select name="factura_id" class="form-select form-select-lg shadow-sm" required
                            onchange="this.form.submit()">
                            <option value="">-- Seleccione una factura disponible --</option>
                            <?php foreach ($invoices as $inv): ?>
                                <option value="<?php echo $inv['id']; ?>">
                                    Factura #<?php echo htmlspecialchars($inv['numero_factura']); ?>
                                    - <?php echo htmlspecialchars($inv['proveedor']); ?>
                                    (Items: <?php echo $inv['total_items']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                            <i class="fas fa-arrow-right"></i> Ver
                        </button>
                    </form>

                    <?php if (empty($invoices)): ?>
                        <div class="mt-4 text-warning">
                            <small><i class="fas fa-info-circle"></i> No tiene facturas cargadas en el sistema.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- VISTA DE DETALLE (CON FACTURA SELECCIONADA) -->
        <?php else: ?>

            <div class="card mb-4 border-0 shadow-sm border-start border-primary border-5">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-uppercase text-secondary fw-bold">Factura</small>
                            <h4 class="mb-0 text-dark">#<?php echo htmlspecialchars($factura['numero_factura']); ?></h4>
                        </div>
                        <div class="col-md-4">
                            <small class="text-uppercase text-secondary fw-bold">Proveedor</small>
                            <h4 class="mb-0 text-dark"><?php echo htmlspecialchars($factura['proveedor']); ?></h4>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <small class="text-uppercase text-secondary fw-bold">Total Líneas</small>
                            <h4 class="mb-0 text-primary"><?php echo count($items); ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3 opacity-50"></i>
                    <h4 class="text-muted">No hay líneas extraídas</h4>
                    <p class="text-secondary small">Esta factura no tiene ítems registrados para clasificar.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $isApproved = !empty($item['approved_id']);
                        $hasAI = !empty($item['codigo_sugerido']);

                        // Determinar estilo de borde y estado
                        $borderClass = 'border-warning'; // Por defecto: Sin clasificar
                        $statusIcon = 'fa-exclamation-triangle';
                        $statusColor = 'text-warning';
                        $statusText = 'Pendiente Clasificación';

                        if ($isApproved) {
                            $borderClass = 'border-success';
                            $statusIcon = 'fa-check-circle';
                            $statusColor = 'text-success';
                            $statusText = 'Aprobado';
                        } elseif ($hasAI) {
                            $borderClass = 'border-info';
                            $statusIcon = 'fa-robot';
                            $statusColor = 'text-info';
                            $statusText = 'Sugerencia IA Lista';
                        }
                        ?>

                        <div class="col-12 mb-4">
                            <div class="card shadow-sm border-0 border-start border-4 <?php echo $borderClass; ?>">
                                <div class="card-body">
                                    <div class="row g-0">
                                        <!-- DETALLE DE LA LÍNEA -->
                                        <div class="col-md-5 pe-md-4 border-end-md">
                                            <div class="d-flex align-items-center mb-2">
                                                <span
                                                    class="badge bg-light text-dark border me-2">#<?php echo $item['numero_linea']; ?></span>
                                                <h5 class="card-title text-primary mb-0 text-truncate"
                                                    title="<?php echo htmlspecialchars($item['descripcion']); ?>">
                                                    <?php echo htmlspecialchars($item['descripcion']); ?>
                                                </h5>
                                            </div>

                                            <div class="row g-2 small text-secondary mb-3">
                                                <div class="col-6">
                                                    <i class="fas fa-layer-group me-1"></i> Cant:
                                                    <strong><?php echo $item['cantidad']; ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <i class="fas fa-tag me-1"></i> P/N:
                                                    <?php echo htmlspecialchars($item['numero_serie_parte'] ?? 'N/A'); ?>
                                                </div>
                                            </div>

                                            <!-- Estado Visual -->
                                            <div class="d-flex align-items-center <?php echo $statusColor; ?> bg-light rounded p-2">
                                                <i class="fas <?php echo $statusIcon; ?> me-2 fa-lg"></i>
                                                <span class="fw-bold"><?php echo $statusText; ?></span>
                                            </div>
                                        </div>

                                        <!-- DATOS DE CLASIFICACIÓN (IA O APROBADO) -->
                                        <div class="col-md-4 px-md-4 border-end-md d-flex flex-column justify-content-center">
                                            <?php if ($isApproved): ?>
                                                <div class="text-center">
                                                    <small class="text-muted text-uppercase fw-bold">Código Aprobado</small>
                                                    <div class="display-6 text-success fw-bold my-2">
                                                        <?php echo htmlspecialchars($item['approved_code']); ?></div>
                                                    <span class="badge bg-success-subtle text-success border border-success">
                                                        <i class="fas fa-calendar-check me-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($item['fecha_aprobacion'])); ?>
                                                    </span>
                                                </div>
                                            <?php elseif ($hasAI): ?>
                                                <div class="bg-primary-subtle p-3 rounded-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <small class="fw-bold text-primary-emphasis">IA Sugiere:</small>
                                                        <span class="badge bg-primary"><?php echo $item['confianza']; ?>%
                                                            Confianza</span>
                                                    </div>
                                                    <h4 class="text-primary mb-1">
                                                        <?php echo htmlspecialchars($item['codigo_sugerido']); ?></h4>
                                                    <small
                                                        class="d-block lh-sm text-muted mb-2"><?php echo htmlspecialchars($item['descripcion_codigo']); ?></small>

                                                    <div class="accordion accordion-flush" id="acc_<?php echo $item['id']; ?>">
                                                        <div class="accordion-item bg-transparent">
                                                            <h2 class="accordion-header">
                                                                <button
                                                                    class="accordion-button collapsed py-1 px-0 bg-transparent shadow-none small text-primary"
                                                                    type="button" data-bs-toggle="collapse"
                                                                    data-bs-target="#expl_<?php echo $item['id']; ?>">
                                                                    <i class="fas fa-info-circle me-1"></i> Ver Explicación
                                                                </button>
                                                            </h2>
                                                            <div id="expl_<?php echo $item['id']; ?>"
                                                                class="accordion-collapse collapse"
                                                                data-bs-parent="#acc_<?php echo $item['id']; ?>">
                                                                <div class="accordion-body p-2 small text-secondary bg-white rounded">
                                                                    <?php echo htmlspecialchars($item['explicacion']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-4">
                                                    <i class="fas fa-robot fa-2x mb-2 opacity-25"></i>
                                                    <p class="mb-0">Sin sugerencia de IA disponible.</p>
                                                    <a href="ai-classify.php?factura_id=<?php echo $invoiceId; ?>"
                                                        class="btn btn-link btn-sm">Clasificar ahora</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- ACCIONES -->
                                        <div class="col-md-3 ps-md-4 d-flex flex-column justify-content-center">
                                            <?php if ($hasAI || $isApproved): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">

                                                    <label class="form-label small text-secondary fw-bold">Código Final</label>
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text bg-white"><i class="fas fa-barcode"></i></span>
                                                        <input type="text" name="codigo_final" class="form-control fw-bold"
                                                            value="<?php echo htmlspecialchars($isApproved ? $item['approved_code'] : $item['codigo_sugerido']); ?>">
                                                    </div>

                                                    <?php if (!$isApproved): ?>
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" name="es_modificado"
                                                                id="mod_<?php echo $item['id']; ?>">
                                                            <label class="form-check-label small text-muted"
                                                                for="mod_<?php echo $item['id']; ?>">
                                                                Es una corrección manual
                                                            </label>
                                                        </div>
                                                    <?php endif; ?>

                                                    <button type="submit"
                                                        class="btn w-100 <?php echo $isApproved ? 'btn-outline-success' : 'btn-success'; ?>">
                                                        <i class="fas <?php echo $isApproved ? 'fa-sync-alt' : 'fa-check'; ?> me-2"></i>
                                                        <?php echo $isApproved ? 'Actualizar' : 'Aprobar'; ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div class="d-grid">
                                                    <button class="btn btn-light text-muted" disabled>
                                                        <i class="fas fa-lock me-2"></i> Pendiente
                                                    </button>
                                                    <small class="text-center text-muted mt-2">Requiere clasificación previa</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>