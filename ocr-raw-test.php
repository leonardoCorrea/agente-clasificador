<?php
/**
 * Test de diagnóstico OCR (Raw)
 * Muestra la respuesta exacta enviada por el microservicio Railway
 */
require_once 'config/config.php';
requireAuth();

echo "<h1>Diagnóstico de OCR - Respuesta Raw</h1>";

$db = Database::getInstance();
$facturaId = $_GET['id'] ?? null;

if (!$facturaId) {
    $lastFactura = $db->select("SELECT id FROM facturas ORDER BY id DESC LIMIT 1");
    if (empty($lastFactura)) {
        die("❌ No hay facturas cargadas para probar.");
    }
    $facturaId = $lastFactura[0]['id'];
}

$facturaData = $db->select("SELECT * FROM facturas WHERE id = ?", [$facturaId]);
$factura = $facturaData[0];
$filePath = $factura['ruta_archivo'];

echo "<p>Probando Factura ID: #$facturaId</p>";
echo "<p>Archivo: " . htmlspecialchars($factura['archivo_original']) . "</p>";

if (!file_exists($filePath)) {
    die("❌ Archivo no encontrado en: $filePath");
}

$ocrServiceUrl = OCR_SERVICE_URL;
$url = $ocrServiceUrl . '/api/ocr/process';

echo "<p>Llamando a: <code>$url</code></p>";

$ch = curl_init($url);
$postFields = [
    'file' => new CURLFile($filePath),
    'api_key' => OPENAI_API_KEY
];

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
]);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<h2>❌ Error de cURL:</h2><pre>$err</pre>";
} else {
    echo "<h2>HTTP Status: " . $info['http_code'] . "</h2>";
    echo "<h3>Respuesta RAW del Microservicio:</h3>";
    echo "<pre style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; max-height: 500px; overflow: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h3>✅ JSON Decodificado Correctamente</h3>";
        echo "<pre>";
        print_r($decoded);
        echo "</pre>";
    } else {
        echo "<h3 style='color: red;'>❌ Error al decodificar JSON: " . json_last_error_msg() . "</h3>";
    }
}

echo "<hr><p><a href='private/ocr-upload.php'>Volver al Upload</a></p>";
