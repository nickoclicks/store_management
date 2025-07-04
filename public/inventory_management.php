<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../src/config/db.php';

$msg = '';

// Handle manual stock adjustment
if (isset($_POST['adjust'])) {
    $product_id = (int)$_POST['product_id'];
    $change = (int)$_POST['quantity_change'];
    $note = $conn->real_escape_string($_POST['note']);
    // Update product quantity
    $conn->query("UPDATE products SET quantity = quantity + ($change) WHERE id = $product_id");
    // Log the adjustment
    $action = 'adjustment';
    $conn->query("INSERT INTO stock_logs (product_id, quantity_change, action, note) VALUES ($product_id, $change, '$action', '$note')");
    $msg = 'Stock adjusted.';
}

// --- FILTERS ---
$filter_name = isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '';
$filter_stock = isset($_GET['filter_stock']) ? $_GET['filter_stock'] : '';
$where = [];
if ($filter_name !== '') {
    $where[] = "name LIKE '%" . $conn->real_escape_string($filter_name) . "%'";
}
if ($filter_stock === 'in') {
    $where[] = "quantity > 0";
} elseif ($filter_stock === 'out') {
    $where[] = "quantity = 0";
}
$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Fetch all products (with filters)
$products = [];
$res = $conn->query('SELECT * FROM products ' . $where_sql . ' ORDER BY name');
while ($row = $res->fetch_assoc()) $products[] = $row;

// Fetch recent stock logs
$logs = [];
$res = $conn->query('SELECT l.*, p.name FROM stock_logs l JOIN products p ON l.product_id = p.id ORDER BY l.created_at DESC LIMIT 10');
while ($row = $res->fetch_assoc()) $logs[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
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
          <li class="nav-item"><a class="nav-link active" href="inventory_management.php"><i class="fa fa-warehouse"></i> Inventory</a></li>
          <li class="nav-item"><a class="nav-link" href="sales_management.php"><i class="fa fa-cash-register"></i> Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fa fa-chart-line"></i> Reports</a></li>
          <li class="nav-item mt-3"><a class="nav-link" href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </nav>
    <main class="col-md-10 ms-sm-auto main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Inventory Management</h2>
      </div>
      <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Product Name
            <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>" class="form-control">
          </label>
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock Status
            <select name="filter_stock" class="form-select">
              <option value="">All</option>
              <option value="in" <?= $filter_stock==='in'?'selected':'' ?>>In Stock</option>
              <option value="out" <?= $filter_stock==='out'?'selected':'' ?>>Out of Stock</option>
            </select>
          </label>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-outline-primary"><i class="fa fa-filter"></i> Filter</button>
          <a href="inventory_management.php" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
          <thead class="table-light">
            <tr>
              <th>Name</th><th>SKU</th><th>Quantity</th><th>Status</th><th>Adjust Stock</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($products as $prod): ?>
            <tr>
              <td><?= htmlspecialchars($prod['name']) ?></td>
              <td><?= htmlspecialchars($prod['sku']) ?></td>
              <td><?= $prod['quantity'] ?></td>
              <td><?= $prod['quantity'] > 0 ? '<span class="badge bg-success">In Stock</span>' : '<span class="badge bg-danger">Out of Stock</span>' ?></td>
              <td>
                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#adjustModal" onclick='openAdjustModal(<?= $prod['id'] ?>, "<?= htmlspecialchars(addslashes($prod['name'])) ?>")'><i class="fa fa-edit"></i> Adjust</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- Modal for Stock Adjustment -->
      <div class="modal fade" id="adjustModal" tabindex="-1" aria-labelledby="adjustModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post" id="adjustForm">
              <div class="modal-header">
                <h5 class="modal-title" id="adjustModalLabel">Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="product_id" id="adj_product_id">
                <div class="mb-3">
                  <label class="form-label">Product</label>
                  <input type="text" id="adj_product_name" class="form-control" readonly>
                </div>
                <div class="mb-3">
                  <label class="form-label">Quantity Change</label>
                  <input type="number" name="quantity_change" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Note</label>
                  <input type="text" name="note" class="form-control" placeholder="Note">
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" name="adjust" class="btn btn-primary">Adjust</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <script>
      function openAdjustModal(id, name) {
        document.getElementById('adj_product_id').value = id;
        document.getElementById('adj_product_name').value = name;
      }
      </script>
      <div class="mt-5">
        <h4>Recent Stock Adjustments</h4>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle bg-white">
            <thead class="table-light">
              <tr><th>Product</th><th>Change</th><th>Action</th><th>Note</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td><?= htmlspecialchars($log['name']) ?></td>
                <td><?= $log['quantity_change'] ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['note']) ?></td>
                <td><?= $log['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </main>
  </div>
</div>
</body>
</html> 