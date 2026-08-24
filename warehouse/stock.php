<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('warehouse');

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM materials WHERE quantity > 0';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR barcode LIKE ?)';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$page_title = 'مەغزەن';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb10">
  <form class="filters" method="get" style="margin:0">
    <div class="form-row"><input type="text" name="q" placeholder="گەڕان بە ناو یان بارکۆد..." value="<?= htmlspecialchars($search) ?>"></div>
    <button class="btn btn-outline">🔍 گەڕان</button>
  </form>
  <a class="btn btn-warn" href="out_of_stock.php">⚠ مادە خەڵاسبووەکان</a>
</div>

<div class="grid-cards">
  <?php foreach ($materials as $m): ?>
    <div class="item-card">
      <img src="<?= $m['image_path'] ? '../' . htmlspecialchars($m['image_path']) : 'https://placehold.co/150x90?text=No+Image' ?>" alt="">
      <div class="name"><?= htmlspecialchars($m['name']) ?></div>
      <div class="text-muted" style="font-size:11.5px"><?= htmlspecialchars($m['barcode']) ?></div>
      <div class="price"><?= rtrim(rtrim(number_format($m['quantity'],2),'0'),'.') ?> دانە</div>
    </div>
  <?php endforeach; ?>
  <?php if (!$materials): ?><p class="text-muted">هیچ مادەیەک نییە</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
