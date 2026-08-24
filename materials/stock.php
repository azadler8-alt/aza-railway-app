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
    $newQty      = (float)$_POST['new_qty'];
    $reasonRaw   = $_POST['reason'] ?? 'adjustment'; // adjustment | waste
    $note        = trim($_POST['note'] ?? '');

    $stmt = db()->prepare('SELECT quantity FROM materials WHERE id = ?');
    $stmt->execute([$material_id]);
    $current = (float)$stmt->fetchColumn();
    $diff = $newQty - $current;
    $movementType = ($diff < 0 && $reasonRaw === 'waste') ? 'waste' : 'adjustment';

    $pdo = db();
    $pdo->beginTransaction();

    if ($newQty <= 0) {
        $pdo->prepare('UPDATE materials SET quantity = ?, is_stopped = 1, stopped_reason = ?, stopped_at = NOW() WHERE id = ?')
            ->execute([$newQty, $movementType === 'waste' ? 'waste' : 'sale', $material_id]);
    } else {
        $pdo->prepare('UPDATE materials SET quantity = ?, is_stopped = 0, stopped_reason = NULL, stopped_at = NULL WHERE id = ?')
            ->execute([$newQty, $material_id]);
    }
    $pdo->prepare('INSERT INTO stock_movements (material_id, change_qty, movement_type, note, created_by) VALUES (?,?,?,?,?)')
        ->execute([$material_id, $diff, $movementType, $note, $_SESSION['user_id']]);
    $pdo->commit();
    $msg = 'بری هەبوون نوێکرایەوە';
}

$search = trim($_GET['q'] ?? '');
$materials = [];
if ($search !== '') {
    $stmt = db()->prepare('SELECT * FROM materials WHERE name LIKE ? OR barcode LIKE ? ORDER BY name LIMIT 30');
    $stmt->execute(["%$search%", "%$search%"]);
    $materials = $stmt->fetchAll();
}

$page_title = 'بری هەبوونی مادە';
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
    <thead><tr><th>بارکۆد</th><th>ناو</th><th>بری هەبوونی ئێستا</th><th>بری هەبوونی نوێ</th><th>هۆکار (ئەگەر کەمکردنەوە)</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($materials as $m): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
            <td><?= htmlspecialchars($m['barcode']) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= $m['quantity'] ?></td>
            <td style="max-width:110px"><input type="number" step="0.01" name="new_qty" value="<?= $m['quantity'] ?>"></td>
            <td style="max-width:130px">
              <select name="reason">
                <option value="adjustment">ڕاستکردنەوە</option>
                <option value="waste">تەلەف</option>
              </select>
            </td>
            <td><?php if (has_permission('materials','edit')): ?><button class="btn btn-primary btn-sm">نوێکردنەوە</button><?php endif; ?></td>
          </form>
        </tr>
      <?php endforeach; ?>
      <?php if ($search && !$materials): ?><tr><td colspan="6" class="text-muted">هیچ نەدۆزرایەوە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
