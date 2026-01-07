<?php
require_once 'config/config.php';
$db = Database::getInstance();
$invoiceId = 16;

$invoice = $db->queryOne("SELECT * FROM facturas WHERE id = ?", [$invoiceId]);
echo "Invoice ID: " . $invoiceId . "\n";
echo "Status: " . ($invoice['estado'] ?? 'NOT FOUND') . "\n";
echo "Observations: " . ($invoice['observaciones'] ?? 'NONE') . "\n";

$ocrResults = $db->query("SELECT * FROM resultados_ocr WHERE factura_id = ?", [$invoiceId]);
echo "OCR Results found: " . count($ocrResults) . "\n";
foreach ($ocrResults as $r) {
    echo "Result ID: " . $r['id'] . " | Method: " . $r['metodo_ocr'] . " | Date: " . $r['fecha_procesamiento'] . "\n";
}

// Check if there are other invoices related to the same file
if ($invoice) {
    $related = $db->query("SELECT id, estado FROM facturas WHERE archivo_original = ? AND id != ?", [$invoice['archivo_original'], $invoiceId]);
    echo "Related Invoices (same file): " . count($related) . "\n";
    foreach ($related as $rel) {
        echo "ID: " . $rel['id'] . " | Status: " . $rel['estado'] . "\n";
    }
}
?>