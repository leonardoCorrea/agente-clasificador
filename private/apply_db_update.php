<?php
require_once '../config/config.php';

echo "Iniciando actualización de base de datos...\n";

$db = Database::getInstance();
$sqlFile = __DIR__ . '/../database/schema_update.sql';

if (!file_exists($sqlFile)) {
    die("Error: No se encuentra el archivo SQL en $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split query by semicolon to execute individually if needed, 
// but Database::execute might handle multiple statements depending on driver.
// Safest is to try executing the whole block or split it.
// Here we will try to split by ";\n" or similar to be safe given the CREATE TABLE logic.

$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    if (!empty($stmt)) {
        try {
            $db->execute($stmt);
            echo "Ejecutado correctamente: " . substr($stmt, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "Advertencia al ejecutar: " . $e->getMessage() . "\n";
            // Continue as "Table already exists" is common
        }
    }
}

echo "Actualización completada.\n";
