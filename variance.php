<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('accounting');

$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$cashierId = (int)($_GET['cashier_id'] ?? 0);

$cashiers = db()->query('SELECT * FROM users WHERE is_cashier=1 ORDER BY full_name')->fetchAll();

$sql = "SELECT u.id, u.full_name,
    (SELECT COALESCE(SUM(s.total_amount),0) FROM pos_sales s WHERE s.cashier_id=u.id AND s.created_at BETWEEN ? AND ?) AS sold,
    (SELECT COALESCE(SUM(cr.amount_iqd + cr.amount_usd * cr.exchange_rate),0) FROM cash_receipts cr WHERE cr.cashier_id=u.id AND cr.receipt_date BETWEEN ? AND ?) AS received
    FROM users u WHERE u.is_cashier = 1";
$params = [$dateFrom.' 00:00:00', $dateTo.' 23:59:59', $dateFrom, $dateTo];
if ($cashierId) { $sql .= ' AND u.id = ?'; $params[] = $cashierId; }
$sql .= ' ORDER BY u.full_name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = 'نەقس و زیادی کاشێر';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="cash_receipt.php">⟵ گەڕانەوە بۆ حیسابات</a>

<form class="filters card mt10" method="get">
  <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
  <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
  <div class="form-row">
    <label>کاشێر</label>
    <select name="cashier_id"><option value="0">هەموو</option><?php foreach ($cashiers as $c): ?><option value="<?= $c['id'] ?>" <?= $cashierId==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['full_name']) ?></option><?php endforeach; ?></select>
  </div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>کاشێر</th><th>بری فرۆشراو</th><th>بری وەرگیراو</th><th>نەقس / زیاد</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r):
        $diff = (float)$r['received'] - (float)$r['sold']; ?>
        <tr>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= number_format($r['sold']) ?></td>
          <td><?= number_format($r['received']) ?></td>
          <td>
            <?php if ($diff > 0): ?>
              <span class="badge badge-green">زیادە: <?= number_format($diff) ?></span>
            <?php elseif ($diff < 0): ?>
              <span class="badge badge-red">نەقسە: <?= number_format(abs($diff)) ?></span>
            <?php else: ?>
              <span class="badge badge-gray">یەکسانە</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="text-muted">هیچ نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
