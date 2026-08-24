<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('it');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_permission('it', 'edit');
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $loc  = trim($_POST['location'] ?? '');
    if ($name !== '') {
        db()->prepare('INSERT INTO pos_terminals (name, location) VALUES (?,?)')->execute([$name, $loc]);
        $msg = 'POS زیادکرا';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    require_permission('it', 'edit');
    csrf_check();
    db()->prepare('UPDATE pos_terminals SET is_active = 1 - is_active WHERE id = ?')->execute([(int)$_POST['id']]);
    $msg = 'دۆخی POS گۆڕدرا';
}

$terminals = db()->query('SELECT * FROM pos_terminals ORDER BY id')->fetchAll();
$page_title = 'بەڕێوەبردنی POS';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="users.php">⟵ گەڕانەوە بۆ یوزەرەکان</a>
<?php if ($msg): ?><div class="alert alert-success mt10"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card mt10">
  <?php if (has_permission('it','edit')): ?>
    <form method="post" class="filters">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-row"><label>ناوی POS</label><input type="text" name="name" required></div>
      <div class="form-row"><label>شوێن</label><input type="text" name="location"></div>
      <button class="btn btn-primary">+ زیادکردن</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <table>
    <thead><tr><th>ناو</th><th>شوێن</th><th>دۆخ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($terminals as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><?= htmlspecialchars($t['location']) ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-green">چالاک</span>' : '<span class="badge badge-red">ناچالاک</span>' ?></td>
          <td>
            <?php if (has_permission('it','edit')): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-outline btn-sm"><?= $t['is_active'] ? 'ناچالاککردن' : 'چالاککردنەوە' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
