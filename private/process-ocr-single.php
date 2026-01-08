<?php
require_once '../config/config.php';
requireAuth();

// Extrema prevención de timeouts
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@header('X-Accel-Buffering: no');
@ini_set('memory_limit', '512M');

$ocrService = new OCRService();

$facturaId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'ocr-upload.php';

if ($facturaId > 0) {
    try {
        $result = $ocrService->processInvoice($facturaId);

        if ($result['success']) {
            header("Location: $redirect?message=" . urlencode('OCR procesado exitosamente') . "&type=success");
        } else {
            // Guardar error detallado en sesión para mostrarlo
            $_SESSION['ocr_error_details'] = $result['error_details'] ?? $result['message'];
            header("Location: $redirect?message=" . urlencode('Error en OCR: ' . $result['message']) . "&type=danger");
        }
    } catch (Exception $e) {
        $_SESSION['ocr_error_details'] = $e->getMessage() . "\n\n" . $e->getTraceAsString();
        header("Location: $redirect?message=" . urlencode('Excepción en OCR: ' . $e->getMessage()) . "&type=danger");
    }
} else {
    header("Location: $redirect?message=" . urlencode('ID de factura inválido') . "&type=danger");
}
exit;
