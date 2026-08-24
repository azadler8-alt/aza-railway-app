<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('materials');

$id = (int)($_GET['id'] ?? 0);
$material = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM materials WHERE id = ?');
    $stmt->execute([$id]);
    $material = $stmt->fetch();
    if (!$material) die('مادە نەدۆزرایەوە');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('materials', 'edit');
    csrf_check();
    $name       = trim($_POST['name'] ?? '');
    $barcode    = trim($_POST['barcode'] ?? '');
    $sale_price = (float)($_POST['sale_price'] ?? 0);
    $show_on_pos = isset($_POST['show_on_pos']) ? 1 : 0;

    if ($name === '' || $barcode === '') {
        $err = 'ناو و بارکۆد پێویستن';
    } else {
        // wêne
        $imagePath = $material['image_path'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fname = 'assets/uploads/' . uniqid('mat_') . '.' . $ext;
            @mkdir(__DIR__ . '/../assets/uploads', 0777, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../' . $fname)) {
                $imagePath = $fname;
            }
        }

        if ($material) {
            $stmt = db()->prepare('UPDATE materials SET name=?, barcode=?, sale_price=?, show_on_pos=?, image_path=? WHERE id=?');
            $stmt->execute([$name, $barcode, $sale_price, $show_on_pos, $imagePath, $id]);
            header('Location: list.php');
            exit;
        } else {
            // dروستکردنی کۆدی ئایتەم بەشێوەی خۆکار
            $lastCode = db()->query('SELECT item_code FROM materials ORDER BY id DESC LIMIT 1')->fetchColumn();
            $next = $lastCode ? ((int)substr($lastCode, -6)) + 1 : 1;
            $itemCode = 'ITM-' . str_pad($next, 6, '0', STR_PAD_LEFT);

            $stmt = db()->prepare('INSERT INTO materials (item_code, name, barcode, sale_price, show_on_pos, image_path, created_by) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$itemCode, $name, $barcode, $sale_price, $show_on_pos, $imagePath, $_SESSION['user_id']]);
            header('Location: list.php');
            exit;
        }
    }
}

$page_title = $material ? 'دەستکاریکردنی مادە' : 'مادەی نوێ';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="list.php">⟵ گەڕانەوە</a>

<div class="card mt10" style="max-width:640px">
  <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="form-row">
      <label>کۆدی ئایتەم</label>
      <input type="text" value="<?= $material ? htmlspecialchars($material['item_code']) : '(بەشێوەی خۆکار دادەنرێت)' ?>" disabled>
    </div>
    <div class="form-row"><label>ناوی مادە</label><input type="text" name="name" required value="<?= htmlspecialchars($material['name'] ?? '') ?>"></div>
    <div class="form-row"><label>بارکۆد</label><input type="text" name="barcode" required value="<?= htmlspecialchars($material['barcode'] ?? '') ?>"></div>
    <div class="form-row"><label>نرخی کرین (خوێندنەوە تەنها)</label><input type="text" value="<?= number_format($material['purchase_price'] ?? 0) ?>" disabled></div>
    <div class="form-row"><label>نرخی فرۆشتن</label><input type="number" step="0.01" name="sale_price" required value="<?= $material['sale_price'] ?? 0 ?>"></div>
    <div class="form-row"><label>بری هەبوون (خوێندنەوە تەنها)</label><input type="text" value="<?= $material['quantity'] ?? 0 ?>" disabled></div>
    <div class="form-row"><label>کۆست (خوێندنەوە تەنها)</label><input type="text" value="<?= number_format($material['cost'] ?? 0) ?>" disabled></div>
    <div class="form-row"><label>وێنەی مادە</label><input type="file" name="image" accept="image/*"></div>
    <?php if (!empty($material['image_path'])): ?>
      <img src="../<?= htmlspecialchars($material['image_path']) ?>" style="max-width:120px;border-radius:8px;margin-bottom:12px">
    <?php endif; ?>
    <div class="form-row">
      <label><input type="checkbox" name="show_on_pos" <?= (!$material || $material['show_on_pos']) ? 'checked' : '' ?>> پیشاندان لەسەر POS</label>
    </div>
    <button class="btn btn-primary">پاشەکەوتکردن</button>
    <a class="btn btn-outline" href="list.php">هەڵوەشاندنەوە</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
