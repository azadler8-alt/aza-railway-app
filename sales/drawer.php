<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('sales');

$posList = db()->query('SELECT * FROM pos_terminals ORDER BY name')->fetchAll();
$cashierList = db()->query("SELECT * FROM users WHERE is_cashier = 1 ORDER BY full_name")->fetchAll();

$posId    = (int)($_GET['pos_id'] ?? 0);
$cashierId= (int)($_GET['cashier_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

$sql = 'SELECT s.*, u.full_name AS cashier_name, p.name AS pos_name FROM pos_sales s
        JOIN users u ON u.id = s.cashier_id JOIN pos_terminals p ON p.id = s.pos_id
        WHERE s.created_at BETWEEN ? AND ?';
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
if ($posId)     { $sql .= ' AND s.pos_id = ?'; $params[] = $posId; }
if ($cashierId) { $sql .= ' AND s.cashier_id = ?'; $params[] = $cashierId; }
$sql .= ' ORDER BY s.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totalSold = array_sum(array_column($sales, 'total_amount'));

// per-material breakdown
$itemSql = 'SELECT si.name, si.barcode, SUM(si.qty) AS qty, SUM(si.line_total) AS total
            FROM pos_sale_items si JOIN pos_sales s ON s.id = si.sale_id
            WHERE s.created_at BETWEEN ? AND ?';
$itemParams = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
if ($posId)     { $itemSql .= ' AND s.pos_id = ?'; $itemParams[] = $posId; }
if ($cashierId) { $itemSql .= ' AND s.cashier_id = ?'; $itemParams[] = $cashierId; }
$itemSql .= ' GROUP BY si.material_id ORDER BY total DESC';
$itemStmt = db()->prepare($itemSql);
$itemStmt->execute($itemParams);
$itemBreakdown = $itemStmt->fetchAll();

$page_title = 'مەبیعات';
include __DIR__ . '/../includes/header.php';
?>
<form class="filters card" method="get">
  <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
  <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
  <div class="form-row">
    <label>POS</label>
    <select name="pos_id"><option value="0">هەموو</option><?php foreach ($posList as $p): ?><option value="<?= $p['id'] ?>" <?= $posId==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select>
  </div>
  <div class="form-row">
    <label>کاشێر</label>
    <select name="cashier_id"><option value="0">هەموو</option><?php foreach ($cashierList as $c): ?><option value="<?= $c['id'] ?>" <?= $cashierId==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['full_name']) ?></option><?php endforeach; ?></select>
  </div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<div class="card">
  <h3>کۆی فرۆشتن: <?= number_format($totalSold) ?> د.ع (<?= count($sales) ?> پسوولە)</h3>
  <table>
    <thead><tr><th>ژمارە</th><th>POS</th><th>کاشێر</th><th>بەروار</th><th>کۆی گشتی</th></tr></thead>
    <tbody>
      <?php foreach ($sales as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['invoice_number']) ?></td>
          <td><?= htmlspecialchars($s['pos_name']) ?></td>
          <td><?= htmlspecialchars($s['cashier_name']) ?></td>
          <td><?= date('Y-m-d H:i', strtotime($s['created_at'])) ?></td>
          <td><?= number_format($s['total_amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sales): ?><tr><td colspan="5" class="text-muted">هیچ فرۆشتنێک نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3>بەپێی مادە</h3>
  <table>
    <thead><tr><th>بارکۆد</th><th>ناو</th><th>عددی فرۆشراو</th><th>کۆی فرۆشراو</th></tr></thead>
    <tbody>
      <?php foreach ($itemBreakdown as $i): ?>
        <tr><td><?= htmlspecialchars($i['barcode']) ?></td><td><?= htmlspecialchars($i['name']) ?></td><td><?= $i['qty'] ?></td><td><?= number_format($i['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$itemBreakdown): ?><tr><td colspan="4" class="text-muted">هیچ نییە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
