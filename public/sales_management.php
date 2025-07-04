<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../src/config/db.php';

$msg = '';

// Handle new sale
if (isset($_POST['record_sale'])) {
    $product_ids = $_POST['product_id'];
    $quantities = $_POST['quantity'];
    $total = 0;
    $items = [];
    foreach ($product_ids as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)$quantities[$i];
        if ($pid && $qty > 0) {
            $res = $conn->query("SELECT price, quantity FROM products WHERE id = $pid");
            if ($res && $row = $res->fetch_assoc()) {
                if ($row['quantity'] < $qty) {
                    $msg = 'Not enough stock for one or more products.';
                    break;
                }
                $price = $row['price'];
                $total += $price * $qty;
                $items[] = ['id' => $pid, 'qty' => $qty, 'price' => $price];
            }
        }
    }
    if ($msg === '' && count($items) > 0) {
        // Insert sale
        $conn->query("INSERT INTO sales (user_id, total_amount) VALUES ({$_SESSION['admin_id']}, $total)");
        $sale_id = $conn->insert_id;
        foreach ($items as $item) {
            $conn->query("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES ($sale_id, {$item['id']}, {$item['qty']}, {$item['price']})");
            // Update product stock
            $conn->query("UPDATE products SET quantity = quantity - {$item['qty']} WHERE id = {$item['id']}");
            // Log stock change
            $conn->query("INSERT INTO stock_logs (product_id, quantity_change, action, note) VALUES ({$item['id']}, -{$item['qty']}, 'sale', 'Sale #$sale_id')");
        }
        $msg = 'Sale recorded. <a href="?receipt=' . $sale_id . '">View Receipt</a>';
    }
}

// Fetch products for sale form
$products = [];
$res = $conn->query('SELECT id, name, price, quantity FROM products WHERE quantity > 0 ORDER BY name');
while ($row = $res->fetch_assoc()) $products[] = $row;

// Fetch sales history (with date filter)
$sales = [];
$where = '';
$filter_date = '';
if (isset($_GET['date']) && $_GET['date']) {
    $filter_date = $_GET['date'];
    $where = "WHERE DATE(s.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
}
$res = $conn->query("SELECT s.*, u.name as user_name FROM sales s LEFT JOIN users u ON s.user_id = u.id $where ORDER BY s.created_at DESC LIMIT 20");
while ($row = $res->fetch_assoc()) $sales[] = $row;

// Fetch receipt if requested
$receipt = null;
$receipt_items = [];
if (isset($_GET['receipt'])) {
    $rid = (int)$_GET['receipt'];
    $res = $conn->query("SELECT s.*, u.name as user_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = $rid");
    if ($res && $receipt = $res->fetch_assoc()) {
        $res2 = $conn->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $rid");
        while ($row = $res2->fetch_assoc()) $receipt_items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Management</title>
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
          <li class="nav-item"><a class="nav-link active" href="sales_management.php"><i class="fa fa-cash-register"></i> Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fa fa-chart-line"></i> Reports</a></li>
          <li class="nav-item mt-3"><a class="nav-link" href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </nav>
    <main class="col-md-10 ms-sm-auto main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Sales Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#saleModal"><i class="fa fa-plus"></i> Record Sale</button>
      </div>
      <!-- Modal for New Sale -->
      <div class="modal fade" id="saleModal" tabindex="-1" aria-labelledby="saleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post" id="saleForm">
              <div class="modal-header">
                <h5 class="modal-title" id="saleModalLabel">Record New Sale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <table class="table table-borderless mb-0">
                  <tr><th>Product</th><th>Quantity</th></tr>
                  <?php for ($i = 0; $i < 3; $i++): ?>
                  <tr>
                    <td>
                      <select name="product_id[]" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($products as $prod): ?>
                          <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="number" name="quantity[]" min="1" value="1" class="form-control" style="width:90px;"></td>
                  </tr>
                  <?php endfor; ?>
                </table>
              </div>
              <div class="modal-footer">
                <button type="submit" name="record_sale" class="btn btn-primary">Record Sale</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <?php if ($msg): ?>
          <div class="alert alert-success"> <?= $msg ?> </div>
        <?php endif; ?>
      </div>
      <div class="mb-4">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Filter by date
              <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control">
            </label>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary"><i class="fa fa-filter"></i> Filter</button>
            <a href="sales_management.php" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
          <thead class="table-light">
            <tr><th>ID</th><th>User</th><th>Total</th><th>Date</th><th>Receipt</th></tr>
          </thead>
          <tbody>
          <?php foreach ($sales as $sale): ?>
            <tr>
              <td><?= $sale['id'] ?></td>
              <td><?= htmlspecialchars($sale['user_name']) ?></td>
              <td><?= $sale['total_amount'] ?></td>
              <td><?= $sale['created_at'] ?></td>
              <td><a href="?receipt=<?= $sale['id'] ?>" class="btn btn-sm btn-info"><i class="fa fa-receipt"></i> View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($receipt): ?>
        <div class="card mt-4">
          <div class="card-header bg-info text-white"><i class="fa fa-receipt"></i> Sales Receipt #<?= $receipt['id'] ?></div>
          <div class="card-body">
            <p><strong>Date:</strong> <?= $receipt['created_at'] ?><br>
            <strong>Sold by:</strong> <?= htmlspecialchars($receipt['user_name']) ?><br>
            <strong>Total:</strong> <?= $receipt['total_amount'] ?></p>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($receipt_items as $item): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= $item['price'] ?></td>
                    <td><?= $item['price'] * $item['quantity'] ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </main>
  </div>
</div>
</body>
</html> 