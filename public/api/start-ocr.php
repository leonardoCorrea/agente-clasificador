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

$facturaId = $_POST['factura_id'] ?? null;
if (!$facturaId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de factura no proporcionado']);
    exit;
}

// 1. Preparar la respuesta inmediata
ob_start();
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Procesamiento iniciado en segundo plano',
    'factura_id' => $facturaId
]);
$size = ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
@ob_flush();
flush();

// 2. A PARTIR DE AQUÍ EL CLIENTE YA RECIBIÓ LA RESPUESTA
// Pero el script sigue corriendo en el servidor.

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Configuración extrema para procesos largos
ignore_user_abort(true);
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// 3. Ejecutar el OCR real
try {
    $ocrService = new OCRService();
    // processInvoice ya actualiza la DB con 'procesando' y luego 'ocr_completado' o 'error'
    $ocrService->processInvoice($facturaId);
} catch (Exception $e) {
    // Si falla aquí, processInvoice ya debería haber puesto el estado en 'error'
    // Pero por si acaso, lo aseguramos
    $db = Database::getInstance();
    $db->update('facturas', [
        'estado' => 'error',
        'observaciones' => 'Error Background: ' . $e->getMessage()
    ], ['id' => $facturaId]);
}
