<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('companies');

$msg = '';
$err = '';

// ---- دروستکردنی کۆمپانیای نوێ ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_permission('companies', 'edit');
    csrf_check();
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($name === '') {
        $err = 'ناوی کۆمپانیا پێویستە';
    } else {
        $stmt = db()->prepare('INSERT INTO companies (name, phone, address, created_by) VALUES (?,?,?,?)');
        $stmt->execute([$name, $phone, $address, $_SESSION['user_id']]);
        $msg = 'کۆمپانیا بە سەرکەوتوویی زیادکرا';
    }
}

// ---- ڕەشکردنەوە (بلۆک) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'block') {
    require_permission('companies', 'edit');
    csrf_check();
    $id = (int)$_POST['id'];

    $debtStmt = db()->prepare('
        SELECT COALESCE(SUM(pi.total_amount),0) - COALESCE((SELECT SUM(amount) FROM partner_payments WHERE company_id = ?),0) AS debt,
               (SELECT COUNT(*) FROM purchase_invoices WHERE company_id = ?) AS invoice_count
        FROM purchase_invoices pi WHERE pi.company_id = ?');
    $debtStmt->execute([$id, $id, $id]);
    $row = $debtStmt->fetch();

    if ($row['invoice_count'] > 0 || (float)$row['debt'] > 0) {
        $err = 'ببورە تۆ ناتوانیت ئەم کۆمپانیایە ڕەش بکەیتەوە، چونکە تۆ قەرزداری ئەو کۆمپانیایەیت یان وەسڵی کرینت هەبووە پێشتر.';
    } else {
        $stmt = db()->prepare('UPDATE companies SET is_blocked = 1 WHERE id = ?');
        $stmt->execute([$id]);
        $msg = 'کۆمپانیا ڕەشکرایەوە';
    }
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT c.*,
         (SELECT COALESCE(SUM(total_amount),0) FROM purchase_invoices WHERE company_id = c.id) -
         (SELECT COALESCE(SUM(amount),0) FROM partner_payments WHERE company_id = c.id) AS debt
        FROM companies c WHERE c.is_blocked = 0';
$params = [];
if ($search !== '') {
    $sql .= ' AND c.name LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY c.name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

$page_title = 'کۆمپانیاکان';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="flex-between mb10">
  <form class="filters" method="get" style="margin:0">
    <div class="form-row">
      <input type="text" name="q" placeholder="گەڕان بە ناوی کۆمپانیا..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <button class="btn btn-outline">🔍 گەڕان</button>
  </form>
  <?php if (has_permission('companies','edit')): ?>
    <button class="btn btn-primary" onclick="openModal('newCompanyModal')">+ کۆمپانیای نوێ</button>
  <?php endif; ?>
</div>

<div class="card">
  <table>
    <thead><tr><th>ناو</th><th>تەلەفۆن</th><th>ناونیشان</th><th>قەرز</th><th>کردار</th></tr></thead>
    <tbody>
      <?php foreach ($companies as $c): ?>
        <tr>
          <td><a href="view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></td>
          <td><?= htmlspecialchars($c['phone']) ?></td>
          <td><?= htmlspecialchars($c['address']) ?></td>
          <td>
            <?php if ($c['debt'] > 0): ?>
              <span class="badge badge-red"><?= fmt($c['debt']) ?></span>
            <?php else: ?>
              <span class="badge badge-green">0</span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="view.php?id=<?= $c['id'] ?>">بینین</a>
            <?php if (has_permission('companies','edit')): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('دڵنیایت لە ڕەشکردنەوە؟');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="block">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-danger btn-sm">ڕەشکردنەوە</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$companies): ?><tr><td colspan="5" class="text-muted">هیچ کۆمپانیایەک نەدۆزرایەوە</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="newCompanyModal">
  <div class="modal">
    <h3>کۆمپانیای نوێ</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-row"><label>ناوی کۆمپانیا</label><input type="text" name="name" required></div>
      <div class="form-row"><label>ژمارەی تەلەفۆن</label><input type="text" name="phone"></div>
      <div class="form-row"><label>ناونیشان</label><input type="text" name="address"></div>
      <button class="btn btn-primary">پاشەکەوتکردن</button>
      <button type="button" class="btn btn-outline" onclick="closeModal('newCompanyModal')">داخستن</button>
    </form>
  </div>
</div>

<?php
function fmt($n) { return number_format((float)$n, 0); }
include __DIR__ . '/../includes/footer.php';
?>
