<?php
require_once '../config/config.php';
requireAuth();

$duaService = new DUAService();
$duas = $duaService->getUserDuas($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mis DUAs - Sistema Aduanero</title>
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
                <a href="dashboard.php" class="btn btn-light"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-list"></i> Mis DUAs</h1>
            <a href="dua-create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crear Nuevo DUA
            </a>
        </div>

        <?php if (empty($duas)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No tiene DUAs creados aún.
                <a href="dua-create.php" class="alert-link">Crear su primer DUA</a>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Referencia</th>
                                <th>Fecha Generación</th>
                                <th>Régimen</th>
                                <th>Valor CIF Total</th>
                                <th>Total Impuestos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duas as $dua): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $dua['numero_referencia']; ?></strong>
                                        <br><small class="text-muted">ID: <?php echo $dua['id']; ?></small>
                                    </td>
                                    <td><?php echo formatDate($dua['fecha_generacion']); ?></td>
                                    <td><?php echo $dua['regimen_aduanero']; ?></td>
                                    <td class="text-end">$<?php echo number_format($dua['valor_cif_total'] ?? 0, 2); ?></td>
                                    <td class="text-end text-danger">
                                        ¢<?php echo number_format($dua['total_impuestos'] ?? 0, 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = 'secondary';
                                        if ($dua['estado'] === 'generado')
                                            $badgeClass = 'success';
                                        elseif ($dua['estado'] === 'transmitido')
                                            $badgeClass = 'info';
                                        elseif ($dua['estado'] === 'borrador')
                                            $badgeClass = 'warning';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo strtoupper($dua['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="dua-view.php?id=<?php echo $dua['id']; ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                        <?php if ($dua['estado'] === 'generado'): ?>
                                            <button class="btn btn-sm btn-outline-secondary"
                                                onclick="window.open('dua-view.php?id=<?php echo $dua['id']; ?>', '_blank'); window.print();">
                                                <i class="fas fa-print"></i>
                                            </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>