<?php
/**
 * API: Iniciar OCR en segundo plano (Background)
 * Responde inmediatamente y sigue procesando.
 */
require_once '../../config/config.php';

// Autenticación
if (!isAuthenticated()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Liberar el bloqueo de sesión inmediatamente para permitir polling concurrente
session_write_close();

$facturaId = $_POST['factura_id'] ?? null;
if (!$facturaId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de factura no proporcionado']);
    exit;
}

// 1. Preparar la respuesta inmediata y desvincular el proceso
if (ob_get_level())
    ob_end_clean();
ignore_user_abort(true);
set_time_limit(0);

ob_start();
header('Content-Type: application/json');
header('Connection: close');
header('Content-Encoding: none');
echo json_encode([
    'success' => true,
    'message' => 'Procesamiento iniciado en segundo plano',
    'factura_id' => $facturaId
]);
// Padding para forzar el flush en algunos servidores (LiteSpeed/Nginx)
echo str_pad('', 4096);
$size = ob_get_length();
header("Content-Length: $size");
ob_end_flush();
@ob_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 2. A PARTIR DE AQUÍ EL CLIENTE YA RECIBIÓ LA RESPUESTA
// Asegurar que existe el directorio de logs
$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/ocr_background.log';
function logBg($msg)
{
    global $logFile, $facturaId;
    $date = date('Y-m-d H:i:s');
    $fullMsg = "[$date][Factura $facturaId] $msg\n";
    file_put_contents($logFile, $fullMsg, FILE_APPEND);
    // También guardar en el error_log del sistema por si falla file_put_contents
    error_log("OCR_BG: " . $fullMsg);
}

logBg("Iniciando proceso de fondo. Desvinculado con éxito.");
logBg("Sesión User ID: " . ($_SESSION['user_id'] ?? 'N/A'));

// Configuración extrema para procesos largos
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// 3. Ejecutar el OCR real
try {
    $ocrService = new OCRService();
    logBg("Llamando a OCRService->processInvoice...");
    $result = $ocrService->processInvoice($facturaId);

    if ($result['success']) {
        logBg("Éxito: " . $result['message']);
    } else {
        logBg("Error en OCRService: " . $result['message']);
    }
} catch (Exception $e) {
    logBg("EXCEPCIÓN CRÍTICA: " . $e->getMessage());
    $db = Database::getInstance();
    $db->update('facturas', [
        'estado' => 'error',
        'observaciones' => 'Error Background: ' . $e->getMessage()
    ], ['id' => $facturaId]);
}
logBg("Proceso de fondo finalizado.");
