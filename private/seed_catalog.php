<?php
require_once '../config/config.php';
require_once '../classes/CatalogService.php';

// Check admin or cli
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
    // Permitir acceso temporalmente si está logueado como cualquiera para facilitar pruebas, o restringir.
    // die("Acceso denegado");
}

$catalogService = new CatalogService();

echo "<h1>Sembrando Catálogo Arancelario (SAC Costa Rica)</h1>";
echo "<p>Iniciando proceso...</p>";

$items = [
    // CAFÉ Y PRODUCTOS AGRÍCOLAS
    ['codigo_sac' => '0901.21.00.00', 'descripcion' => 'Café tostado sin descafeinar', 'dai' => 14, 'sc' => 0, 'iva' => 1, 'ley' => 1],
    ['codigo_sac' => '0901.11.30.00', 'descripcion' => 'Café oro (grano crudo)', 'dai' => 9, 'sc' => 0, 'iva' => 1, 'ley' => 1],
    ['codigo_sac' => '0804.40.00.00', 'descripcion' => 'Aguacates (paltas), frescos o secos', 'dai' => 14, 'sc' => 0, 'iva' => 1, 'ley' => 1],

    // TECNOLOGÍA Y ELECTRÓNICA
    ['codigo_sac' => '8517.13.00.00', 'descripcion' => 'Teléfonos inteligentes (Smartphones)', 'dai' => 0, 'sc' => 0, 'iva' => 13, 'ley' => 1],
    ['codigo_sac' => '8471.30.00.00', 'descripcion' => 'Máquinas automáticas para tratamiento de datos, portátiles (Laptops)', 'dai' => 0, 'sc' => 0, 'iva' => 13, 'ley' => 1],
    ['codigo_sac' => '8528.59.00.00', 'descripcion' => 'Monitores y proyectores', 'dai' => 5, 'sc' => 0, 'iva' => 13, 'ley' => 1],

    // ROPA Y TEXTILES
    ['codigo_sac' => '6109.10.00.00', 'descripcion' => 'Camisetas (T-shirts) de algodón', 'dai' => 14, 'sc' => 0, 'iva' => 13, 'ley' => 1],
    ['codigo_sac' => '6203.42.00.00', 'descripcion' => 'Pantalones largos de algodón para hombres', 'dai' => 14, 'sc' => 0, 'iva' => 13, 'ley' => 1],

    // VEHÍCULOS
    ['codigo_sac' => '8703.22.59.00', 'descripcion' => 'Vehículos turismo gasolina >1000cm3 <= 1500cm3', 'dai' => 14, 'sc' => 30, 'iva' => 13, 'ley' => 1],
    ['codigo_sac' => '8703.80.00.00', 'descripcion' => 'Vehículos eléctricos puros', 'dai' => 0, 'sc' => 0, 'iva' => 1, 'ley' => 0],

    // HOGAR
    ['codigo_sac' => '9403.50.00.00', 'descripcion' => 'Muebles de madera de los tipos utilizados en dormitorios', 'dai' => 14, 'sc' => 0, 'iva' => 13, 'ley' => 1],
    ['codigo_sac' => '8516.50.00.00', 'descripcion' => 'Hornos de microondas', 'dai' => 14, 'sc' => 0, 'iva' => 13, 'ley' => 1]
];

$count = 0;
foreach ($items as $item) {
    if ($catalogService->importCatalogItem($item)) {
        $count++;
    }
}

echo "<p class='text-success'>Se han importado/actualizado <strong>$count</strong> partidas arancelarias exitosamente.</p>";
echo "<br><a href='dashboard.php'>Volver al Dashboard</a>";
