<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('accounting');

$cashiers = db()->query('SELECT u.*, p.name AS pos_name FROM users u LEFT JOIN pos_terminals p ON p.id=u.pos_id WHERE u.is_cashier=1 ORDER BY u.full_name')->fetchAll();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('accounting', 'edit');
    csrf_check();
    $cashierId = (int)$_POST['cashier_id'];
    $cStmt = db()->prepare('SELECT pos_id FROM users WHERE id = ?');
    $cStmt->execute([$cashierId]);
    $posId = (int)$cStmt->fetchColumn();

    $stmt = db()->prepare('INSERT INTO cash_receipts (cashier_id, pos_id, receipt_date, amount_iqd, amount_usd, exchange_rate, received_by) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $cashierId, $posId, $_POST['receipt_date'] ?? date('Y-m-d'),
        (float)($_POST['amount_iqd'] ?? 0), (float)($_POST['amount_usd'] ?? 0),
        (float)($_POST['exchange_rate'] ?? 1460), $_SESSION['user_id']
    ]);
    $msg = 'وەرگرتنی پارە تۆمارکرا';
}

$recent = db()->query('SELECT cr.*, u.full_name AS cashier_name FROM cash_receipts cr JOIN users u ON u.id=cr.cashier_id ORDER BY cr.created_at DESC LIMIT 30')->fetchAll();

$page_title = 'وەرگرتنی پارە لە کاشێر';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex mb10">
  <a class="btn btn-outline" href="cash_receipt.php">💵 وەرگرتنی پارە</a>
  <a class="btn btn-outline" href="variance.php">⚖ نەقس و زیادی کاشێر</a>
  <a class="btn btn-outline" href="partner_debt.php">🏢 قەرزی شەریکە</a>
  <a class="btn btn-outline" href="expenses.php">🧾 مەسروفات</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if (has_permission('accounting','edit')): ?>
<div class="card">
  <h3>وەرگرتنی پارە</h3>
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="form-row">
      <label>کاشێر</label>
      <select name="cashier_id" required>
        <?php foreach ($cashiers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> — <?= htmlspecialchars($c['pos_name'] ?? '') ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>بەروار</label><input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-row"><label>بری پارە (دینار عێراقی)</label><input type="number" step="0.01" name="amount_iqd" value="0"></div>
    <div class="form-row"><label>بری پارە (دۆلار ئەمەریکی)</label><input type="number" step="0.01" name="amount_usd" value="0"></div>
    <div class="form-row"><label>نرخی دۆلار (بۆ گۆڕین)</label><input type="number" step="0.01" name="exchange_rate" value="1460"></div>
    <div class="form-row" style="align-self:end"><button class="btn btn-primary">پاشەکەوتکردن</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>تۆمارە دواییەکان</h3>
  <table>
    <thead><tr><th>کاشێر</th><th>بەروار</th><th>دینار</th><th>دۆلار</th><th>وەرگیراوی لەلایەن</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['cashier_name']) ?></td>
          <td><?= htmlspecialchars($r['receipt_date']) ?></td>
          <td><?= number_format($r['amount_iqd']) ?></td>
          <td><?= number_format($r['amount_usd'],2) ?></td>
          <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
