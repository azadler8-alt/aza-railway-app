<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['is_cashier'] = $user['is_cashier'];
        $_SESSION['pos_id']     = $user['pos_id'];

        if ($user['is_cashier']) {
            header('Location: ' . BASE_URL . '/pos/pos_screen.php');
        } else {
            header('Location: ' . BASE_URL . '/dashboard.php');
        }
        exit;
    }
    $error = 'ناوی بەکارهێنەر یان وشەی نهێنی هەڵەیە';
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>چوونەژوورەوە — aza</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <h1>🔐 چوونەژوورەوە بۆ aza</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="form-row">
        <label>ناوی بەکارهێنەر</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-row">
        <label>وشەی نهێنی</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn btn-primary" style="width:100%" type="submit">چوونەژوورەوە</button>
    </form>
  </div>
</div>
</body>
</html>
