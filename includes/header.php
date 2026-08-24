<?php
// $page_title و $show_back پێش include کردنی ئەم فایلە دیاری بکە
$page_title = $page_title ?? APP_NAME;
$show_back  = $show_back  ?? true;
$u = current_user();
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div><strong><?= htmlspecialchars($page_title) ?></strong></div>
    <div class="flex">
      <span class="text-muted"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
      <?php if ($show_back): ?>
        <a class="back" href="<?= BASE_URL ?>/dashboard.php">⟵ دشبۆرد</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="content">
