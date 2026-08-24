<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('materials');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stop_selling') {
    require_permission('materials', 'edit');
    csrf_check();
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("UPDATE materials SET is_stopped = 1, stopped_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        $msg = count($ids) . ' مادە گواسترایەوە بۆ بەشی ڕاگیراوی فرۆشتن';
    }
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM materials WHERE is_stopped = 0';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR barcode LIKE ? OR item_code LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$page_title = 'مادەکان';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="flex-between mb10">
  <form class="filters" method="get" style="margin:0">
    <div class="form-row"><input type="text" name="q" placeholder="گەڕان بە ناو / بارکۆد / کۆد..." value="<?= htmlspecialchars($search) ?>"></div>
    <button class="btn btn-outline">🔍 گەڕان</button>
  </form>
  <div class="flex">
    <a class="btn btn-outline" href="stopped.php">⏸ ڕاگیراوی فرۆشتن</a>
    <a class="btn btn-outline" href="stock.php">📦 بری هەبوون</a>
    <a class="btn btn-outline" href="cost.php">💲 کۆست</a>
    <?php if (has_permission('materials','edit')): ?>
      <a class="btn btn-primary" href="form.php">+ مادەی نوێ</a>
    <?php endif; ?>
  </div>
</div>

<form method="post" id="stopForm">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="stop_selling">
  <?php if (has_permission('materials','edit')): ?>
    <div class="mb10">
      <label><input type="checkbox" id="selectAll"> هەموو هەڵبژێرە</label>
      <button type="submit" class="btn btn-warn btn-sm" onclick="return confirm('ئایا دڵنیایت لە ڕاگرتنی فرۆشتنی مادە هەڵبژێردراوەکان؟');">⏸ ڕاگرتنی فرۆشتن</button>
    </div>
  <?php endif; ?>

  <div class="grid-cards">
    <?php foreach ($materials as $m): ?>
      <div class="item-card">
        <?php if (has_permission('materials','edit')): ?>
          <div style="text-align:right"><input type="checkbox" name="ids[]" value="<?= $m['id'] ?>"></div>
        <?php endif; ?>
        <a href="form.php?id=<?= $m['id'] ?>">
          <img src="<?= $m['image_path'] ? '../' . htmlspecialchars($m['image_path']) : 'https://placehold.co/150x90?text=No+Image' ?>" alt="">
          <div class="name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="text-muted" style="font-size:11.5px"><?= htmlspecialchars($m['barcode']) ?></div>
          <div class="price"><?= number_format($m['sale_price']) ?> د.ع</div>
          <div class="text-muted" style="font-size:11.5px">بەردەست: <?= rtrim(rtrim(number_format($m['quantity'],2),'0'),'.') ?></div>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$materials): ?><p class="text-muted">هیچ مادەیەک نەدۆزرایەوە</p><?php endif; ?>
  </div>
</form>

<script>
document.getElementById('selectAll')?.addEventListener('change', function(){
  document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
