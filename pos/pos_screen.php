<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$u = current_user();

if (!$u['is_cashier'] && !has_permission('pos')) {
    require_permission('pos');
}

$posId = $u['pos_id'];
if ($u['is_cashier'] && !$posId) {
    die('ئەم یوزەرە هیچ POS ێکی بۆ دیاری نەکراوە. تکایە پەیوەندی بە IT بکە.');
}
// یوزەری non-cashier کە بەشی POS دەکاتەوە -> یەکەم POS چالاک بەکاردەهێنێت
if (!$posId) {
    $posId = (int) db()->query('SELECT id FROM pos_terminals WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
}

$materials = db()->query('SELECT id, name, barcode, sale_price, image_path, quantity FROM materials
                           WHERE show_on_pos = 1 AND is_stopped = 0 ORDER BY name')->fetchAll();

$page_title = 'POS';
$show_back = !$u['is_cashier'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pos-grid">
  <div class="pos-items">
    <div class="form-row mb10">
      <input type="text" id="posSearch" placeholder="گەڕان بە بارکۆد یان ناو... (سکان بکە)" autofocus>
    </div>
    <div class="grid-cards" id="posGrid">
      <?php foreach ($materials as $m): ?>
        <div class="item-card" data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>" data-barcode="<?= htmlspecialchars($m['barcode']) ?>"
             onclick='addToCart(<?= json_encode(['id'=>$m['id'],'name'=>$m['name'],'barcode'=>$m['barcode'],'price'=>(float)$m['sale_price']]) ?>)'>
          <img src="<?= $m['image_path'] ? '../' . htmlspecialchars($m['image_path']) : 'https://placehold.co/150x90?text=No+Image' ?>" alt="">
          <div class="name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="price"><?= number_format($m['sale_price']) ?> د.ع</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pos-cart">
    <h3>🧺 سەبەتە</h3>
    <div class="lines" id="cartLines"></div>
    <div class="total-box">کۆی گشتی: <span id="cartTotal">0</span> د.ع</div>
    <button class="btn btn-success" style="width:100%;font-size:16px;padding:14px" id="checkoutBtn">✔ فرۆشتن و پرینت</button>
    <button class="btn btn-outline mt10" style="width:100%" id="clearCartBtn">🗑 بەتاڵکردنەوەی سەبەتە</button>
  </div>
</div>

<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const posId = <?= (int)$posId ?>;
let cart = []; // {id, name, barcode, price, qty}

document.getElementById('posSearch').addEventListener('input', function(){
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('#posGrid .item-card').forEach(card => {
    const match = card.dataset.name.includes(q) || card.dataset.barcode.includes(q);
    card.style.display = match ? '' : 'none';
  });
});
// enter-to-add بۆ سکانەری بارکۆد: ئەگەر تەنها یەک ئایتم دیارە کلیکی لێ بکە
document.getElementById('posSearch').addEventListener('keydown', function(e){
  if (e.key === 'Enter') {
    const visible = [...document.querySelectorAll('#posGrid .item-card')].filter(c => c.style.display !== 'none');
    if (visible.length === 1) { visible[0].click(); this.value=''; this.dispatchEvent(new Event('input')); }
  }
});

function addToCart(item) {
  const existing = cart.find(l => l.id === item.id);
  if (existing) existing.qty += 1;
  else cart.push({...item, qty: 1});
  renderCart();
}
function changeQty(id, delta) {
  const l = cart.find(x => x.id === id);
  if (!l) return;
  l.qty += delta;
  if (l.qty <= 0) cart = cart.filter(x => x.id !== id);
  renderCart();
}
function renderCart() {
  const box = document.getElementById('cartLines');
  let total = 0;
  box.innerHTML = cart.map(l => {
    const lt = l.qty * l.price;
    total += lt;
    return `<div class="cart-line">
      <div>${l.name}<br><span class="text-muted">${fmtMoney(l.price)} × ${l.qty}</span></div>
      <div class="flex">
        <button class="btn btn-outline btn-sm" onclick="changeQty(${l.id},-1)">−</button>
        <button class="btn btn-outline btn-sm" onclick="changeQty(${l.id},1)">+</button>
        <strong>${fmtMoney(lt)}</strong>
      </div>
    </div>`;
  }).join('') || '<p class="text-muted">سەبەتە بەتاڵە</p>';
  document.getElementById('cartTotal').textContent = fmtMoney(total);
}

document.getElementById('clearCartBtn').addEventListener('click', () => { cart = []; renderCart(); });

document.getElementById('checkoutBtn').addEventListener('click', () => {
  if (!cart.length) { alert('سەبەتە بەتاڵە'); return; }
  azaFetch('checkout.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ csrf, pos_id: posId, lines: cart.map(l => ({material_id:l.id, qty:l.qty, price:l.price})) })
  }).then(res => {
    window.open('receipt.php?id=' + res.sale_id, '_blank');
    cart = [];
    renderCart();
    location.reload();
  }).catch(err => alert(err.message || 'هەڵەیەک ڕوویدا'));
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
