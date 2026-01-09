<?php
/**
 * API: Procesar OCR asíncronamente
 * Recibe el ID de la factura y ejecuta el motor OCR
 */
require_once '../../config/config.php';

// Limpiar buffers de salida para evitar problemas con JSON
@ob_clean();

// Autenticación básica
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

$facturaId = $_POST['factura_id'] ?? null;

if (!$facturaId) {
    jsonResponse(['success' => false, 'message' => 'ID de factura no proporcionado'], 400);
}

// Prevenir timeouts en la petición AJAX también
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@header('X-Accel-Buffering: no');

try {
    $ocrService = new OCRService();
    $result = $ocrService->processInvoice($facturaId);

    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'message' => $result['message'],
            'count' => $result['count'] ?? 0
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => $result['message'],
            'error_details' => $result['error_details'] ?? '',
            'stderr' => $result['stderr'] ?? ''
        ], 200); // Enviamos 200 para que el JS maneje el error lógicamente
    }
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Excepción en API: ' . $e->getMessage()
    ], 500);
}
