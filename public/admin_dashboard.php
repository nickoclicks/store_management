<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../src/config/db.php';

// Total products
$totalProducts = 0;
$res = $conn->query('SELECT COUNT(*) as cnt FROM products');
if ($res && $row = $res->fetch_assoc()) {
    $totalProducts = $row['cnt'];
}

// Stock alerts (low quantity, e.g., quantity <= 5)
$stockAlerts = [];
$res = $conn->query('SELECT name, quantity FROM products WHERE quantity <= 5 ORDER BY quantity ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $stockAlerts[] = $row;
    }
}

// Recent sales (last 5)
$recentSales = [];
$res = $conn->query('SELECT s.id, s.total_amount, s.created_at, u.name as user_name FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 5');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentSales[] = $row;
    }
}

// Top-selling products (by quantity sold, top 5)
$topSelling = [];
$res = $conn->query('SELECT p.name, SUM(si.quantity) as total_sold FROM sale_items si JOIN products p ON si.product_id = p.id GROUP BY si.product_id ORDER BY total_sold DESC LIMIT 5');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topSelling[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
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
          <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="product_management.php"><i class="fa fa-box"></i> Products</a></li>
          <li class="nav-item"><a class="nav-link" href="inventory_management.php"><i class="fa fa-warehouse"></i> Inventory</a></li>
          <li class="nav-item"><a class="nav-link" href="sales_management.php"><i class="fa fa-cash-register"></i> Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fa fa-chart-line"></i> Reports</a></li>
          <li class="nav-item mt-3"><a class="nav-link" href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </nav>
    <main class="col-md-10 ms-sm-auto main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</h2>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card shadow-sm text-center">
            <div class="card-body">
              <i class="fa fa-box fa-2x mb-2 text-primary"></i>
              <h5 class="card-title">Total Products</h5>
              <p class="card-text fs-4 fw-bold"><?= $totalProducts ?></p>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card shadow-sm text-center">
            <div class="card-body">
              <i class="fa fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
              <h5 class="card-title">Stock Alerts</h5>
              <p class="card-text fs-4 fw-bold"><?= count($stockAlerts) ?></p>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card shadow-sm text-center">
            <div class="card-body">
              <i class="fa fa-receipt fa-2x mb-2 text-success"></i>
              <h5 class="card-title">Recent Sales</h5>
              <p class="card-text fs-4 fw-bold"><?= count($recentSales) ?></p>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card shadow-sm text-center">
            <div class="card-body">
              <i class="fa fa-star fa-2x mb-2 text-info"></i>
              <h5 class="card-title">Top Products</h5>
              <p class="card-text fs-4 fw-bold"><?= count($topSelling) ?></p>
            </div>
          </div>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-warning text-dark"><i class="fa fa-exclamation-triangle"></i> Stock Alerts (≤ 5)</div>
            <div class="card-body p-2">
              <?php if (count($stockAlerts) > 0): ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($stockAlerts as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <?= htmlspecialchars($item['name']) ?>
                      <span class="badge bg-danger rounded-pill">Qty: <?= $item['quantity'] ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-muted">No low stock alerts.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-success text-white"><i class="fa fa-receipt"></i> Recent Sales</div>
            <div class="card-body p-2">
              <?php if (count($recentSales) > 0): ?>
                <table class="table table-sm table-hover mb-0">
                  <thead><tr><th>ID</th><th>User</th><th>Total</th><th>Date</th></tr></thead>
                  <tbody>
                  <?php foreach ($recentSales as $sale): ?>
                    <tr>
                      <td><?= $sale['id'] ?></td>
                      <td><?= htmlspecialchars($sale['user_name']) ?></td>
                      <td><?= $sale['total_amount'] ?></td>
                      <td><?= $sale['created_at'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="text-muted">No recent sales.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="row g-4 mt-4">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-info text-white"><i class="fa fa-star"></i> Top-Selling Products</div>
            <div class="card-body p-2">
              <?php if (count($topSelling) > 0): ?>
                <ol class="mb-0">
                  <?php foreach ($topSelling as $prod): ?>
                    <li><?= htmlspecialchars($prod['name']) ?> <span class="badge bg-primary">Sold: <?= $prod['total_sold'] ?></span></li>
                  <?php endforeach; ?>
                </ol>
              <?php else: ?>
                <div class="text-muted">No sales data available.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 