<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('companies');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$id]);
$company = $stmt->fetch();
if (!$company) { die('کۆمپانیا نەدۆزرایەوە'); }

$invStmt = db()->prepare('SELECT * FROM purchase_invoices WHERE company_id = ? ORDER BY created_at DESC');
$invStmt->execute([$id]);
$invoices = $invStmt->fetchAll();

$paidStmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM partner_payments WHERE company_id = ?');
$paidStmt->execute([$id]);
$paid = (float)$paidStmt->fetchColumn();
$totalPurchased = array_sum(array_column($invoices, 'total_amount'));
$debt = $totalPurchased - $paid;

$page_title = 'کۆمپانیا: ' . $company['name'];
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="list.php">⟵ گەڕانەوە بۆ لیستی کۆمپانیاکان</a>

<div class="card mt10">
  <h2><?= htmlspecialchars($company['name']) ?></h2>
  <p class="text-muted">📞 <?= htmlspecialchars($company['phone']) ?: '—' ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($company['address']) ?: '—' ?></p>
  <div class="flex">
    <div class="badge badge-gray">کۆی کرین: <?= number_format($totalPurchased) ?></div>
    <div class="badge badge-green">پارەدراو: <?= number_format($paid) ?></div>
    <div class="badge <?= $debt > 0 ? 'badge-red' : 'badge-green' ?>">قەرزی ماوە: <?= number_format($debt) ?></div>
  </div>
</div>

<div class="card">
  <h3>وەسڵەکانی کرین</h3>
  <table>
    <thead><tr><th>P Number</th><th>بەروار</th><th>کۆی گشتی</th><th>پێداچوونەوە</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td><?= htmlspecialchars($inv['p_number']) ?></td>
          <td><?= date('Y-m-d H:i', strtotime($inv['created_at'])) ?></td>
          <td><?= number_format($inv['total_amount']) ?></td>
          <td><?= $inv['is_reviewed'] ? '<span class="badge badge-green">پێداچوونەوەی بۆ کراوە</span>' : '<span class="badge badge-orange">پێویستی بە پێداچوونەوەیە</span>' ?></td>
          <td><a class="btn btn-outline btn-sm" href="../purchase/invoice_form.php?id=<?= $inv['id'] ?>">کردنەوە</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="5" class="text-muted">هیچ وەسڵێک نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
