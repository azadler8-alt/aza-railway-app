<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('materials');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reactivate') {
    require_permission('materials', 'edit');
    csrf_check();
    $id = (int)$_POST['id'];
    db()->prepare('UPDATE materials SET is_stopped = 0, stopped_reason = NULL, stopped_at = NULL WHERE id = ?')->execute([$id]);
    $msg = 'مادەکە گەڕایەوە بۆ فرۆشتنی چالاک';
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM materials WHERE is_stopped = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR barcode LIKE ?)';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY stopped_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$reasonLabel = ['sale' => 'فرۆشتن', 'waste' => 'تەلەف'];

$page_title = 'ڕاگیراوی فرۆشتن';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="list.php">⟵ گەڕانەوە</a>
<?php if ($msg): ?><div class="alert alert-success mt10"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form class="filters mt10" method="get">
  <div class="form-row"><input type="text" name="q" placeholder="گەڕان بە ناو یان بارکۆد..." value="<?= htmlspecialchars($search) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>بارکۆد</th><th>ناو</th><th>تاریخی خەڵاسبوون</th><th>هۆکار</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($materials as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['barcode']) ?></td>
          <td><?= htmlspecialchars($m['name']) ?></td>
          <td><?= $m['stopped_at'] ? date('Y-m-d H:i', strtotime($m['stopped_at'])) : '—' ?></td>
          <td><?= $reasonLabel[$m['stopped_reason']] ?? 'بەدەستی' ?></td>
          <td>
            <?php if (has_permission('materials','edit')): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-success btn-sm">چالاککردنەوە</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$materials): ?><tr><td colspan="5" class="text-muted">هیچ مادەیەکی ڕاگیراو نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
