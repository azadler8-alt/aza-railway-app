<?php
$u = current_user();
$isCashier = !empty($u['is_cashier']);
$cur = basename($_SERVER['SCRIPT_NAME']);
$curDir = basename(dirname($_SERVER['SCRIPT_NAME']));

function nav_active($dir, $curDir) { return $dir === $curDir ? 'active' : ''; }
?>
<div class="sidebar">
  <div class="brand">aza</div>
  <nav>
    <?php if ($isCashier): ?>
      <a class="active" href="<?= BASE_URL ?>/pos/pos_screen.php">🧾 POS</a>
    <?php else: ?>
      <a class="<?= nav_active('','') ?> <?= $cur === 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard.php">🏠 دشبۆرد</a>
      <?php if (has_permission('purchase')): ?>
        <a class="<?= nav_active('purchase',$curDir) ?>" href="<?= BASE_URL ?>/purchase/records.php">🧾 پرچێس</a>
      <?php endif; ?>
      <?php if (has_permission('companies')): ?>
        <a class="<?= nav_active('companies',$curDir) ?>" href="<?= BASE_URL ?>/companies/list.php">🏢 کۆمپانیاکان</a>
      <?php endif; ?>
      <?php if (has_permission('materials')): ?>
        <a class="<?= nav_active('materials',$curDir) ?>" href="<?= BASE_URL ?>/materials/list.php">📦 مادەکان</a>
      <?php endif; ?>
      <?php if (has_permission('warehouse')): ?>
        <a class="<?= nav_active('warehouse',$curDir) ?>" href="<?= BASE_URL ?>/warehouse/stock.php">🏬 مەغزەن</a>
      <?php endif; ?>
      <?php if (has_permission('it')): ?>
        <a class="<?= nav_active('it',$curDir) ?>" href="<?= BASE_URL ?>/it/users.php">💻 IT</a>
      <?php endif; ?>
      <?php if (has_permission('sales')): ?>
        <a class="<?= nav_active('sales',$curDir) ?>" href="<?= BASE_URL ?>/sales/drawer.php">💰 مەبیعات</a>
      <?php endif; ?>
      <?php if (has_permission('accounting')): ?>
        <a class="<?= nav_active('accounting',$curDir) ?>" href="<?= BASE_URL ?>/accounting/cash_receipt.php">📊 حیسابات</a>
      <?php endif; ?>
      <?php if (has_permission('pos')): ?>
        <a class="<?= nav_active('pos',$curDir) ?>" href="<?= BASE_URL ?>/pos/pos_screen.php">🧾 POS</a>
      <?php endif; ?>
      <?php if (has_permission('reports')): ?>
        <a class="<?= nav_active('reports',$curDir) ?>" href="<?= BASE_URL ?>/reports/profit.php">📈 راپۆرت</a>
      <?php endif; ?>
    <?php endif; ?>
    <a class="logout" href="<?= BASE_URL ?>/logout.php">🚪 چوونەدەرەوە</a>
  </nav>
</div>
