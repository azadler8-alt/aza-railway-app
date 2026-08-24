<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('it');

$modules = require __DIR__ . '/../includes/modules.php';
$posTerminals = db()->query('SELECT * FROM pos_terminals WHERE is_active = 1 ORDER BY name')->fetchAll();

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_permission('it', 'edit');
    csrf_check();
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $fullName  = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'staff';
    $isCashier = isset($_POST['is_cashier']) ? 1 : 0;
    $posId     = $isCashier ? (int)($_POST['pos_id'] ?? 0) : null;

    if ($username === '' || $password === '' || $fullName === '') {
        $err = 'هەموو خانەکان پێویستن';
    } else {
        $exists = db()->prepare('SELECT id FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($exists->fetch()) {
            $err = 'ئەم ناوی بەکارهێنەرە پێشتر بەکارهاتووە';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role, is_cashier, pos_id, created_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$username, $hash, $fullName, $role, $isCashier, $posId ?: null, $_SESSION['user_id']]);
            $newUserId = (int)$pdo->lastInsertId();

            if (!$isCashier) {
                $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, can_view, can_edit) VALUES (?,?,?,?)');
                foreach ($modules as $key => $label) {
                    $canView = isset($_POST['perm_view'][$key]) ? 1 : 0;
                    $canEdit = isset($_POST['perm_edit'][$key]) ? 1 : 0;
                    if ($canView || $canEdit) {
                        $permStmt->execute([$newUserId, $key, $canView, $canEdit]);
                    }
                }
            }
            $pdo->commit();
            $msg = 'یوزەر بە سەرکەوتوویی دروستکرا';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    require_permission('it', 'edit');
    csrf_check();
    $uid = (int)$_POST['id'];
    db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
    $msg = 'دۆخی یوزەر گۆڕدرا';
}

$users = db()->query('SELECT u.*, p.name AS pos_name FROM users u LEFT JOIN pos_terminals p ON p.id = u.pos_id ORDER BY u.id DESC')->fetchAll();

$page_title = 'بەڕێوەبردنی یوزەرەکان';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb10">
  <div class="flex">
    <a class="btn btn-outline" href="pos_terminals.php">🖥 POS ەکان</a>
  </div>
  <?php if (has_permission('it','edit')): ?>
    <button class="btn btn-primary" onclick="openModal('newUserModal')">+ یوزەری نوێ</button>
  <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <table>
    <thead><tr><th>ناوی بەکارهێنەر</th><th>ناوی تەواو</th><th>ڕۆڵ</th><th>کاشێر</th><th>POS</th><th>دۆخ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['full_name']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td><?= $u['is_cashier'] ? '✔' : '—' ?></td>
          <td><?= htmlspecialchars($u['pos_name'] ?? '—') ?></td>
          <td><?= $u['is_active'] ? '<span class="badge badge-green">چالاک</span>' : '<span class="badge badge-red">ناچالاک</span>' ?></td>
          <td>
            <?php if (has_permission('it','edit') && $u['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-outline btn-sm"><?= $u['is_active'] ? 'ناچالاککردن' : 'چالاککردنەوە' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="newUserModal">
  <div class="modal" style="max-width:640px">
    <h3>یوزەری نوێ</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-row"><label>ناوی بەکارهێنەر</label><input type="text" name="username" required></div>
        <div class="form-row"><label>وشەی نهێنی</label><input type="password" name="password" required></div>
        <div class="form-row"><label>ناوی تەواو</label><input type="text" name="full_name" required></div>
        <div class="form-row">
          <label>ڕۆڵ</label>
          <select name="role">
            <option value="staff">ستاف</option>
            <option value="manager">بەڕێوەبەر</option>
            <option value="it">IT</option>
            <option value="admin">ئەدمین</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <label><input type="checkbox" name="is_cashier" id="isCashierChk" onchange="document.getElementById('posSelectRow').style.display=this.checked?'block':'none'"> ئەم یوزەرە کاشێرە</label>
      </div>
      <div class="form-row" id="posSelectRow" style="display:none">
        <label>POS چالاک بۆ ئەم کاشێرە</label>
        <select name="pos_id">
          <?php foreach ($posTerminals as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>

      <h4>مۆڵەتەکان (بۆ یوزەری کاشێر پێویست نییە)</h4>
      <table>
        <thead><tr><th>بەش</th><th>بینین</th><th>دەستکاری</th></tr></thead>
        <tbody>
          <?php foreach ($modules as $key => $label): ?>
            <tr>
              <td><?= $label ?></td>
              <td><input type="checkbox" name="perm_view[<?= $key ?>]"></td>
              <td><input type="checkbox" name="perm_edit[<?= $key ?>]"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="mt10">
        <button class="btn btn-primary">پاشەکەوتکردن</button>
        <button type="button" class="btn btn-outline" onclick="closeModal('newUserModal')">داخستن</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
