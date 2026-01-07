<?php
/**
 * Test de integración OCR
 * Ejecutar desde: https://ticosoftcr.com/agenteClasificador/test-ocr.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/OCRService.php';

echo "<h1>Test de Integración OCR</h1>";

// Verificar que tenemos una factura de prueba
$db = Database::getInstance();
$facturas = $db->select("SELECT * FROM facturas WHERE estado = 'pendiente' LIMIT 1");

if (empty($facturas)) {
    echo "<p>❌ No hay facturas pendientes para probar</p>";
    echo "<p>Sube una factura primero desde la aplicación</p>";
    exit;
}

$factura = $facturas[0];
echo "<h2>Factura de Prueba</h2>";
echo "<pre>";
echo "ID: " . $factura['id'] . "\n";
echo "Archivo: " . $factura['ruta_archivo'] . "\n";
echo "Estado: " . $factura['estado'] . "\n";
echo "</pre>";

// Verificar que el archivo existe
if (!file_exists($factura['ruta_archivo'])) {
    echo "<p>❌ El archivo no existe: " . $factura['ruta_archivo'] . "</p>";
    exit;
}

echo "<p>✅ Archivo existe</p>";

// Probar OCR
echo "<h2>Procesando OCR...</h2>";
echo "<p>Esto puede tardar 30-60 segundos...</p>";
flush();

try {
    $ocrService = new OCRService();
    $result = $ocrService->processInvoice($factura['id']);

    echo "<h3>✅ Resultado del OCR:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";

    if ($result['success']) {
        echo "<p style='color: green; font-weight: bold;'>🎉 ¡OCR FUNCIONÓ CORRECTAMENTE!</p>";
    } else {
        echo "<p style='color: red;'>❌ OCR falló: " . ($result['message'] ?? 'Error desconocido') . "</p>";
    }

} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<pre style='color: red;'>";
    echo "Mensaje: " . $e->getMessage() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='diagnostic.php'>← Volver al diagnóstico</a></p>";
