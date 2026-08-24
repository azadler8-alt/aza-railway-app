<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('accounting');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    require_permission('accounting', 'edit');
    csrf_check();
    $companyId = (int)$_POST['company_id'];
    $amount = (float)$_POST['amount'];
    if ($amount > 0) {
        db()->prepare('INSERT INTO partner_payments (company_id, payment_date, amount, note, created_by) VALUES (?,?,?,?,?)')
            ->execute([$companyId, date('Y-m-d'), $amount, trim($_POST['note'] ?? ''), $_SESSION['user_id']]);
        $msg = 'پارەدان تۆمارکرا';
    }
}

$companies = db()->query('SELECT c.*,
    (SELECT COALESCE(SUM(total_amount),0) FROM purchase_invoices WHERE company_id=c.id) -
    (SELECT COALESCE(SUM(amount),0) FROM partner_payments WHERE company_id=c.id) AS debt
    FROM companies c WHERE c.is_blocked=0 ORDER BY c.name')->fetchAll();

// راپۆرتی پارەدان
$repCompany = (int)($_GET['company_id'] ?? 0);
$repFrom = $_GET['date_from'] ?? '';
$repTo   = $_GET['date_to'] ?? '';
$paymentRows = [];
if ($repCompany) {
    $sql = 'SELECT pp.*, c.name AS company_name FROM partner_payments pp JOIN companies c ON c.id=pp.company_id WHERE pp.company_id = ?';
    $params = [$repCompany];
    if ($repFrom) { $sql .= ' AND pp.payment_date >= ?'; $params[] = $repFrom; }
    if ($repTo)   { $sql .= ' AND pp.payment_date <= ?'; $params[] = $repTo; }
    $sql .= ' ORDER BY pp.payment_date DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $paymentRows = $stmt->fetchAll();
}

$page_title = 'قەرزی شەریکە';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="cash_receipt.php">⟵ گەڕانەوە بۆ حیسابات</a>
<?php if ($msg): ?><div class="alert alert-success mt10"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card mt10">
  <h3>لیستی شەریکەکان</h3>
  <table>
    <thead><tr><th>ناوی شەریکە</th><th>کۆی قەرز</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($companies as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td><span class="badge <?= $c['debt']>0?'badge-red':'badge-green' ?>"><?= number_format($c['debt']) ?></span></td>
          <td><?php if (has_permission('accounting','edit') && $c['debt']>0): ?><button class="btn btn-primary btn-sm" onclick="payCompany(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'],ENT_QUOTES) ?>', <?= $c['debt'] ?>)">پارەدان</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="payModal">
  <div class="modal">
    <h3>پارەدان بە <span id="payCompanyName"></span></h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="company_id" id="payCompanyId">
      <div class="form-row"><label>بەرواری ئەمڕۆ</label><input type="text" disabled value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row"><label>کۆی قەرز</label><input type="text" id="payDebtView" disabled></div>
      <div class="form-row"><label>بری پارەدان</label><input type="number" step="0.01" name="amount" id="payAmount" required></div>
      <div class="form-row"><label>تێبینی</label><input type="text" name="note"></div>
      <button class="btn btn-primary">پاشەکەوتکردن</button>
      <button type="button" class="btn btn-outline" onclick="closeModal('payModal')">داخستن</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>راپۆرتی پارەدان</h3>
  <form class="filters" method="get">
    <div class="form-row">
      <label>شەریکە</label>
      <select name="company_id">
        <option value="0">هەڵبژاردن...</option>
        <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>" <?= $repCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>لە بەرواری</label><input type="date" name="date_from" value="<?= htmlspecialchars($repFrom) ?>"></div>
    <div class="form-row"><label>بۆ بەرواری</label><input type="date" name="date_to" value="<?= htmlspecialchars($repTo) ?>"></div>
    <button class="btn btn-outline">🔍 گەڕان</button>
  </form>

  <?php if ($repCompany): ?>
    <table class="mt10">
      <thead><tr><th>بەروار</th><th>بری پارەدان</th><th>تێبینی</th></tr></thead>
      <tbody>
        <?php foreach ($paymentRows as $p): ?>
          <tr><td><?= htmlspecialchars($p['payment_date']) ?></td><td><?= number_format($p['amount']) ?></td><td><?= htmlspecialchars($p['note']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$paymentRows): ?><tr><td colspan="3" class="text-muted">هیچ پارەدانێک نییە</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function payCompany(id, name, debt) {
  document.getElementById('payCompanyId').value = id;
  document.getElementById('payCompanyName').textContent = name;
  document.getElementById('payDebtView').value = fmtMoney(debt);
  document.getElementById('payAmount').max = debt;
  openModal('payModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
