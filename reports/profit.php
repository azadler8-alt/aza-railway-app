<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('reports');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

$soldStmt = db()->prepare('SELECT COALESCE(SUM(total_amount),0) FROM pos_sales WHERE created_at BETWEEN ? AND ?');
$soldStmt->execute([$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
$totalSold = (float)$soldStmt->fetchColumn();

$purchaseStmt = db()->prepare('SELECT COALESCE(SUM(total_amount),0) FROM purchase_invoices WHERE created_at BETWEEN ? AND ?');
$purchaseStmt->execute([$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
$totalPurchase = (float)$purchaseStmt->fetchColumn();

$expStmt = db()->prepare('SELECT COALESCE(SUM(total_amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?');
$expStmt->execute([$dateFrom, $dateTo]);
$totalExpenses = (float)$expStmt->fetchColumn();

$grossProfit = $totalSold - $totalPurchase;
$netRemaining = $grossProfit - $totalExpenses;

$page_title = 'راپۆرتی قازانج';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex mb10">
  <a class="btn btn-outline" href="profit.php">📊 قازانجی گشتی</a>
  <a class="btn btn-outline" href="item_profit.php">📦 قازانج بەپێی مادە</a>
</div>

<form class="filters card" method="get">
  <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
  <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="grid-cards">
  <div class="dash-card"><div class="icon">💰</div><div class="label">کۆی فرۆشراو</div><div class="mt10"><?= number_format($totalSold) ?></div></div>
  <div class="dash-card"><div class="icon">🧾</div><div class="label">کۆی کرین</div><div class="mt10"><?= number_format($totalPurchase) ?></div></div>
  <div class="dash-card"><div class="icon">📈</div><div class="label">قازانج (فرۆشتن - کرین)</div>
    <div class="mt10"><span class="badge <?= $grossProfit>=0?'badge-green':'badge-red' ?>"><?= number_format($grossProfit) ?></span></div>
  </div>
  <div class="dash-card"><div class="icon">🧮</div><div class="label">مەسروفات</div><div class="mt10"><?= number_format($totalExpenses) ?></div></div>
  <div class="dash-card"><div class="icon">✅</div><div class="label">بری ماوە (قازانج - مەسروفات)</div>
    <div class="mt10"><span class="badge <?= $netRemaining>=0?'badge-green':'badge-red' ?>"><?= number_format($netRemaining) ?></span></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
