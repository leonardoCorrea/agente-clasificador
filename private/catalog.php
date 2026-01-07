<?php
require_once '../config/config.php';
requireAuth();

$catalogService = new CatalogService();
$searchTerm = $_GET['q'] ?? '';
$results = [];

if ($searchTerm) {
    $results = $catalogService->search($searchTerm);
}

// Simple seed check for demo purposes
// In production this would be a real import tool
if (empty($results) && $searchTerm == 'demo') {
    // Insertar datos de prueba comunes
    $catalogService->importCatalogItem([
        'codigo_sac' => '0901.21.00.00',
        'descripcion' => 'Café tostado sin descafeinar',
        'dai' => 10,
        'sc' => 0,
        'iva' => 1,
        'ley' => 1
    ]);
    $catalogService->importCatalogItem([
        'codigo_sac' => '8517.13.00.00',
        'descripcion' => 'Teléfonos inteligentes (smartphones)',
        'dai' => 0,
        'sc' => 0,
        'iva' => 13,
        'ley' => 1
    ]);
    header("Location: catalog.php?q=demo");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Arancelario (SAC) - Sistema Aduanero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-file-invoice"></i> Sistema Aduanero
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-book"></i> Catálogo Arancelario (SAC Costa Rica)</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-lg"
                        placeholder="Buscar por código (ej. 8517) o descripción..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </form>
                <div class="form-text mt-2">
                    Tip: Escribe 'demo' y busca para cargar datos de prueba si el catálogo está vacío.
                </div>
            </div>
        </div>

        <?php if ($searchTerm && empty($results)): ?>
            <div class="alert alert-warning">
                No se encontraron resultados para "<?php echo htmlspecialchars($searchTerm); ?>".
            </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <div class="card">
                <div class="card-header bg-white">
                    Resultados de búsqueda
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código SAC</th>
                                <th style="width: 40%">Descripción</th>
                                <th>DAI %</th>
                                <th>SC %</th>
                                <th>Ley 1%</th>
                                <th>IVA %</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $item): ?>
                                <tr>
                                    <td><span class="badge bg-primary"
                                            style="font-size: 1em;"><?php echo $item['codigo_sac']; ?></span></td>
                                    <td><?php echo $item['descripcion']; ?></td>
                                    <td><?php echo $item['dai']; ?>%</td>
                                    <td><?php echo $item['sc']; ?>%</td>
                                    <td><?php echo $item['ley_6946']; ?>%</td>
                                    <td><?php echo $item['iva']; ?>%</td>
                                    <td>
                                        <?php if ($item['nota_tecnica']): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-file-contract"></i> Nota
                                                Técnica</span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>