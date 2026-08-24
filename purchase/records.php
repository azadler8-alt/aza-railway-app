<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('purchase');

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
$pNumber  = trim($_GET['p_number'] ?? '');
$company  = trim($_GET['company'] ?? '');
$creator  = trim($_GET['creator'] ?? '');

$sql = 'SELECT pi.*, c.name AS company_name, u.full_name AS creator_name
        FROM purchase_invoices pi
        JOIN companies c ON c.id = pi.company_id
        JOIN users u ON u.id = pi.created_by
        WHERE 1=1';
$params = [];

if ($dateFrom) { $sql .= ' AND pi.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo)   { $sql .= ' AND pi.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
if ($pNumber)  { $sql .= ' AND pi.p_number LIKE ?'; $params[] = "%$pNumber%"; }
if ($company)  { $sql .= ' AND c.name LIKE ?'; $params[] = "%$company%"; }
if ($creator)  { $sql .= ' AND u.full_name LIKE ?'; $params[] = "%$creator%"; }

$sql .= ' ORDER BY pi.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$page_title = 'ریکۆردی وەسڵی کرین';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb10">
  <h2 style="margin:0">وەسڵی کرین</h2>
  <?php if (has_permission('purchase','edit')): ?>
    <a class="btn btn-primary" href="invoice_form.php">+ وەسڵی کرینی نوێ</a>
  <?php endif; ?>
</div>

<form class="filters card" method="get">
  <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
  <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
  <div class="form-row"><label>P Number</label><input type="text" name="p_number" value="<?= htmlspecialchars($pNumber) ?>"></div>
  <div class="form-row"><label>ناوی کۆمپانیا</label><input type="text" name="company" value="<?= htmlspecialchars($company) ?>"></div>
  <div class="form-row"><label>دروستکراوە لەلایەن</label><input type="text" name="creator" value="<?= htmlspecialchars($creator) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>P Number</th><th>کۆمپانیا</th><th>بەروار</th><th>کۆی گشتی</th><th>دروستکراوە لەلایەن</th><th>بار</th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr onclick="location.href='invoice_form.php?id=<?= $inv['id'] ?>'" style="cursor:pointer">
          <td><?= htmlspecialchars($inv['p_number']) ?></td>
          <td><?= htmlspecialchars($inv['company_name']) ?></td>
          <td><?= date('Y-m-d H:i', strtotime($inv['created_at'])) ?></td>
          <td><?= number_format($inv['total_amount']) ?></td>
          <td><?= htmlspecialchars($inv['creator_name']) ?></td>
          <td><?= $inv['is_reviewed'] ? '<span class="badge badge-green">پێداچوونەوە کراوە</span>' : '<span class="badge badge-orange">پێویستە پێداچوونەوە</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="6" class="text-muted">هیچ وەسڵێک نەدۆزرایەوە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
