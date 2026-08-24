<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('materials');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('materials', 'edit');
    csrf_check();
    $material_id = (int)$_POST['material_id'];
    $newCost = (float)$_POST['new_cost'];
    db()->prepare('UPDATE materials SET cost = ? WHERE id = ?')->execute([$newCost, $material_id]);
    $msg = 'کۆست نوێکرایەوە';
}

$search = trim($_GET['q'] ?? '');
$materials = [];
if ($search !== '') {
    $stmt = db()->prepare('SELECT * FROM materials WHERE name LIKE ? OR barcode LIKE ? ORDER BY name LIMIT 30');
    $stmt->execute(["%$search%", "%$search%"]);
    $materials = $stmt->fetchAll();
}

$page_title = 'کۆستی مادەکان';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="list.php">⟵ گەڕانەوە</a>
<?php if ($msg): ?><div class="alert alert-success mt10"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="alert alert-info mt10">
  کۆست بە شێوەی ئۆتۆماتیکی وەک <strong>ئەڤەرێج کۆست</strong> ژمێردەکرێت لە کاتی پاشەکەوتکردنی وەسڵی کرین.
  دەستکاریکردنی مانوای لێرە تەنها بۆ ڕاستکردنەوەی هەڵەیە.
</div>

<form class="filters" method="get">
  <div class="form-row"><input type="text" name="q" placeholder="گەڕان بە ناو یان بارکۆد..." value="<?= htmlspecialchars($search) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>بارکۆد</th><th>ناو</th><th>کۆستی ئێستا</th><th>کۆستی نوێ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($materials as $m): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
            <td><?= htmlspecialchars($m['barcode']) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= number_format($m['cost']) ?></td>
            <td style="max-width:120px"><input type="number" step="0.01" name="new_cost" value="<?= $m['cost'] ?>"></td>
            <td><?php if (has_permission('materials','edit')): ?><button class="btn btn-primary btn-sm">نوێکردنەوە</button><?php endif; ?></td>
          </form>
        </tr>
      <?php endforeach; ?>
      <?php if ($search && !$materials): ?><tr><td colspan="5" class="text-muted">هیچ نەدۆزرایەوە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
