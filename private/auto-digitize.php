<?php
require_once '../config/config.php';
requireAuth();

$invoiceId = isset($_GET['factura_id']) ? (int) $_GET['factura_id'] : 0;
if (!$invoiceId) {
    redirect('ocr-upload.php');
}

$invoice = new Invoice();
$ocrService = new OCRService();
$itemManager = new InvoiceItem();

$factura = $invoice->getById($invoiceId);
if (!$factura || ($factura['usuario_id'] != $_SESSION['user_id'] && !hasRole('admin'))) {
    redirect('ocr-upload.php');
}

$ocrResult = $ocrService->getOCRResults($invoiceId);
$items = $itemManager->getByFactura($invoiceId);

$message = '';
$messageType = '';

/**
 * Limpiar valores decimales
 */
function cleanDecimal($value)
{
    if (empty($value))
        return 0;
    $clean = preg_replace('/[^-0-9.]/', '', str_replace(',', '', $value));
    return (float) $clean;
}

/**
 * Intentar formatear fecha a YYYY-MM-DD
 */
function formatToMySQLDate($dateStr)
{
    if (empty($dateStr))
        return null;

    // Si ya viene en formato YYYY-MM-DD, dejarla igual
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }

    $timestamp = strtotime(str_replace('/', '-', $dateStr));
    if ($timestamp) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardar cambios en la factura y los ítems
    $headerData = [
        'proveedor' => $_POST['proveedor'],
        'numero_factura' => $_POST['numero_factura'],
        'fecha_factura' => formatToMySQLDate($_POST['fecha_factura']),
        'total_factura' => cleanDecimal($_POST['total_invoice']),
        'moneda' => $_POST['moneda'],
        'estado' => 'digitado',
        'remitente_nombre' => $_POST['remitente'] ?? null,
        'consignatario_nombre' => $_POST['consignatario'] ?? null,
        'pais_origen' => $_POST['pais_origen'] ?? null
    ];

    $updateResult = $invoice->update($invoiceId, $headerData);

    if ($updateResult['success']) {
        // Procesar ítems
        if (!empty($_POST['items'])) {
            // Eliminar ítems anteriores para esta factura
            $db = Database::getInstance();
            $db->execute("DELETE FROM items_factura WHERE factura_id = ?", [$invoiceId]);

            foreach ($_POST['items'] as $index => $item) {
                $itemData = [
                    'numero_linea' => $index + 1,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => cleanDecimal($item['cantidad']),
                    'precio_unitario' => cleanDecimal($item['precio_unitario']),
                    'subtotal' => cleanDecimal($item['total']),
                    'unidad_medida' => $item['unidad'] ?? null,
                    'numero_serie_parte' => $item['numero_serie_parte'] ?? null,
                    'caracteristicas' => $item['caracteristicas'] ?? null
                ];
                $itemManager->create($invoiceId, $itemData);
            }
        }

        $message = 'Datos de la factura guardados correctamente';
        $messageType = 'success';

        // Recargar ítems actualizados
        $items = $itemManager->getByFactura($invoiceId);
        $factura = $invoice->getById($invoiceId);

        // Opcional: Redirigir a clasificación IA
        // header("Location: ai-classify.php?factura_id=" . $invoiceId);
        // exit;
    } else {
        $message = 'Error al guardar los datos: ' . $updateResult['message'];
        $messageType = 'danger';
    }
}

// Si no hay ítems guardados aún, intentar tomarlos del OCR
if (empty($items) && $ocrResult) {
    $datos = $ocrResult['datos_estructurados'];
    $items = $datos['items'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digitación Automática - Sistema de Clasificación Aduanera</title>
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
                <a href="ocr-upload.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Volver a OCR
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-keyboard"></i> Digitación Automática</h1>
                <p class="lead text-secondary">Los campos se han auto-completado con los datos del OCR. Por favor revise
                    y confirme.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Sección Cabecera (Full Width) -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary"><i class="fas fa-file-invoice"></i> Cabecera de Factura</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Datos Principales -->
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Proveedor</label>
                            <input type="text" name="proveedor" class="form-control"
                                value="<?php echo htmlspecialchars($factura['proveedor'] ?: ($ocrResult['datos_estructurados']['proveedor'] ?? '')); ?>"
                                required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Nº Factura</label>
                            <input type="text" name="numero_factura" class="form-control"
                                value="<?php echo htmlspecialchars($factura['numero_factura'] ?: ($ocrResult['datos_estructurados']['numero_factura'] ?? '')); ?>"
                                required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Fecha</label>
                            <input type="text" name="fecha_factura" class="form-control"
                                value="<?php echo htmlspecialchars($factura['fecha_factura'] ?: ($ocrResult['datos_estructurados']['fecha'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Moneda</label>
                            <input type="text" name="moneda" class="form-control"
                                value="<?php echo htmlspecialchars($factura['moneda'] ?: ($ocrResult['datos_estructurados']['moneda'] ?? 'USD')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Total Factura</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" name="total_invoice" class="form-control fw-bold"
                                    value="<?php echo htmlspecialchars($factura['total_factura'] ?: ($ocrResult['datos_estructurados']['totales']['total_final'] ?? $ocrResult['datos_estructurados']['total'] ?? '0.00')); ?>"
                                    required>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <!-- Remitente / Consignatario / Origen -->
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Remitente (Shipper)</label>
                            <input type="text" name="remitente" class="form-control form-control-sm"
                                placeholder="Nombre del remitente"
                                value="<?php echo htmlspecialchars($ocrResult['datos_estructurados']['remitente']['nombre'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Consignatario (Consignee)</label>
                            <input type="text" name="consignatario" class="form-control form-control-sm"
                                placeholder="Nombre del consignatario"
                                value="<?php echo htmlspecialchars($ocrResult['datos_estructurados']['consignatario']['nombre'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">País de Origen</label>
                            <input type="text" name="pais_origen" class="form-control form-control-sm"
                                placeholder="Ej: CHINA, USA..."
                                value="<?php echo htmlspecialchars($ocrResult['datos_estructurados']['pais_origen'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Ítems (Full Width) -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list"></i> Ítems de Factura</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                        <i class="fas fa-plus"></i> Añadir Ítem
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="itemsTable">
                            <thead class="table-light text-secondary text-uppercase small">
                                <tr>
                                    <th width="50" class="px-3">#</th>
                                    <th width="15%">P/N (Serie)</th>
                                    <th width="25%">Descripción y Características</th>
                                    <th width="10%" class="text-center">Cant.</th>
                                    <th width="10%" class="text-center">Unidad</th>
                                    <th width="15%" class="text-end">P. Unitario</th>
                                    <th width="15%" class="text-end px-4">Total</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="fas fa-box-open fa-2x mb-2"></i><br>
                                            No se detectaron ítems
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $index => $item): ?>
                                        <tr>
                                            <td class="text-center align-middle px-3">
                                                <span
                                                    class="badge bg-secondary"><?php echo $item['numero_linea'] ?? ($index + 1); ?></span>
                                                <input type="hidden" name="items[<?php echo $index; ?>][numero_linea]"
                                                    value="<?php echo $item['numero_linea'] ?? ($index + 1); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="items[<?php echo $index; ?>][numero_serie_parte]"
                                                    class="form-control form-control-sm" placeholder="N/A"
                                                    value="<?php echo htmlspecialchars($item['numero_serie_parte'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                <textarea name="items[<?php echo $index; ?>][descripcion]"
                                                    class="form-control form-control-sm mb-1" rows="1"
                                                    placeholder="Descripción"><?php echo htmlspecialchars($item['descripcion']); ?></textarea>
                                                <input type="text" name="items[<?php echo $index; ?>][caracteristicas]"
                                                    class="form-control form-control-sm form-control-plaintext text-muted small py-0"
                                                    placeholder="Características adicionales..." style="font-size: 0.85rem;"
                                                    value="<?php echo htmlspecialchars($item['caracteristicas'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="items[<?php echo $index; ?>][cantidad]"
                                                    class="form-control form-control-sm text-center fw-bold"
                                                    value="<?php echo $item['cantidad'] ?? 1; ?>" step="0.01">
                                            </td>
                                            <td>
                                                <input type="text" name="items[<?php echo $index; ?>][unidad]"
                                                    class="form-control form-control-sm text-center text-uppercase"
                                                    value="<?php echo htmlspecialchars($item['unidad_medida'] ?? 'UN'); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="items[<?php echo $index; ?>][precio_unitario]"
                                                    class="form-control form-control-sm text-end font-monospace"
                                                    value="<?php echo $item['precio_unitario'] ?? $item['precio'] ?? '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="items[<?php echo $index; ?>][total]"
                                                    class="form-control form-control-sm text-end fw-bold font-monospace px-4"
                                                    value="<?php echo $item['subtotal'] ?? $item['total'] ?? '0.00'; ?>">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm text-danger remove-item p-1"><i
                                                        class="fas fa-trash-alt"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 fixed-bottom bg-white p-3 border-top shadow-lg"
                style="z-index: 1000;">
                <button type="button" class="btn btn-outline-secondary"
                    onclick="window.location.href='view-ocr.php?id=<?php echo $invoiceId; ?>'">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-success btn-lg px-5">
                    <i class="fas fa-save"></i> Guardar Cambios <i class="fas fa-check ms-2"></i>
                </button>
            </div>
        </form>
        <!-- Espacio para el footer flotante -->
        <div style="height: 80px;"></div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <span class="text-white">© <?php echo date('Y'); ?> Sistema de Clasificación Aduanera Inteligente - Todos
                los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemIndex = <?php echo count($items); ?>;

        document.getElementById('addItemBtn').addEventListener('click', function () {
            const tbody = document.getElementById('itemsBody');
            const currentRowCount = tbody.querySelectorAll('tr').length;
            const nextLineNum = tbody.querySelector('.text-muted') ? 1 : currentRowCount + 1;

            const newRow = `
                <tr class="table-info animate__animated animate__fadeIn">
                    <td class="text-center align-middle px-3">
                        <span class="badge bg-secondary">${nextLineNum}</span>
                        <input type="hidden" name="items[${itemIndex}][numero_linea]" value="${nextLineNum}">
                    </td>
                    <td>
                        <input type="text" name="items[${itemIndex}][numero_serie_parte]" class="form-control form-control-sm" placeholder="P/N">
                    </td>
                    <td>
                        <textarea name="items[${itemIndex}][descripcion]" class="form-control form-control-sm mb-1" rows="1" placeholder="Descripción" required></textarea>
                        <input type="text" name="items[${itemIndex}][caracteristicas]" class="form-control form-control-sm form-control-plaintext text-muted small py-0" placeholder="Características..." style="font-size: 0.85rem;">
                    </td>
                    <td><input type="number" name="items[${itemIndex}][cantidad]" class="form-control form-control-sm text-center fw-bold" value="1" step="0.01"></td>
                    <td><input type="text" name="items[${itemIndex}][unidad]" class="form-control form-control-sm text-center text-uppercase" value="UN"></td>
                    <td><input type="text" name="items[${itemIndex}][precio_unitario]" class="form-control form-control-sm text-end font-monospace" value="0.00"></td>
                    <td><input type="text" name="items[${itemIndex}][total]" class="form-control form-control-sm text-end fw-bold font-monospace px-4" value="0.00"></td>
                    <td><button type="button" class="btn btn-sm text-danger remove-item p-1"><i class="fas fa-trash-alt"></i></button></td>
                </tr>
            `;

            if (tbody.querySelector('.text-muted')) {
                tbody.innerHTML = newRow;
            } else {
                tbody.insertAdjacentHTML('beforeend', newRow);
            }

            itemIndex++;

            const lastRow = tbody.lastElementChild;
            lastRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => {
                lastRow.classList.remove('table-info');
            }, 2000);
        });

        document.getElementById('itemsBody').addEventListener('click', function (e) {
            if (e.target.closest('.remove-item')) {
                e.target.closest('tr').remove();
                if (document.getElementById('itemsBody').rows.length === 0) {
                    document.getElementById('itemsBody').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-box-open fa-2x mb-2"></i><br>No hay ítems</td></tr>';
                }
            }
        });
    </script>
</body>

</html>