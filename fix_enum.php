<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Update the ENUM definition to include 'ocr_completado' and other potentially missing statuses
    $sql = "ALTER TABLE facturas MODIFY COLUMN estado ENUM('pendiente', 'procesando', 'ocr_completado', 'digitado', 'clasificado', 'aprobado', 'rechazado', 'error') DEFAULT 'pendiente'";
    $conn->exec($sql);
    echo "Successfully updated 'estado' column definition in 'facturas' table.\n";
} catch (PDOException $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
