<?php
/**
 * API: Consultar estado del OCR
 * Se usa para Polling desde el frontend.
 */
require_once '../../config/config.php';

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

$facturaId = $_GET['factura_id'] ?? null;
if (!$facturaId) {
    jsonResponse(['success' => false, 'message' => 'ID de factura no proporcionado'], 400);
}

try {
    $db = Database::getInstance();
    $factura = $db->queryOne("SELECT id, estado, observaciones FROM facturas WHERE id = ?", [$facturaId]);

    if (!$factura) {
        jsonResponse(['success' => false, 'message' => 'Factura no encontrada'], 404);
    }

    $result = [
        'success' => true,
        'estado' => $factura['estado'],
        'observaciones' => $factura['observaciones']
    ];

    // Si ya terminó, podemos incluir un mensaje de éxito o info adicional
    if ($factura['estado'] === 'ocr_completado') {
        $result['message'] = 'OCR completado exitosamente';
    } elseif ($factura['estado'] === 'error') {
        $result['message'] = 'Error en el procesamiento: ' . ($factura['observaciones'] ?? 'Error desconocido');
    }

    jsonResponse($result);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Error al consultar estado: ' . $e->getMessage()
    ], 500);
}
