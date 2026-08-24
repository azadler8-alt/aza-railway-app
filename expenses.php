<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('accounting');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_permission('accounting', 'edit');
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $qty  = (float)($_POST['qty'] ?? 1);
    $unit = (float)($_POST['unit_price'] ?? 0);
    $total = $qty * $unit;
    if ($name !== '') {
        db()->prepare('INSERT INTO expenses (expense_date, name, qty, unit_price, total_amount, created_by) VALUES (?,?,?,?,?,?)')
            ->execute([date('Y-m-d'), $name, $qty, $unit, $total, $_SESSION['user_id']]);
        $msg = 'پێداویستی زیادکرا';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_permission('accounting', 'edit');
    csrf_check();
    db()->prepare('DELETE FROM expenses WHERE id = ?')->execute([(int)$_POST['id']]);
    $msg = 'سڕایەوە';
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$stmt = db()->prepare('SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC');
$stmt->execute([$dateFrom, $dateTo]);
$expenses = $stmt->fetchAll();
$total = array_sum(array_column($expenses, 'total_amount'));

$page_title = 'مەسروفات';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="cash_receipt.php">⟵ گەڕانەوە بۆ حیسابات</a>
<?php if ($msg): ?><div class="alert alert-success mt10"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if (has_permission('accounting','edit')): ?>
<div class="card mt10">
  <h3>زیادکردنی پێداویستی</h3>
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-row"><label>ناوی پێداویستی</label><input type="text" name="name" required></div>
    <div class="form-row"><label>عدد</label><input type="number" step="0.01" name="qty" value="1"></div>
    <div class="form-row"><label>نرخی یەک دانە</label><input type="number" step="0.01" name="unit_price" value="0"></div>
    <div class="form-row" style="align-self:end"><button class="btn btn-primary">زیادکردن</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <form class="filters" method="get">
    <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
    <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
    <button class="btn btn-outline">🔍 گەڕان</button>
  </form>
  <h3 class="mt10">کۆی مەسروفات: <?= number_format($total) ?></h3>
  <table>
    <thead><tr><th>بەروار</th><th>ناو</th><th>عدد</th><th>نرخی یەک دانە</th><th>کۆی گشتی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($expenses as $e): ?>
        <tr>
          <td><?= htmlspecialchars($e['expense_date']) ?></td>
          <td><?= htmlspecialchars($e['name']) ?></td>
          <td><?= $e['qty'] ?></td>
          <td><?= number_format($e['unit_price']) ?></td>
          <td><?= number_format($e['total_amount']) ?></td>
          <td>
            <?php if (has_permission('accounting','edit')): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('دڵنیایت؟');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                <button class="btn btn-danger btn-sm">سڕینەوە</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$expenses): ?><tr><td colspan="6" class="text-muted">هیچ مەسروفاتێک نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
