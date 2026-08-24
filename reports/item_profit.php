<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('reports');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$materialId = (int)($_GET['material_id'] ?? 0);

$materials = db()->query('SELECT id, name, barcode FROM materials ORDER BY name')->fetchAll();

$result = null;
if ($materialId) {
    $matStmt = db()->prepare('SELECT * FROM materials WHERE id = ?');
    $matStmt->execute([$materialId]);
    $mat = $matStmt->fetch();

    $stmt = db()->prepare('SELECT COALESCE(SUM(si.qty),0) AS qty, COALESCE(SUM(si.line_total),0) AS sold,
                                   COALESCE(SUM(si.qty * si.cost_at_sale),0) AS cost_total
                            FROM pos_sale_items si JOIN pos_sales s ON s.id = si.sale_id
                            WHERE si.material_id = ? AND s.created_at BETWEEN ? AND ?');
    $stmt->execute([$materialId, $dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
    $row = $stmt->fetch();

    $sold = (float)$row['sold'];
    $costTotal = (float)$row['cost_total'];
    $purchaseTotal = (float)$mat['purchase_price'] * (float)$row['qty'];

    $result = [
        'name' => $mat['name'], 'barcode' => $mat['barcode'], 'qty' => $row['qty'],
        'sold' => $sold,
        'profit_by_purchase' => $sold - $purchaseTotal,
        'profit_by_cost' => $sold - $costTotal,
    ];
}

$page_title = 'قازانج بەپێی مادە';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex mb10">
  <a class="btn btn-outline" href="profit.php">📊 قازانجی گشتی</a>
  <a class="btn btn-outline" href="item_profit.php">📦 قازانج بەپێی مادە</a>
</div>

<form class="filters card" method="get">
  <div class="form-row">
    <label>مادە</label>
    <select name="material_id" required>
      <option value="0">هەڵبژاردن...</option>
      <?php foreach ($materials as $m): ?><option value="<?= $m['id'] ?>" <?= $materialId==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?> — <?= htmlspecialchars($m['barcode']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
  <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
  <button class="btn btn-outline">🔍 گەڕان</button>
</form>

<?php if ($result): ?>
<div class="card">
  <h3><?= htmlspecialchars($result['name']) ?> — <?= htmlspecialchars($result['barcode']) ?></h3>
  <table>
    <tr><th>عددی فرۆشراو</th><td><?= $result['qty'] ?></td></tr>
    <tr><th>کۆی فرۆشراو</th><td><?= number_format($result['sold']) ?></td></tr>
    <tr><th>قازانج بەپێی نرخی کرین</th><td><span class="badge <?= $result['profit_by_purchase']>=0?'badge-green':'badge-red' ?>"><?= number_format($result['profit_by_purchase']) ?></span></td></tr>
    <tr><th>قازانج بەپێی کۆست</th><td><span class="badge <?= $result['profit_by_cost']>=0?'badge-green':'badge-red' ?>"><?= number_format($result['profit_by_cost']) ?></span></td></tr>
  </table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
