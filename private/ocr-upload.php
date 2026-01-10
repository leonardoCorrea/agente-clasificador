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
        $result = $invoice->create($_SESSION['user_id'], $_FILES['factura'], $_POST);

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
    <style>
        /* Estilos Críticos para el Modal de OCR - Incrustados para asegurar carga */
        .ocr-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 24, 40, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ocr-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .ocr-modal-content {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 2.5rem;
            padding: 3.5rem;
            max-width: 550px;
            width: 90%;
            text-align: center;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.7);
            color: #fff;
            transform: translateY(30px) scale(0.95);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .ocr-modal-overlay.active .ocr-modal-content {
            transform: translateY(0) scale(1);
        }

        .ocr-loader-container {
            width: 140px;
            height: 140px;
            margin: 0 auto 2.5rem;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Spinner Moderno */
        .ocr-main-spinner {
            width: 100%;
            height: 100%;
            border: 4px solid rgba(255, 255, 255, 0.05);
            border-top: 4px solid #00f2fe;
            border-right: 4px solid #4facfe;
            border-radius: 50%;
            animation: ocr-spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
            filter: drop-shadow(0 0 10px rgba(79, 172, 254, 0.5));
        }

        @keyframes ocr-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .ocr-brain-icon {
            position: absolute;
            font-size: 3.5rem;
            background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: ocr-pulse 2s infinite ease-in-out;
        }

        @keyframes ocr-pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.8;
            }

            50% {
                transform: scale(1.1);
                opacity: 1;
            }
        }

        .ocr-status-title {
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }

        .ocr-steps {
            list-style: none;
            padding: 0;
            margin: 2.5rem 0;
            text-align: left;
            display: inline-block;
        }

        .ocr-step {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
            opacity: 0.3;
            transition: all 0.4s ease;
            font-size: 1.05rem;
        }

        .ocr-step.active {
            opacity: 1;
            color: #4facfe;
            font-weight: 600;
            transform: translateX(10px);
        }

        .ocr-step.completed {
            opacity: 0.8;
            color: #00f2fe;
        }

        .ocr-step-icon {
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .ocr-step.completed .ocr-step-icon {
            background: rgba(0, 242, 254, 0.1);
            color: #00f2fe;
        }

        .ocr-tip-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .ocr-tip-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #00f2fe, #4facfe);
        }

        .ocr-tip-tag {
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            color: #4facfe;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            display: block;
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

                            <div class="mt-4">
                                <label for="items_esperados" class="form-label text-secondary fw-bold">
                                    <i class="fas fa-list-ol"></i> ¿Cuántas líneas tiene la factura? (Opcional)
                                </label>
                                <input type="number" class="form-control form-control-lg" name="items_esperados"
                                    id="items_esperados" placeholder="Ej: 26" min="1">
                                <div class="form-text">Si ingresas el número exacto, la IA se esforzará por encontrar
                                    esa cantidad.</div>
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
                                                    <?php if ($f['estado'] === 'procesando'): ?>
                                                        <br>
                                                        <a href="reset-ocr-status.php?id=<?php echo $f['id']; ?>"
                                                            class="text-danger" style="font-size: 0.7em;">Reiniciar</a>
                                                    <?php endif; ?>
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

    <!-- Enhanced OCR Processing Modal (Spinner Loader Style) -->
    <div id="ocrModal" class="ocr-modal-overlay">
        <div class="ocr-modal-content">
            <div class="ocr-loader-container">
                <div class="ocr-main-spinner"></div>
                <i class="fas fa-brain ocr-brain-icon"></i>
            </div>

            <h2 class="ocr-status-title">Procesando Factura</h2>
            <p id="ocrMainStatus" class="text-white-50">Sincronizando con el motor de IA Vision...</p>

            <div class="text-start d-flex justify-content-center">
                <ul class="ocr-steps">
                    <li id="step1" class="ocr-step active">
                        <div class="ocr-step-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <span>Carga de documento</span>
                    </li>
                    <li id="step2" class="ocr-step">
                        <div class="ocr-step-icon"><i class="fas fa-eye"></i></div>
                        <span>Análisis de estructura</span>
                    </li>
                    <li id="step3" class="ocr-step">
                        <div class="ocr-step-icon"><i class="fas fa-microchip"></i></div>
                        <span>Extracción de datos</span>
                    </li>
                    <li id="step4" class="ocr-step">
                        <div class="ocr-step-icon"><i class="fas fa-check-double"></i></div>
                        <span>Validación y Coherencia</span>
                    </li>
                </ul>
            </div>

            <div class="ocr-tip-box">
                <span class="ocr-tip-tag">Avance...</span>
                <p id="ocrTip" class="m-0 text-white-50" style="font-size: 0.95rem; line-height: 1.5;">
                    Nuestra IA está analizando cada ítem para asegurar la clasificación correcta.
                </p>
            </div>

            <div class="mt-4">
                <span class="badge rounded-pill bg-dark border border-secondary text-secondary px-3 py-2"
                    id="ocrAttempt" style="font-size: 0.75rem;">
                    Intento de verificación: 0
                </span>
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
        /**
         * Lógica de OCR y UI - Versión Mejorada
         */
        document.addEventListener('DOMContentLoaded', function () {
            console.log("OCR: Página cargada, inicializando componentes...");

            // 1. Inicializar dropzone
            if (typeof app !== 'undefined' && app.initDropzone) {
                app.initDropzone('dropzone', function (file) {
                    if (app.validateFile(file)) {
                        document.getElementById('fileName').textContent = file.name;
                        document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                        document.getElementById('fileInfo').style.display = 'block';
                    }
                });
            }

            // 2. Mostrar spinner al enviar formulario (solo carga inicial)
            const uploadForm = document.getElementById('uploadForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function () {
                    console.log("OCR: Formulario enviado, subiendo archivo...");
                    if (typeof app !== 'undefined' && app.showSpinner) {
                        app.showSpinner('Subiendo archivo al servidor...');
                    }
                });
            }

            // 3. Manejar procesamiento OCR automático al cargar si viene de un POST exitoso
            <?php if ($startOcr): ?>
                console.log("OCR: Detectada factura recién subida ID: <?php echo $startOcr; ?>. Iniciando polling...");
                setTimeout(() => {
                    startOCRPolling(<?php echo $startOcr; ?>);
                }, 500);
            <?php endif; ?>

            // 4. Manejar procesamiento OCR manual desde la tabla
            document.querySelectorAll('.btn-process-ocr').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    const idMatch = url.match(/id=(\d+)/);

                    if (idMatch) {
                        console.log("OCR: Click en procesar manual para ID: " + idMatch[1]);
                        startOCRPolling(idMatch[1]);
                    } else {
                        window.location.href = url;
                    }
                });
            });
        });

        /**
         * Función principal para manejar el polling del estado del OCR con UI Mejorada
         */
        function startOCRPolling(facturaId) {
            console.log("OCR: startOCRPolling iniciado para facturaId: " + facturaId);

            const modal = document.getElementById('ocrModal');
            const mainStatus = document.getElementById('ocrMainStatus');
            const attemptLabel = document.getElementById('ocrAttempt');
            const tipLabel = document.getElementById('ocrTip');

            if (!modal) {
                console.error("OCR ERROR: No se encontró el modal 'ocrModal' en el DOM.");
                return;
            }

            const tips = [
                "Nuestra IA puede detectar más de 50 idiomas y formatos de factura internacionales.",
                "El sistema valida automáticamente que (Cantidad x Precio) coincida con el Total.",
                "Estamos priorizando el motor de Railway para mayor precisión y velocidad.",
                "Las facturas PDF se procesan página por página para no perder ningún detalle.",
                "Si la factura es muy larga, el proceso puede tardar un poco más. ¡Ten paciencia!",
                "La IA extrae automáticamente remitente, consignatario y todos los ítems de la tabla.",
                "Una vez terminado, podrás digitalizar la factura con un solo clic."
            ];

            const updateTip = () => {
                const randomTip = tips[Math.floor(Math.random() * tips.length)];
                if (tipLabel) {
                    tipLabel.style.opacity = 0;
                    setTimeout(() => {
                        tipLabel.textContent = randomTip;
                        tipLabel.style.opacity = 0.9;
                    }, 500);
                }
            };

            const setStep = (stepNumber, status = 'active') => {
                console.log("OCR UI: Actualizando a paso " + stepNumber + " (" + status + ")");
                const steps = [1, 2, 3, 4];
                steps.forEach(s => {
                    const el = document.getElementById('step' + s);
                    if (!el) return;

                    if (s < stepNumber) {
                        el.className = 'ocr-step completed';
                        const icon = el.querySelector('i');
                        if (icon) icon.className = 'fas fa-check-circle';
                    } else if (s === stepNumber) {
                        el.className = 'ocr-step ' + status;
                    } else {
                        el.className = 'ocr-step';
                    }
                });
            };

            // Activar visualmente el modal
            modal.classList.add('active');
            let tipInterval = setInterval(updateTip, 6000);
            updateTip();
            setStep(1); // Empezar en paso 1

            const formData = new FormData();
            formData.append('factura_id', facturaId);

            console.log("OCR: Solicitando inicio de proceso al servidor...");
            fetch('../public/api/start-ocr.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) throw new Error("Error HTTP " + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log("OCR: Respuesta del servidor exitosa, iniciando polling de estado.");
                        let attempts = 0;
                        const maxAttempts = 150;
                        setStep(2);
                        if (mainStatus) mainStatus.textContent = "Analizando el documento...";

                        const interval = setInterval(() => {
                            attempts++;
                            if (attemptLabel) attemptLabel.textContent = `Intento de verificación: ${attempts}`;

                            // Progresión visual estimada
                            if (attempts === 5) {
                                setStep(3);
                                if (mainStatus) mainStatus.textContent = "La IA está extrayendo los datos...";
                            } else if (attempts === 15) {
                                setStep(4);
                                if (mainStatus) mainStatus.textContent = "Validando y finalizando...";
                            }

                            const controller = new AbortController();
                            const timeoutId = setTimeout(() => controller.abort(), 25000);

                            fetch(`../public/api/check-ocr-status.php?factura_id=${facturaId}`, { signal: controller.signal })
                                .then(res => {
                                    clearTimeout(timeoutId);
                                    if (!res.ok) throw new Error("Status " + res.status);
                                    return res.json();
                                })
                                .then(statusData => {
                                    if (statusData.estado === 'ocr_completado') {
                                        console.log("OCR: ¡COMPLETADO!");
                                        clearInterval(interval);
                                        clearInterval(tipInterval);
                                        setStep(5); // Completar todos
                                        if (mainStatus) mainStatus.textContent = "¡Extracción completada con éxito!";
                                        setTimeout(() => {
                                            window.location.href = 'ocr-upload.php?message=' + encodeURIComponent('OCR completado con éxito') + '&type=success';
                                        }, 1500);
                                    } else if (statusData.estado === 'error') {
                                        console.error("OCR Server Error: " + (statusData.observaciones || "Error desconocido"));
                                        clearInterval(interval);
                                        clearInterval(tipInterval);
                                        modal.classList.remove('active');
                                        if (typeof app !== 'undefined' && app.showAlert) {
                                            app.showAlert('Error en el procesamiento: ' + (statusData.observaciones || 'Error desconocido'), 'danger');
                                        }
                                    }

                                    if (attempts >= maxAttempts) {
                                        console.warn("OCR: Tiempo de espera máximo alcanzado.");
                                        clearInterval(interval);
                                        clearInterval(tipInterval);
                                        modal.classList.remove('active');
                                        if (typeof app !== 'undefined' && app.showAlert) {
                                            app.showAlert('El procesamiento está tardando demasiado. Revisa la lista en unos minutos.', 'warning');
                                        }
                                    }
                                })
                                .catch(err => {
                                    clearTimeout(timeoutId);
                                    console.warn("OCR Polling Warning: " + err.message);
                                });
                        }, 5000); // Polling cada 5 segundos
                    } else {
                        console.error("OCR Failure: " + data.message);
                        modal.classList.remove('active');
                        if (typeof app !== 'undefined' && app.showAlert) {
                            app.showAlert(data.message, 'danger');
                        }
                    }
                })
                .catch(error => {
                    console.error("OCR Critical Error: " + error.message);
                    modal.classList.remove('active');
                    if (typeof app !== 'undefined' && app.showAlert) {
                        app.showAlert('Error crítico al iniciar OCR: ' + error.message, 'danger');
                    }
                });
        }
    </script>
</body>

</html>