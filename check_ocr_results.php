<?php
require_once 'config/config.php';
$db = Database::getInstance();
$id = 18;
$res = $db->select('SELECT * FROM resultados_ocr WHERE factura_id = ?', [$id]);
if (empty($res)) {
    echo "No hay resultados OCR para la factura $id\n";
} else {
    foreach ($res as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "Metodo: " . $row['metodo_ocr'] . "\n";
        echo "Texto extraído (primeros 200 chars): " . substr($row['texto_extraido'], 0, 200) . "...\n";
        echo "Datos estructurados: " . $row['datos_estructurados'] . "\n";
        echo "-------------------\n";
    }
}
