<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../src/config/db.php';

// Handle export
function export_csv($filename, $header, $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
function export_pdf($title, $html) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
    echo '<h2>' . htmlspecialchars($title) . '</h2>' . $html;
    exit;
}

// Sales report
$period = isset($_GET['period']) ? $_GET['period'] : 'day';
$where = '';
$group = '';
$label = '';
if ($period === 'day') {
    $group = 'DATE(created_at)';
    $label = 'Day';
} elseif ($period === 'week') {
    $group = 'YEAR(created_at), WEEK(created_at)';
    $label = 'Week';
} else {
    $group = 'YEAR(created_at), MONTH(created_at)';
    $label = 'Month';
}
$sales_report = [];
$res = $conn->query("SELECT $group as grp, SUM(total_amount) as total, COUNT(*) as sales_count FROM sales GROUP BY $group ORDER BY grp DESC LIMIT 12");
while ($row = $res->fetch_assoc()) $sales_report[] = $row;

// Inventory report
$low_stock = [];
$res = $conn->query('SELECT name, sku, quantity FROM products WHERE quantity <= 5 AND quantity > 0 ORDER BY quantity ASC');
while ($row = $res->fetch_assoc()) $low_stock[] = $row;
$out_of_stock = [];
$res = $conn->query('SELECT name, sku FROM products WHERE quantity = 0');
while ($row = $res->fetch_assoc()) $out_of_stock[] = $row;

// Export handlers
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'sales_csv') {
        $rows = array_map(function($r) use ($label) {
            return [$r['grp'], $r['total'], $r['sales_count']];
        }, $sales_report);
        export_csv('sales_report.csv', [$label, 'Total Sales', 'Number of Sales'], $rows);
    } elseif ($_GET['export'] === 'inventory_csv') {
        $rows = array_merge(
            array_map(function($r) { return [$r['name'], $r['sku'], $r['quantity'], 'Low Stock']; }, $low_stock),
            array_map(function($r) { return [$r['name'], $r['sku'], 0, 'Out of Stock']; }, $out_of_stock)
        );
        export_csv('inventory_report.csv', ['Product', 'SKU', 'Quantity', 'Status'], $rows);
    } elseif ($_GET['export'] === 'sales_pdf') {
        ob_start();
        echo '<table border="1" cellpadding="5"><tr><th>' . $label . '</th><th>Total Sales</th><th>Number of Sales</th></tr>';
        foreach ($sales_report as $r) {
            echo '<tr><td>' . htmlspecialchars($r['grp']) . '</td><td>' . $r['total'] . '</td><td>' . $r['sales_count'] . '</td></tr>';
        }
        echo '</table>';
        $html = ob_get_clean();
        export_pdf('sales_report', $html);
    } elseif ($_GET['export'] === 'inventory_pdf') {
        ob_start();
        echo '<table border="1" cellpadding="5"><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Status</th></tr>';
        foreach ($low_stock as $r) {
            echo '<tr><td>' . htmlspecialchars($r['name']) . '</td><td>' . htmlspecialchars($r['sku']) . '</td><td>' . $r['quantity'] . '</td><td>Low Stock</td></tr>';
        }
        foreach ($out_of_stock as $r) {
            echo '<tr><td>' . htmlspecialchars($r['name']) . '</td><td>' . htmlspecialchars($r['sku']) . '</td><td>0</td><td>Out of Stock</td></tr>';
        }
        echo '</table>';
        $html = ob_get_clean();
        export_pdf('inventory_report', $html);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <nav class="col-md-2 d-none d-md-block sidebar">
      <div class="position-sticky">
        <ul class="nav flex-column">
          <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="product_management.php"><i class="fa fa-box"></i> Products</a></li>
          <li class="nav-item"><a class="nav-link" href="inventory_management.php"><i class="fa fa-warehouse"></i> Inventory</a></li>
          <li class="nav-item"><a class="nav-link" href="sales_management.php"><i class="fa fa-cash-register"></i> Sales</a></li>
          <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="fa fa-chart-line"></i> Reports</a></li>
          <li class="nav-item mt-3"><a class="nav-link" href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </nav>
    <main class="col-md-10 ms-sm-auto main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Reports</h2>
      </div>
      <div class="card mb-4">
        <div class="card-header bg-primary text-white"><i class="fa fa-chart-line"></i> Sales Report</div>
        <div class="card-body">
          <form method="get" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Period
                <select name="period" class="form-select">
                  <option value="day" <?= $period==='day'?'selected':'' ?>>Day</option>
                  <option value="week" <?= $period==='week'?'selected':'' ?>>Week</option>
                  <option value="month" <?= $period==='month'?'selected':'' ?>>Month</option>
                </select>
              </label>
            </div>
            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-outline-primary"><i class="fa fa-filter"></i> Show</button>
            </div>
          </form>
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle bg-white">
              <thead class="table-light">
                <tr><th><?= $label ?></th><th>Total Sales</th><th>Number of Sales</th></tr>
              </thead>
              <tbody>
              <?php foreach ($sales_report as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['grp']) ?></td>
                  <td><?= $r['total'] ?></td>
                  <td><?= $r['sales_count'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <a href="?period=<?= $period ?>&export=sales_csv" class="btn btn-success me-2"><i class="fa fa-file-csv"></i> Export to Excel (CSV)</a>
          <a href="?period=<?= $period ?>&export=sales_pdf" class="btn btn-danger"><i class="fa fa-file-pdf"></i> Export to PDF</a>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header bg-info text-white"><i class="fa fa-warehouse"></i> Inventory Report</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle bg-white">
              <thead class="table-light">
                <tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Status</th></tr>
              </thead>
              <tbody>
              <?php foreach ($low_stock as $r): ?>
                <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['sku']) ?></td><td><?= $r['quantity'] ?></td><td><span class="badge bg-warning text-dark">Low Stock</span></td></tr>
              <?php endforeach; ?>
              <?php foreach ($out_of_stock as $r): ?>
                <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['sku']) ?></td><td>0</td><td><span class="badge bg-danger">Out of Stock</span></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <a href="?export=inventory_csv" class="btn btn-success me-2"><i class="fa fa-file-csv"></i> Export to Excel (CSV)</a>
          <a href="?export=inventory_pdf" class="btn btn-danger"><i class="fa fa-file-pdf"></i> Export to PDF</a>
        </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </main>
  </div>
</div>
</body>
</html>
