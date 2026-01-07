<?php
require_once '../config/config.php';
requireAuth();

$invoiceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$invoiceId) {
    redirect('ocr-upload.php');
}

$invoice = new Invoice();
$ocrService = new OCRService();

$factura = $invoice->getById($invoiceId);
if (!$factura || ($factura['usuario_id'] != $_SESSION['user_id'] && !hasRole('admin'))) {
    redirect('ocr-upload.php');
}

$ocrResult = $ocrService->getOCRResults($invoiceId);

$message = '';
$messageType = '';

// Manejar acción de corroboración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'corroborate') {
        $corroboration = $ocrService->corroborateInvoice($invoiceId);
        if ($corroboration['success']) {
            $message = $corroboration['message'];
            $messageType = 'success';
            // Recargar resultados
            $ocrResult = $ocrService->getOCRResults($invoiceId);
            $factura = $invoice->getById($invoiceId); // Recargar info de factura
        } else {
            $message = $corroboration['message'];
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
    <title>Ver resultados OCR - Sistema de Clasificación Aduanera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/styles.css">
    <style>
        .ocr-text-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
            font-size: 0.9rem;
        }

        .data-card {
            border-left: 4px solid var(--primary-color);
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
            <div class="ms-auto">
                <a href="ocr-upload.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Volver a Cargas
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><i class="fas fa-eye"></i> Resultados OCR</h1>
                <p class="lead text-secondary">Visualización de los datos extraídos para la factura
                    #<?php echo $invoiceId; ?></p>
            </div>
            <div class="col-md-4 text-end gap-2 d-flex justify-content-end align-items-center">
                <?php if ($factura['estado'] === 'ocr_completado'): ?>
                    <a href="auto-digitize.php?factura_id=<?php echo $invoiceId; ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Ir a Digitación
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$ocrResult): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No se han encontrado resultados OCR para esta factura.
                Es posible que aún se esté procesando o que haya ocurrido un error.
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Datos Estructurados (Cabecera) -->
                <div class="col-md-6">
                    <div class="card mb-4 data-card h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-primary"><i class="fas fa-file-invoice"></i> Datos del Encabezado</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $datos = $ocrResult['datos_estructurados'];
                            ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th class="text-secondary">Nº Factura:</th>
                                            <td class="fw-bold">
                                                <?php echo htmlspecialchars($datos['numero_factura'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-secondary">Fecha:</th>
                                            <td><?php echo htmlspecialchars($datos['fecha'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-secondary">Moneda:</th>
                                            <td><?php echo htmlspecialchars($datos['moneda'] ?? 'USD'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded text-center">
                                        <small class="text-muted d-block text-uppercase">Total Factura</small>
                                        <h3 class="text-primary mb-0">
                                            <?php echo htmlspecialchars($datos['totales']['total_final'] ?? $datos['total'] ?? '0.00'); ?>
                                        </h3>
                                        <small class="text-success fw-bold">Confianza:
                                            <?php echo $ocrResult['confianza_promedio']; ?>%</small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted text-uppercase small mb-2">Proveedor</h6>
                                    <p class="mb-2 fw-bold"><?php echo htmlspecialchars($datos['proveedor'] ?? 'N/A'); ?>
                                    </p>
                                    <?php if (!empty($datos['remitente']['nombre'])): ?>
                                        <p class="small text-muted mb-0"><i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($datos['remitente']['direccion'] ?? $datos['remitente']['nombre']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted text-uppercase small mb-2">Consignatario</h6>
                                    <?php if (!empty($datos['consignatario']['nombre'])): ?>
                                        <p class="mb-2 fw-bold">
                                            <?php echo htmlspecialchars($datos['consignatario']['nombre']); ?></p>
                                        <p class="small text-muted mb-0"><i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($datos['consignatario']['direccion'] ?? $datos['consignatario']['pais'] ?? ''); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-muted small">No detectado</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Texto Completo -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-primary"><i class="fas fa-align-left"></i> Texto Extraído (Raw)</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyOCRText()">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="ocr-text-container border-0" id="ocrText"
                                style="max-height: 400px; border-radius: 0;">
                                <?php echo htmlspecialchars($ocrResult['texto_extraido']); ?>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">Método: <?php echo strtoupper($ocrResult['metodo_ocr'] === 'gpt-4o-vision-multi' ? 'OCR Engine' : $ocrResult['metodo_ocr']); ?> |
                                Procesado: <?php echo formatDate($ocrResult['fecha_procesamiento'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Ítems Full Width -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-primary"><i class="fas fa-list"></i> Ítems de la Factura <span
                                        class="badge bg-secondary rounded-pill ms-2"><?php echo count($datos['items'] ?? []); ?></span>
                                </h5>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-light text-secondary text-uppercase small">
                                        <tr>
                                            <th class="px-3" width="50">#</th>
                                            <th width="30%">Descripción</th>
                                            <th width="20%">Info Adicional</th>
                                            <th class="text-center" width="10%">Cantidad</th>
                                            <th class="text-end" width="15%">P. Unitario</th>
                                            <th class="text-end px-4" width="15%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($datos['items'])): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">
                                                    <i class="fas fa-box-open fa-3x mb-3"></i><br>
                                                    No se detectaron ítems individuales
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (($datos['items'] ?? []) as $index => $item): ?>
                                                <tr>
                                                    <td class="px-3 text-center">
                                                        <span
                                                            class="badge bg-light text-dark border"><?php echo $item['numero_linea'] ?? ($index + 1); ?></span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="fw-bold d-block text-dark"><?php echo htmlspecialchars($item['descripcion']); ?></span>
                                                        <?php if (!empty($item['caracteristicas'])): ?>
                                                            <small class="text-muted"><i
                                                                    class="fas fa-info-circle small me-1"></i><?php echo htmlspecialchars($item['caracteristicas']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($item['numero_serie_parte'])): ?>
                                                            <div class="small fw-bold text-secondary">P/N:
                                                                <?php echo htmlspecialchars($item['numero_serie_parte']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['datos_importantes'])): ?>
                                                            <div class="small text-muted">
                                                                <?php echo htmlspecialchars($item['datos_importantes']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="fw-bold"><?php echo $item['cantidad']; ?></span>
                                                        <span
                                                            class="small text-muted text-uppercase ms-1"><?php echo htmlspecialchars($item['unidad_medida'] ?? ''); ?></span>
                                                    </td>
                                                    <td class="text-end font-monospace">
                                                        <?php echo $item['precio_unitario'] ?? $item['precio'] ?? '0.00'; ?>
                                                    </td>
                                                    <td class="text-end px-4 font-monospace fw-bold text-primary">
                                                        <?php echo $item['precio_total'] ?? $item['total'] ?? '0.00'; ?>
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
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3">
        <div class="container text-center">
            <span class="text-white">© <?php echo date('Y'); ?> Sistema de Clasificación Aduanera Inteligente - Todos
                los derechos reservados.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
    <script>
        function copyOCRText() {
            const text = document.getElementById('ocrText').innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Texto copiado al portapapeles');
            });
        }

    </script>
</body>

</html>