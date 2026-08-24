<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('warehouse');

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM materials WHERE quantity <= 0';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR barcode LIKE ?)';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY stopped_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$reasonLabel = ['sale' => 'فرۆشتن', 'waste' => 'تەلەف', 'adjustment' => 'ڕاستکردنەوە'];

$page_title = 'مادە خەڵاسبووەکان';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="stock.php">⟵ گەڕانەوە</a>

<form class="filters mt10" method="get">
  <div class="form-row"><label>گەڕان بۆ راپۆرتی مادەیەک</label><input type="text" name="q" placeholder="بارکۆد یان ناوی مادە..." value="<?= htmlspecialchars($search) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>بارکۆد</th><th>ناو</th><th>تاریخی خەڵاسبوون</th><th>هۆکار</th></tr></thead>
    <tbody>
      <?php foreach ($materials as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['barcode']) ?></td>
          <td><?= htmlspecialchars($m['name']) ?></td>
          <td><?= $m['stopped_at'] ? date('Y-m-d H:i', strtotime($m['stopped_at'])) : '—' ?></td>
          <td><?= $reasonLabel[$m['stopped_reason']] ?? 'فرۆشتن' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$materials): ?><tr><td colspan="4" class="text-muted">هیچ مادەیەکی خەڵاسبوو نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
