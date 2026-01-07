<?php
require_once '../config/config.php';
requireAuth();

$invoice = new Invoice();
$duaService = new DUAService();

$message = '';
$messageType = 'danger'; // default to error

// Handle POST to create DUA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_ids'])) {
    $result = $duaService->createDraftFromInvoices($_SESSION['user_id'], $_POST['invoice_ids']);
    if ($result['success']) {
        // If items were skipped, show warning message before redirecting
        if (isset($result['items_skipped']) && $result['items_skipped'] > 0) {
            $message = $result['message'];
            $messageType = 'warning';
            // Don't redirect immediately, show the warning
        } else {
            // All items processed successfully, redirect
            header("Location: dua-view.php?id=" . $result['dua_id']);
            exit;
        }
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get Invoices ready for DUA (Approved or at least Classified)
// For simplicity, showing all completed/approved ones
$facturas = $invoice->getAll(['usuario_id' => $_SESSION['user_id']], 50);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Generar DUA - Sistema Aduanero</title>
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
        </div>
    </nav>

    <div class="container mt-5">
        <h1><i class="fas fa-passport"></i> Generar Borrador de DUA</h1>
        <p class="lead text-muted">Seleccione las facturas que desea incluir en esta Declaración Única Aduanera.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    Facturas Disponibles
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Factura #</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturas as $f): ?>
                                <?php if (in_array($f['estado'], ['ocr_completado', 'digitado', 'clasificado', 'aprobado'])): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="invoice_ids[]" value="<?php echo $f['id']; ?>"
                                                class="form-check-input invoice-check">
                                        </td>
                                        <td><?php echo $f['numero_factura'] ?: 'S/N'; ?> <small class="text-muted">(ID:
                                                <?php echo $f['id']; ?>)</small></td>
                                        <td><?php echo $f['proveedor']; ?></td>
                                        <td><?php echo formatDate($f['fecha_factura']); ?></td>
                                        <td><?php echo $f['moneda'] . ' ' . number_format($f['total_factura'], 2); ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'bg-secondary';
                                            if ($f['estado'] === 'aprobado')
                                                $badgeClass = 'bg-success';
                                            elseif ($f['estado'] === 'clasificado')
                                                $badgeClass = 'bg-info';
                                            elseif ($f['estado'] === 'digitado')
                                                $badgeClass = 'bg-primary';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $f['estado']; ?></span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white py-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-cogs"></i> Generar Borrador DUA
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('selectAll').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.invoice-check');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>

</html>