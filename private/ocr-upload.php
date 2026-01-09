<?php
require_once '../config/config.php';
requireAuth();

// Prevenir timeouts en procesos largos de OCR
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@header('X-Accel-Buffering: no');
@ini_set('memory_limit', '512M');

$invoice = new Invoice();
$ocrService = new OCRService();

$message = '';
$messageType = '';
$errorDetails = '';

// Manejar mensajes de redirección (para retries manuales)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';

    // Si hay detalles técnicos en sesión, recuperarlos
    if (isset($_SESSION['ocr_error_details'])) {
        $errorDetails = $_SESSION['ocr_error_details'];
        unset($_SESSION['ocr_error_details']); // Limpiar para la próxima
    }
}

// Procesar carga de factura
$startOcr = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['factura'])) {
    try {
        $result = $invoice->create($_SESSION['user_id'], $_FILES['factura']);

        if ($result['success']) {
            $facturaId = $result['factura_id'];
            $startOcr = $facturaId; // Marcar para que JS inicie el OCR
            $message = 'Factura subida. Iniciando extracción de datos...';
            $messageType = 'info';
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = 'Error al cargar factura: ' . $e->getMessage();
        $messageType = 'danger';
        $errorDetails = "Excepción capturada:\n" . $e->getTraceAsString();
    }
}

// Obtener facturas del usuario
$facturas = $invoice->getAll(['usuario_id' => $_SESSION['user_id']], 20);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconocimiento OCR - Sistema de Clasificación Aduanera</title>
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
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-file-upload"></i> Reconocimiento de Factura (OCR)</h1>
                <p class="lead text-secondary">Cargue facturas en PDF o imagen para extraer información automáticamente
                </p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorDetails): ?>
            <div class="alert alert-danger">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-triangle"></i> Detalles Técnicos del Error
                </h5>
                <hr>
                <details>
                    <summary style="cursor: pointer; user-select: none;">
                        <strong>Click para ver detalles técnicos</strong>
                    </summary>
                    <pre class="mt-3 p-3 bg-dark text-white rounded"
                        style="max-height: 400px; overflow-y: auto; font-size: 0.85em;"><?php echo htmlspecialchars($errorDetails); ?></pre>
                </details>
                <hr>
                <p class="mb-0">
                    <i class="fas fa-info-circle"></i>
                    <strong>Sugerencias:</strong>
                <ul class="mt-2">
                    <li>Si la factura tiene múltiples páginas, asegúrese de que todas sean legibles</li>
                    <li>Verifique que el archivo PDF no esté corrupto o protegido</li>
                    <li>Intente con una versión de menor tamaño o con menos páginas</li>
                    <li>Si el problema persiste, contacte al administrador con los detalles técnicos arriba</li>
                </ul>
                </p>
            </div>
        <?php endif; ?>

        <!-- Zona de carga -->
        <div class="row mb-5">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-cloud-upload-alt"></i> Cargar Nueva Factura</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="dropzone" id="dropzone">
                                <div class="dropzone-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h4>Arrastre su factura aquí</h4>
                                <p class="text-secondary">o haga clic para seleccionar</p>
                                <p class="text-secondary"><small>Formatos permitidos: PDF, JPG, PNG (Máx. 50MB)</small>
                                </p>
                                <input type="file" name="factura" id="fileInput" accept=".pdf,.jpg,.jpeg,.png"
                                    style="display: none;" required>
                            </div>

                            <div id="fileInfo" class="mt-3" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fas fa-file"></i> <span id="fileName"></span> (<span
                                        id="fileSize"></span>)
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">
                                <i class="fas fa-upload"></i> Cargar y Procesar con OCR
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de facturas -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list"></i> Facturas Cargadas</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($facturas)): ?>
                            <p class="text-center text-secondary">No hay facturas cargadas aún</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Archivo</th>
                                            <th>Fecha Carga</th>
                                            <th>Estado</th>
                                            <th>Ítems</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($facturas as $f): ?>
                                            <tr>
                                                <td>#<?php echo $f['id']; ?></td>
                                                <td>
                                                    <i class="fas fa-file-<?php echo $f['tipo_archivo']; ?>"></i>
                                                    <?php echo htmlspecialchars($f['archivo_original']); ?>
                                                </td>
                                                <td><?php echo formatDate($f['fecha_carga']); ?></td>
                                                <td>
                                                    <?php
                                                    $badgeClass = 'secondary';
                                                    switch ($f['estado']) {
                                                        case 'aprobado':
                                                            $badgeClass = 'success';
                                                            break;
                                                        case 'ocr_completado':
                                                            $badgeClass = 'info';
                                                            break;
                                                        case 'procesando':
                                                            $badgeClass = 'warning';
                                                            break;
                                                        case 'pendiente':
                                                            $badgeClass = 'secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                                        <?php echo ucfirst($f['estado']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $f['total_items'] ?? 0; ?></td>
                                                <td>
                                                    <a href="view-ocr.php?id=<?php echo $f['id']; ?>"
                                                        class="btn btn-sm btn-outline-info" title="Ver Resultados">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($f['estado'] === 'ocr_completado'): ?>
                                                        <a href="auto-digitize.php?factura_id=<?php echo $f['id']; ?>"
                                                            class="btn btn-sm btn-success">
                                                            <i class="fas fa-arrow-right"></i> Digitalizar
                                                        </a>
                                                    <?php elseif (in_array($f['estado'], ['pendiente', 'error', 'procesando', 'pendiente_ocr'])): ?>
                                                        <a href="process-ocr-single.php?id=<?php echo $f['id']; ?>"
                                                            class="btn btn-sm btn-primary btn-process-ocr"
                                                            title="Procesar OCR Manualmente">
                                                            <i class="fas fa-microchip"></i> Procesar OCR
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
    <script>
        // Inicializar dropzone
        app.initDropzone('dropzone', function (file) {
            if (app.validateFile(file)) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                document.getElementById('fileInfo').style.display = 'block';
            }
        });

        // Mostrar spinner al enviar formulario (solo carga inicial)
        document.getElementById('uploadForm').addEventListener('submit', function () {
            app.showSpinner('Subiendo archivo al servidor...');
        });

        /**
         * Función para manejar el polling del estado del OCR
         */
        function startOCRPolling(facturaId) {
            app.showSpinner('Iniciando procesamiento con IA Vision...');

            const formData = new FormData();
            formData.append('factura_id', facturaId);
            fetch('../public/api/start-ocr.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let attempts = 0;
                        const maxAttempts = 120; // 10 minutos (cada 5s)

                        const interval = setInterval(() => {
                            attempts++;
                            app.showSpinner(`Analizando factura con IA... Por favor espera.<br><small class="text-white-50">Intento de verificación: ${attempts}</small><br><small style="font-size: 0.7em; opacity: 0.8;">No cierres esta ventana mientras la IA trabaja.</small>`);

                            fetch(`../public/api/check-ocr-status.php?factura_id=${facturaId}`)
                                .then(res => {
                                    // Si no es un status exitoso (ej: timeout de LiteSpeed), simplemente ignoramos este intento
                                    if (!res.ok) {
                                        console.warn(`Polling: El servidor respondió con status ${res.status}. Reintentando en el próximo ciclo...`);
                                        return null;
                                    }
                                    return res.json();
                                })
                                .then(statusData => {
                                    if (!statusData) return; // Ignorar si hubo error de red/timeout manejado arriba

                                    if (statusData.estado === 'ocr_completado') {
                                        clearInterval(interval);
                                        app.hideSpinner();
                                        window.location.href = 'ocr-upload.php?message=' + encodeURIComponent('OCR completado con éxito') + '&type=success';
                                    } else if (statusData.estado === 'error') {
                                        clearInterval(interval);
                                        app.hideSpinner();
                                        app.showAlert('Error en el procesamiento: ' + (statusData.observaciones || 'Error desconocido'), 'danger');
                                    } else if (attempts >= maxAttempts) {
                                        clearInterval(interval);
                                        app.hideSpinner();
                                        app.showAlert('El procesamiento está tardando más de lo esperado. Por favor, revisa la lista en unos minutos.', 'warning');
                                    }
                                })
                                .catch(err => {
                                    // Error de parseo si no es JSON o error de red
                                    console.error('Polling error (Parse/Network):', err);
                                    // Seguimos intentando, el proceso de fondo es el que manda
                                });
                        }, 5000); // Poll cada 5 segundos
                    } else {
                        app.hideSpinner();
                        app.showAlert(data.message, 'danger');
                    }
                })
                .catch(error => {
                    app.hideSpinner();
                    app.showAlert('Error al iniciar OCR: ' + error.message, 'danger');
                });
        }

        // Manejar procesamiento OCR automático al cargar si viene de un POST exitoso
        <?php if ($startOcr): ?>
            document.addEventListener('DOMContentLoaded', function () {
                startOCRPolling(<?php echo $startOcr; ?>);
            });
        <?php endif; ?>

        // Manejar procesamiento OCR manual desde la tabla
        document.querySelectorAll('.btn-process-ocr').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                const idMatch = url.match(/id=(\d+)/);

                if (idMatch) {
                    startOCRPolling(idMatch[1]);
                } else {
                    window.location.href = url;
                }
            });
        });
    </script>
</body>

</html>