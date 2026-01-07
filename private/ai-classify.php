<?php
require_once '../config/config.php';
requireAuth();

$invoiceId = isset($_GET['factura_id']) ? (int) $_GET['factura_id'] : 0;
$aiService = new AIClassificationService();
$invoice = new Invoice();

$factura = null;
$facturasDigitadas = [];

if ($invoiceId) {
    $factura = $invoice->getById($invoiceId);
} else {
    $facturasDigitadas = $invoice->getAll(['usuario_id' => $_SESSION['user_id']]);
    // Filtrar localmente para mayor flexibilidad en los estados que mostramos para clasificar
    $facturasDigitadas = array_filter($facturasDigitadas, function ($f) {
        return in_array($f['estado'], ['ocr_completado', 'digitado', 'clasificado']);
    });
}

// Obtener todos los ítems de la factura con sus resultados de IA si existen (que no estén aprobados aún)
$pendingItems = [];
$classifiedItems = [];

if ($invoiceId) {
    $sql = "SELECT i.*, r.codigo_arancelario_sugerido, r.confianza, r.explicacion, r.descripcion_codigo
            FROM items_factura i
            LEFT JOIN (
                SELECT r1.* FROM resultados_ia r1
                INNER JOIN (
                    SELECT item_factura_id, MAX(id) as max_id 
                    FROM resultados_ia 
                    GROUP BY item_factura_id
                ) r2 ON r1.id = r2.max_id
            ) r ON i.id = r.item_factura_id
            LEFT JOIN clasificacion_final cf ON i.id = cf.item_factura_id
            WHERE i.factura_id = ? AND cf.id IS NULL
            ORDER BY i.numero_linea ASC";
    $allInvoiceItems = Database::getInstance()->query($sql, [$invoiceId]);

    foreach ($allInvoiceItems as $item) {
        if ($item['codigo_arancelario_sugerido']) {
            $classifiedItems[] = $item;
        } else {
            $pendingItems[] = $item;
        }
    }
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Acción: Actualizar clasificación manual
    if (isset($_POST['action']) && $_POST['action'] === 'update_manual') {
        $itemId = $_POST['item_id'];
        $newCode = $_POST['new_code'];

        $result = $aiService->saveManualClassification($itemId, $newCode);

        if ($result['success']) {
            $message = 'Clasificación actualizada correctamente';
            $messageType = 'success';
            // Refesh para ver cambios
            // header("Location: ai-classify.php?factura_id=$invoiceId");
            // exit;
        } else {
            $message = 'Error al actualizar: ' . $result['message'];
            $messageType = 'danger';
        }
    }

    $selectedItems = $_POST['items_to_classify'] ?? [];

    // Si viene action 'classify_all' y hay facturaId, obtener todos sus items pendientes
    if (isset($_POST['action']) && $_POST['action'] === 'classify_all' && $invoiceId) {
        $pendingItems = $aiService->getPendingItems($invoiceId);
        $selectedItems = array_column($pendingItems, 'id');
    }

    if (empty($selectedItems) && (!isset($_POST['action']) || $_POST['action'] !== 'update_manual')) {
        $message = 'Por favor, seleccione al menos un ítem';
        $messageType = 'warning';
    } elseif (!empty($selectedItems)) {
        $successCount = 0;

        foreach ($selectedItems as $itemId) {
            $aiResult = $aiService->classifySingleItem($itemId);
            if ($aiResult['success']) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            $message = "Se han clasificado $successCount ítems exitosamente bajo el estándar SAC de Costa Rica.";
            $messageType = 'success';
            // Refrescar lista después de clasificar
            header("Location: ai-classify.php?factura_id=$invoiceId&success=1");
            exit;
        } else {
            $message = 'Error al procesar la clasificación por IA';
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
    <title>Clasificación Inteligente IA - Sistema de Clasificación Aduanera</title>
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
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><i class="fas fa-brain"></i> Clasificación Inteligente con IA</h1>
                <p class="lead text-secondary">Utilice Vision Multi para identificar automáticamente los códigos
                    arancelarios
                    sugeridos.</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="review-approve.php" class="btn btn-primary">
                    <i class="fas fa-check-double"></i> Ir a Revisión y Aprobación
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary">
                    <i class="fas <?php echo $invoiceId ? 'fa-list' : 'fa-file-invoice'; ?>"></i>
                    <?php echo $invoiceId ? 'Ítems Pendientes por Clasificar' : 'Seleccione una Factura para Clasificar'; ?>
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($invoiceId && $factura): ?>
                        <span class="badge bg-info">Factura #<?php echo $invoiceId; ?> -
                            <?php echo htmlspecialchars($factura['numero_factura']); ?></span>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="classify_all">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-bolt"></i> Clasificar TODA la Factura
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$invoiceId): ?>
                    <!-- Vista de Lista de Facturas -->
                    <?php if (empty($facturasDigitadas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4>No hay facturas pendientes</h4>
                            <p class="text-secondary">Todas las facturas digitalizadas ya han sido procesadas o no hay nuevas
                                facturas.</p>
                            <a href="ocr-upload.php" class="btn btn-primary">Subir Nueva Factura</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Proveedor</th>
                                        <th>Nº Factura</th>
                                        <th>Fecha</th>
                                        <th>Cant. Ítems</th>
                                        <th>Total</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($facturasDigitadas as $f): ?>
                                        <tr>
                                            <td>#<?php echo $f['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($f['proveedor'] ?? 'N/A'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($f['numero_factura'] ?? 'N/A'); ?></td>
                                            <td><?php echo formatDate($f['fecha_factura'] ?? $f['fecha_carga'], 'd/m/Y'); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo $f['total_items']; ?></span></td>
                                            <td><?php echo $f['moneda'] ?? 'USD'; ?>
                                                <?php echo number_format($f['total_factura'] ?? 0, 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="ai-classify.php?factura_id=<?php echo $f['id']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    Seleccionar <i class="fas fa-arrow-right ms-1"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Vista de Ítems de la Factura -->
                    <form method="POST" id="classifyForm">

                        <!-- SECCIÓN: LÍNEAS POR CLASIFICAR -->
                        <div class="mb-5">
                            <h6 class="text-secondary border-bottom pb-2 mb-3">
                                <i class="fas fa-hourglass-half me-1"></i> Líneas Pendientes de Clasificación IA
                                <span class="badge bg-secondary"><?php echo count($pendingItems); ?></span>
                            </h6>

                            <?php if (empty($pendingItems)): ?>
                                <div class="alert alert-light border text-center py-4">
                                    <i class="fas fa-check-circle text-success me-2"></i> No hay líneas pendientes de
                                    procesamiento por IA.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="40"><input type="checkbox" id="selectAll"></th>
                                                <th>Descripción / Info Recibida</th>
                                                <th class="text-center">Cant.</th>
                                                <th class="text-end">Subtotal</th>
                                                <th width="150" class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingItems as $item): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="items_to_classify[]"
                                                            value="<?php echo $item['id']; ?>" class="item-checkbox"></td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($item['descripcion']); ?>
                                                        </div>
                                                        <small class="text-muted">Ref:
                                                            <?php echo $item['codigo_producto'] ?: 'N/A'; ?></small>
                                                    </td>
                                                    <td class="text-center"><?php echo $item['cantidad']; ?></td>
                                                    <td class="text-end"><?php echo number_format($item['subtotal'], 2); ?></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-primary classify-single-btn"
                                                            data-id="<?php echo $item['id']; ?>">
                                                            <i class="fas fa-magic"></i> Clasificar
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-2">
                                    <button type="submit" class="btn btn-primary" id="batchClassifyBtn">
                                        <i class="fas fa-robot"></i> Clasificar Seleccionados con IA
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- SECCIÓN: LÍNEAS CLASIFICADAS -->
                        <div>
                            <h6 class="text-success border-bottom pb-2 mb-3">
                                <i class="fas fa-check-double me-1"></i> Líneas Clasificadas por IA (Pendientes de
                                Aprobación)
                                <span class="badge bg-success"><?php echo count($classifiedItems); ?></span>
                            </h6>

                            <?php if (empty($classifiedItems)): ?>
                                <div class="alert alert-light border text-center py-4 text-muted">
                                    Aún no hay líneas clasificadas por la IA para esta factura.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Descripción</th>
                                                <th class="text-end" width="100">Subtotal</th>
                                                <th class="text-center">Código Sugerido (SAC)</th>
                                                <th class="text-center">Confianza</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classifiedItems as $item): ?>
                                                <tr class="table-success-subtle">
                                                    <td>
                                                        <div class="small fw-bold">
                                                            <?php echo htmlspecialchars($item['descripcion']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-end small"><?php echo number_format($item['subtotal'], 2); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST"
                                                            class="d-flex align-items-center justify-content-center gap-1">
                                                            <input type="hidden" name="action" value="update_manual">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                            <div class="input-group input-group-sm" style="max-width: 150px;">
                                                                <input type="text" name="new_code"
                                                                    class="form-control font-monospace fw-bold text-center p-1"
                                                                    value="<?php echo htmlspecialchars($item['codigo_arancelario_sugerido']); ?>"
                                                                    aria-label="Código Arancelario">
                                                                <button class="btn btn-outline-success" type="submit"
                                                                    title="Guardar cambio manual">
                                                                    <i class="fas fa-save"></i>
                                                                </button>
                                                            </div>
                                                        </form>
                                                        <div class="x-small text-muted d-block mt-1">
                                                            <?php echo htmlspecialchars($item['descripcion_codigo'] ?? ''); ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        $color = $item['confianza'] > 80 ? 'success' : ($item['confianza'] > 50 ? 'warning' : 'danger');
                                                        ?>
                                                        <span
                                                            class="badge bg-<?php echo $color; ?>"><?php echo number_format($item['confianza'], 1); ?>%</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-info"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#detail-<?php echo $item['id']; ?>">
                                                            <i class="fas fa-eye"></i> Info
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-primary classify-single-btn"
                                                            data-id="<?php echo $item['id']; ?>" title="Volver a clasificar">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr class="collapse" id="detail-<?php echo $item['id']; ?>">
                                                    <td colspan="5" class="bg-light p-3">
                                                        <div class="card card-sm shadow-none border">
                                                            <div class="card-body py-2">
                                                                <h7 class="fw-bold text-primary"><i class="fas fa-info-circle"></i>
                                                                    Justificación del Agente Aduanal:</h7>
                                                                <p class="small mb-0 mt-2">
                                                                    <?php echo nl2br(htmlspecialchars($item['explicacion'])); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-5 pt-3 border-top d-flex justify-content-between align-items-center">
                            <a href="ai-classify.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a Lista de Facturas
                            </a>
                            <?php if (!empty($classifiedItems)): ?>
                                <a href="review-approve.php?factura_id=<?php echo $invoiceId; ?>"
                                    class="btn btn-success btn-lg">
                                    Ir a Aprobación Final <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
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
    <script>
        document.getElementById('selectAll')?.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('classifyForm')?.addEventListener('submit', function () {
            app.showSpinner('Clasificando ítems con Inteligencia Artificial (Vision Multi)...');
        });

        document.querySelectorAll('.classify-single-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const itemId = this.getAttribute('data-id');
                const form = document.getElementById('classifyForm');

                // Desmarcar todos y marcar solo este
                document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
                const cb = form.querySelector(`.item-checkbox[value="${itemId}"]`);
                if (cb) cb.checked = true;

                form.submit();
            });
        });
    </script>
</body>

</html>