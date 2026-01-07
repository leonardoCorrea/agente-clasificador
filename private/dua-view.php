<?php
require_once '../config/config.php';
requireAuth();

$duaService = new DUAService();
$duaId = $_GET['id'] ?? null;

$message = '';
$messageType = 'info';

if (!$duaId) {
    die("ID de DUA no especificado");
}

// Handle POST actions (Finalizar DUA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'finalize') {
        $result = $duaService->finalizeDua($duaId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    }
}

$dua = $duaService->getDua($duaId);
if (!$dua) {
    die("DUA no encontrado");
}

$h = $dua['header'];
$items = $dua['items'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Detalle DUA - Sistema Aduanero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/styles.css">
    <style>
        .tax-column {
            background-color: #f8f9fa;
            font-size: 0.9em;
        }

        .tax-header {
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-file-invoice"></i> Sistema Aduanero
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0">
                    <i class="fas fa-file-contract"></i>
                    <?php echo $h['estado'] === 'borrador' ? 'Borrador de DUA' : 'DUA Generado'; ?>
                </h2>
                <span class="text-muted">Ref: <?php echo $h['numero_referencia']; ?></span>
            </div>
            <div>
                <?php if ($h['estado'] === 'borrador'): ?>
                    <!-- Botón para finalizar DUA -->
                    <form method="POST" style="display: inline;"
                        onsubmit="return confirm('¿Está seguro de finalizar este DUA? Una vez finalizado, no se podrá modificar.');">
                        <input type="hidden" name="action" value="finalize">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Finalizar DUA
                        </button>
                    </form>
                    <a href="dua-create.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                <?php else: ?>
                    <!-- Botones para DUA generado -->
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button class="btn btn-success" disabled>
                        <i class="fas fa-upload"></i> Transmitir a TICA
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Header Info -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Aduana</small>
                        <h5><?php echo $h['aduana_control']; ?> (Santamaría)</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Valor CIF Total</small>
                        <h5 class="text-primary">$<?php echo number_format($h['valor_cif_total'] ?? 0, 2); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Total Impuestos</small>
                        <h5 class="text-danger">¢<?php echo number_format($h['total_impuestos'] ?? 0, 2); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Estado</small>
                        <h5>
                            <?php
                            $badgeClass = 'secondary';
                            $statusText = strtoupper($h['estado']);
                            if ($h['estado'] === 'generado') {
                                $badgeClass = 'success';
                                $statusText = 'GENERADO';
                            } elseif ($h['estado'] === 'transmitido') {
                                $badgeClass = 'info';
                                $statusText = 'TRANSMITIDO';
                            } elseif ($h['estado'] === 'borrador') {
                                $badgeClass = 'warning';
                                $statusText = 'BORRADOR';
                            }
                            ?>
                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle de Liquidación -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="m-0"><i class="fas fa-calculator"></i> Liquidación de Impuestos (Detalle por Línea)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark text-center">
                        <tr>
                            <th rowspan="2" class="align-middle">Lín.</th>
                            <th rowspan="2" class="align-middle">Partida (SAC)</th>
                            <th rowspan="2" class="align-middle" style="width: 20%;">Descripción</th>
                            <th rowspan="2" class="align-middle">CIF Linea ($)</th>
                            <th colspan="4">Cálculo de Impuestos (CRC/USD)</th>
                            <th rowspan="2" class="align-middle bg-danger">Total Imp.</th>
                        </tr>
                        <tr class="tax-header">
                            <th>DAI</th>
                            <th>Selectivo</th>
                            <th>Ley 1%</th>
                            <th>IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $taxes = json_decode($item['porcentajes_aplicados'], true);
                            ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $item['numero_linea']; ?></td>
                                <td class="text-center font-monospace text-primary"><?php echo $item['codigo_sac']; ?></td>
                                <td>
                                    <div><?php echo $item['descripcion_mercancia']; ?></div>
                                    <small class="text-muted">Valor FOB:
                                        $<?php echo number_format($item['valor_fob'] ?? 0, 2); ?></small>
                                </td>
                                <td class="text-end fw-bold">$<?php echo number_format($item['valor_cif'] ?? 0, 2); ?></td>

                                <!-- DAI -->
                                <td class="text-end tax-column">
                                    <div><?php echo number_format($item['dai_monto'] ?? 0, 2); ?></div>
                                    <small class="text-muted"><?php echo $taxes['dai']; ?>%</small>
                                </td>

                                <!-- SC -->
                                <td class="text-end tax-column">
                                    <div><?php echo number_format($item['sc_monto'] ?? 0, 2); ?></div>
                                    <small class="text-muted"><?php echo $taxes['sc']; ?>%</small>
                                </td>

                                <!-- Ley -->
                                <td class="text-end tax-column">
                                    <div><?php echo number_format($item['ley_monto'] ?? 0, 2); ?></div>
                                    <small class="text-muted">1%</small>
                                </td>

                                <!-- IVA -->
                                <td class="text-end tax-column">
                                    <div><?php echo number_format($item['iva_monto'] ?? 0, 2); ?></div>
                                    <small class="text-muted"><?php echo $taxes['iva']; ?>%</small>
                                </td>

                                <td class="text-end fw-bold text-danger bg-light">
                                    <?php echo number_format($item['total_impuestos_linea'] ?? 0, 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end">
                <small class="text-muted">Nota: Cálculos estimados basados en arancel 2024. Tipo de cambio
                    referencial.</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>