<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../src/config/db.php';

// Handle add/update/delete
$msg = '';
$editing = false;
$editProduct = null;

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE id = $id");
    $msg = 'Product deleted.';
}

// Handle add/update form submission
if (isset($_POST['save'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $conn->real_escape_string($_POST['name']);
    $sku = $conn->real_escape_string($_POST['sku']);
    $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : 'NULL';
    $supplier_id = $_POST['supplier_id'] ? (int)$_POST['supplier_id'] : 'NULL';
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $description = $conn->real_escape_string($_POST['description']);
    $image = '';

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
        $imgName = uniqid('prod_') . '_' . basename($_FILES['image']['name']);
        $target = 'assets/images/' . $imgName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $target)) {
            $image = $target;
        }
    }

    if ($id) {
        // Update
        $setImage = $image ? ", image = '$image'" : '';
        $sql = "UPDATE products SET name='$name', sku='$sku', category_id=$category_id, supplier_id=$supplier_id, price=$price, quantity=$quantity, description='$description' $setImage WHERE id=$id";
        $conn->query($sql);
        $msg = 'Product updated.';
    } else {
        // Insert
        $imgCol = $image ? ', image' : '';
        $imgVal = $image ? ", '$image'" : '';
        $sql = "INSERT INTO products (name, sku, category_id, supplier_id, price, quantity, description$imgCol) VALUES ('$name', '$sku', $category_id, $supplier_id, $price, $quantity, '$description'$imgVal)";
        $conn->query($sql);
        $msg = 'Product added.';
    }
}

// Handle edit
if (isset($_GET['edit'])) {
    $editing = true;
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE id = $id");
    if ($res && $res->num_rows) {
        $editProduct = $res->fetch_assoc();
    }
}

// Fetch categories and suppliers
$categories = [];
$res = $conn->query('SELECT id, name FROM categories ORDER BY name');
while ($row = $res->fetch_assoc()) $categories[] = $row;

$suppliers = [];
$res = $conn->query('SELECT id, name FROM suppliers ORDER BY name');
while ($row = $res->fetch_assoc()) $suppliers[] = $row;

// --- FILTERS ---
$filter_name = isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '';
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_supplier = isset($_GET['filter_supplier']) ? $_GET['filter_supplier'] : '';
$filter_stock = isset($_GET['filter_stock']) ? $_GET['filter_stock'] : '';
$where = [];
if ($filter_name !== '') {
    $where[] = "p.name LIKE '%" . $conn->real_escape_string($filter_name) . "%'";
}
if ($filter_category !== '') {
    $where[] = "p.category_id = " . (int)$filter_category;
}
if ($filter_supplier !== '') {
    $where[] = "p.supplier_id = " . (int)$filter_supplier;
}
if ($filter_stock === 'in') {
    $where[] = "p.quantity > 0";
} elseif ($filter_stock === 'out') {
    $where[] = "p.quantity = 0";
}
$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Fetch all products (with filters)
$products = [];
$res = $conn->query('SELECT p.*, c.name as category, s.name as supplier FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id ' . $where_sql . ' ORDER BY p.created_at DESC');
while ($row = $res->fetch_assoc()) $products[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Management</title>
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
          <li class="nav-item"><a class="nav-link active" href="product_management.php"><i class="fa fa-box"></i> Products</a></li>
          <li class="nav-item"><a class="nav-link" href="inventory_management.php"><i class="fa fa-warehouse"></i> Inventory</a></li>
          <li class="nav-item"><a class="nav-link" href="sales_management.php"><i class="fa fa-cash-register"></i> Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fa fa-chart-line"></i> Reports</a></li>
          <li class="nav-item mt-3"><a class="nav-link" href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </nav>
    <main class="col-md-10 ms-sm-auto main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Product Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openAddModal()"><i class="fa fa-plus"></i> Add Product</button>
      </div>
      <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Product Name
            <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>" class="form-control">
          </label>
        </div>
        <div class="col-md-2">
          <label class="form-label">Category
            <select name="filter_category" class="form-select">
              <option value="">All</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="col-md-2">
          <label class="form-label">Supplier
            <select name="filter_supplier" class="form-select">
              <option value="">All</option>
              <?php foreach ($suppliers as $sup): ?>
                <option value="<?= $sup['id'] ?>" <?= $filter_supplier == $sup['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="col-md-2">
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
          <a href="product_management.php" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
          <thead class="table-light">
            <tr>
              <th>Name</th><th>SKU</th><th>Category</th><th>Supplier</th><th>Price</th><th>Qty</th><th>Image</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($products as $prod): ?>
            <tr>
              <td><?= htmlspecialchars($prod['name']) ?></td>
              <td><?= htmlspecialchars($prod['sku']) ?></td>
              <td><?= htmlspecialchars($prod['category']) ?></td>
              <td><?= htmlspecialchars($prod['supplier']) ?></td>
              <td><?= $prod['price'] ?></td>
              <td><?= $prod['quantity'] ?></td>
              <td><?php if ($prod['image']): ?><img src="<?= htmlspecialchars($prod['image']) ?>" width="50"><?php endif; ?></td>
              <td>
                <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#productModal" onclick='openEditModal(<?= htmlspecialchars(json_encode($prod)) ?>)'><i class="fa fa-edit"></i></button>
                <a href="?delete=<?= $prod['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?');"><i class="fa fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- Modal for Add/Edit Product -->
      <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post" enctype="multipart/form-data" id="productForm">
              <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="id" id="prod_id">
                <div class="mb-3">
                  <label class="form-label">Product Name</label>
                  <input type="text" name="name" id="prod_name" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">SKU/Code</label>
                  <input type="text" name="sku" id="prod_sku" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Category</label>
                  <select name="category_id" id="prod_category_id" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Supplier (optional)</label>
                  <select name="supplier_id" id="prod_supplier_id" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach ($suppliers as $sup): ?>
                      <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Price</label>
                  <input type="number" step="0.01" name="price" id="prod_price" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Quantity in Stock</label>
                  <input type="number" name="quantity" id="prod_quantity" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Description</label>
                  <textarea name="description" id="prod_description" class="form-control"></textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label">Image</label>
                  <input type="file" name="image" id="prod_image" class="form-control" accept="image/*">
                  <div id="prod_image_preview" class="mt-2"></div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" name="save" class="btn btn-primary">Save Product</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <script>
      function openAddModal() {
        document.getElementById('productModalLabel').innerText = 'Add Product';
        document.getElementById('productForm').reset();
        document.getElementById('prod_id').value = '';
        document.getElementById('prod_image_preview').innerHTML = '';
      }
      function openEditModal(prod) {
        document.getElementById('productModalLabel').innerText = 'Edit Product';
        document.getElementById('prod_id').value = prod.id;
        document.getElementById('prod_name').value = prod.name;
        document.getElementById('prod_sku').value = prod.sku;
        document.getElementById('prod_category_id').value = prod.category_id || '';
        document.getElementById('prod_supplier_id').value = prod.supplier_id || '';
        document.getElementById('prod_price').value = prod.price;
        document.getElementById('prod_quantity').value = prod.quantity;
        document.getElementById('prod_description').value = prod.description || '';
        if (prod.image) {
          document.getElementById('prod_image_preview').innerHTML = '<img src="' + prod.image + '" width="80">';
        } else {
          document.getElementById('prod_image_preview').innerHTML = '';
        }
      }
      </script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </main>
  </div>
</div>
</body>
</html> 