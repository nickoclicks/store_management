<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';

$error = '';

if (isset($_POST['email'], $_POST['password'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE email = '$email' AND role = 'admin' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Compare plain text password (not secure, for development only)
        if ($password === $user['password']) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['name'];
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Invalid password.';
        }
    } else {
        $error = 'Admin user not found or invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h2>Admin Login</h2>
    <?php if ($error): ?>
        <div style="color:red;"> <?= htmlspecialchars($error) ?> </div>
    <?php endif; ?>
    <form method="post" action="">
        <label>Email:<br><input type="email" name="email" required></label><br><br>
        <label>Password:<br><input type="password" name="password" required></label><br><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
