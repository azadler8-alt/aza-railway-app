<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
enforce_cashier_lock();

$page_title = 'دشبۆردی سەرەکی';
$show_back  = false;

$cards = [
    ['key'=>'purchase',   'icon'=>'🧾', 'label'=>'پرچێس (کرین)',    'url'=>'purchase/records.php'],
    ['key'=>'companies',  'icon'=>'🏢', 'label'=>'کۆمپانیاکان',      'url'=>'companies/list.php'],
    ['key'=>'materials',  'icon'=>'📦', 'label'=>'مادەکان',          'url'=>'materials/list.php'],
    ['key'=>'warehouse',  'icon'=>'🏬', 'label'=>'مەغزەن',           'url'=>'warehouse/stock.php'],
    ['key'=>'it',         'icon'=>'💻', 'label'=>'IT (یوزەر و POS)', 'url'=>'it/users.php'],
    ['key'=>'sales',      'icon'=>'💰', 'label'=>'مەبیعات',          'url'=>'sales/drawer.php'],
    ['key'=>'accounting', 'icon'=>'📊', 'label'=>'حیسابات',          'url'=>'accounting/cash_receipt.php'],
    ['key'=>'pos',        'icon'=>'🧾', 'label'=>'POS',              'url'=>'pos/pos_screen.php'],
    ['key'=>'reports',    'icon'=>'📈', 'label'=>'راپۆرت',           'url'=>'reports/profit.php'],
];

include __DIR__ . '/includes/header.php';
?>
<div class="grid-cards">
  <?php foreach ($cards as $c): if (!has_permission($c['key'])) continue; ?>
    <a class="dash-card" href="<?= BASE_URL ?>/<?= $c['url'] ?>">
      <div class="icon"><?= $c['icon'] ?></div>
      <div class="label"><?= $c['label'] ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
