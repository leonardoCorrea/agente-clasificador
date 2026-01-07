<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

/**
 * Helper function to check if a column exists
 */
function columnExists($conn, $table, $column)
{
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper function to add a column if it doesn't exist
 */
function addColumnIfNotExists($conn, $table, $column, $definition)
{
    if (columnExists($conn, $table, $column)) {
        echo "Skipped: Column '$column' already exists in table '$table'.\n";
        return;
    }

    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
    try {
        $conn->exec($sql);
        echo "Executed: Added column '$column' to table '$table'.\n";
    } catch (PDOException $e) {
        echo "Error adding column '$column': " . $e->getMessage() . "\n";
    }
}

// Facturas Table Columns
$facturasColumns = [
    'remitente_nombre' => "VARCHAR(255) NULL",
    'remitente_direccion' => "TEXT NULL",
    'remitente_contacto' => "VARCHAR(255) NULL",
    'remitente_telefono' => "VARCHAR(50) NULL",
    'consignatario_nombre' => "VARCHAR(255) NULL",
    'consignatario_direccion' => "TEXT NULL",
    'consignatario_contacto' => "VARCHAR(255) NULL",
    'pais_consignatario' => "VARCHAR(100) NULL",
    'pais_origen' => "VARCHAR(100) NULL",
    'subtotal' => "DECIMAL(15,2) DEFAULT 0.00",
    'descuento' => "DECIMAL(15,2) DEFAULT 0.00",
    'impuesto_calculado' => "DECIMAL(15,2) DEFAULT 0.00",
    'impuesto_porcentaje' => "DECIMAL(5,2) DEFAULT 0.00",
    'total_final' => "DECIMAL(15,2) DEFAULT 0.00"
];

foreach ($facturasColumns as $col => $def) {
    addColumnIfNotExists($conn, 'facturas', $col, $def);
}

// Items Factura Table Columns
$itemsColumns = [
    'numero_serie_parte' => "VARCHAR(100) NULL",
    'caracteristicas' => "TEXT NULL",
    'datos_importantes' => "TEXT NULL",
    'precio_total' => "DECIMAL(15,2) DEFAULT 0.00"
];

foreach ($itemsColumns as $col => $def) {
    addColumnIfNotExists($conn, 'items_factura', $col, $def);
}

echo "Database update completed.\n";
