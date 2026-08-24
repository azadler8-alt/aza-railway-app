<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
enforce_cashier_lock();
require_permission('purchase');

$id = (int)($_GET['id'] ?? 0);
$invoice = null;
$items = [];

if ($id) {
    $stmt = db()->prepare('SELECT pi.*, c.name AS company_name, u.full_name AS creator_name
                            FROM purchase_invoices pi
                            JOIN companies c ON c.id = pi.company_id
                            JOIN users u ON u.id = pi.created_by
                            WHERE pi.id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) die('وەسڵ نەدۆزرایەوە');

    $itemsStmt = db()->prepare('SELECT * FROM purchase_invoice_items WHERE invoice_id = ?');
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll();
}

$page_title = $invoice ? 'وەسڵی کرین: ' . $invoice['p_number'] : 'وەسڵی کرینی نوێ';
include __DIR__ . '/../includes/header.php';
?>
<a class="btn btn-outline btn-sm" href="records.php">⟵ گەڕانەوە بۆ ریکۆردی وەسڵەکان</a>

<?php if ($invoice): ?>
  <!-- ====== VIEW MODE بۆ وەسڵی هەبوو ====== -->
  <div class="card mt10">
    <div class="flex-between">
      <div>
        <h2><?= htmlspecialchars($invoice['p_number']) ?></h2>
        <p class="text-muted">
          کۆمپانیا: <strong><?= htmlspecialchars($invoice['company_name']) ?></strong> &nbsp;|&nbsp;
          دروستکراوە لەلایەن: <?= htmlspecialchars($invoice['creator_name']) ?> &nbsp;|&nbsp;
          بەروار: <?= date('Y-m-d H:i', strtotime($invoice['created_at'])) ?>
        </p>
      </div>
      <div>
        <?= $invoice['is_reviewed']
            ? '<span class="badge badge-green">✔ پێداچوونەوەی بۆ کراوە</span>'
            : '<span class="badge badge-orange">پێویستی بە پێداچوونەوەیە</span>' ?>
      </div>
    </div>

    <?php if (has_permission('purchase_review', 'edit')): ?>
      <form method="post" action="toggle_review.php" class="mt10">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $invoice['id'] ?>">
        <?php if ($invoice['is_reviewed']): ?>
          <input type="hidden" name="action" value="unreview">
          <button class="btn btn-warn">لابردنی پێداچوونەوە (کردنەوەی وەسڵ بۆ دەستکاری)</button>
        <?php else: ?>
          <input type="hidden" name="action" value="review">
          <button class="btn btn-success">✔ پێداچوونەوەی بۆ بکە (داخستنی وەسڵ)</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <table>
      <thead><tr><th>بارکۆد</th><th>ناو</th><th>عدد</th><th>نرخی کرینی پێشوو</th><th>نرخی کرینی ئێستا</th><th>کۆی گشتی</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['barcode']) ?></td>
            <td><?= htmlspecialchars($it['name']) ?></td>
            <td><?= $it['qty'] ?></td>
            <td><?= number_format($it['prev_purchase_price']) ?></td>
            <td><?= number_format($it['current_purchase_price']) ?></td>
            <td><?= number_format($it['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="5">کۆی گشتی</th><th><?= number_format($invoice['total_amount']) ?></th></tr></tfoot>
    </table>
  </div>

<?php else: ?>
  <!-- ====== دروستکردنی وەسڵی نوێ ====== -->
  <div class="card mt10">
    <div class="form-grid">
      <div class="form-row">
        <label>P Number</label>
        <input type="text" value="بەشێوەی خۆکار دادەنرێت لە کاتی پاشەکەوتکردن" disabled>
      </div>
      <div class="form-row" style="position:relative">
        <label>ناوی کۆمپانیا</label>
        <input type="text" id="companySearch" placeholder="دەستپێبکە بە نووسین..." autocomplete="off">
        <input type="hidden" id="companyId">
        <div id="companyResults" class="card" style="position:absolute;z-index:9;width:100%;display:none;max-height:220px;overflow-y:auto;padding:6px"></div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>زیادکردنی مادە</h3>
    <div class="form-grid" style="position:relative">
      <div class="form-row">
        <label>گەڕان بە بارکۆد یان ناو</label>
        <input type="text" id="itemSearch" placeholder="بارکۆد یان ناوی مادە..." autocomplete="off">
        <div id="itemResults" class="card" style="position:absolute;z-index:9;width:100%;display:none;max-height:220px;overflow-y:auto;padding:6px"></div>
      </div>
      <div class="form-row"><label>عدد</label><input type="number" step="0.01" id="itemQty" value="1"></div>
      <div class="form-row"><label>نرخی کرینی ئێستا</label><input type="number" step="0.01" id="itemPrice"></div>
      <div class="form-row" style="align-self:end"><button type="button" class="btn btn-primary" id="addLineBtn">+ زیادکردن</button></div>
    </div>

    <table class="mt10">
      <thead><tr><th>بارکۆد</th><th>ناو</th><th>عدد</th><th>نرخی کرینی پێشوو</th><th>نرخی کرینی ئێستا</th><th>کۆی گشتی</th><th></th></tr></thead>
      <tbody id="linesBody"></tbody>
      <tfoot><tr><th colspan="5">کۆی گشتی</th><th id="grandTotal">0</th><th></th></tr></tfoot>
    </table>

    <div class="mt10">
      <button class="btn btn-danger btn-sm" id="clearAllBtn" type="button">🗑 ڕەشکردنەوەی هەموو لاینەکان</button>
    </div>
  </div>

  <div class="mt10">
    <button class="btn btn-primary" id="saveInvoiceBtn">✔ پاشەکەوتکردن</button>
    <a class="btn btn-outline" href="records.php">✖ هەڵوەشاندنەوە</a>
  </div>

<script>
const csrf = <?= json_encode(csrf_token()) ?>;
let lines = [];
let selectedMaterial = null;

// ---- کۆمپانیا ----
const companySearch = document.getElementById('companySearch');
const companyResults = document.getElementById('companyResults');
companySearch.addEventListener('input', debounce(() => {
  const q = companySearch.value.trim();
  document.getElementById('companyId').value = '';
  if (!q) { companyResults.style.display='none'; return; }
  azaFetch('<?= BASE_URL ?>/companies/ajax_search.php?q=' + encodeURIComponent(q))
    .then(list => {
      companyResults.innerHTML = list.map(c =>
        `<div class="btn-outline" style="padding:8px;cursor:pointer;border-bottom:1px solid #eee" onclick="pickCompany(${c.id}, '${c.name.replace(/'/g,"")}')">${c.name}</div>`
      ).join('') || '<div class="text-muted" style="padding:8px">هیچ نەدۆزرایەوە</div>';
      companyResults.style.display = 'block';
    });
}, 300));
function pickCompany(id, name) {
  document.getElementById('companyId').value = id;
  companySearch.value = name;
  companyResults.style.display = 'none';
}

// ---- گەڕانی مادە ----
const itemSearch = document.getElementById('itemSearch');
const itemResults = document.getElementById('itemResults');
itemSearch.addEventListener('input', debounce(() => {
  const q = itemSearch.value.trim();
  if (!q) { itemResults.style.display='none'; return; }
  azaFetch('<?= BASE_URL ?>/materials/ajax_search.php?q=' + encodeURIComponent(q))
    .then(list => {
      itemResults.innerHTML = list.map(m =>
        `<div style="padding:8px;cursor:pointer;border-bottom:1px solid #eee" onclick='pickMaterial(${JSON.stringify(m)})'>${m.name} — ${m.barcode}</div>`
      ).join('') || '<div class="text-muted" style="padding:8px">هیچ نەدۆزرایەوە</div>';
      itemResults.style.display = 'block';
    });
}, 300));
function pickMaterial(m) {
  selectedMaterial = m;
  itemSearch.value = m.name + ' — ' + m.barcode;
  document.getElementById('itemPrice').value = m.purchase_price;
  itemResults.style.display = 'none';
}

document.getElementById('addLineBtn').addEventListener('click', () => {
  if (!selectedMaterial) { alert('تکایە مادەیەک هەڵبژێرە'); return; }
  const qty = parseFloat(document.getElementById('itemQty').value) || 0;
  const price = parseFloat(document.getElementById('itemPrice').value) || 0;
  if (qty <= 0) { alert('عدد پێویستە لە سفر زیاتر بێت'); return; }
  lines.push({
    material_id: selectedMaterial.id,
    barcode: selectedMaterial.barcode,
    name: selectedMaterial.name,
    prev_price: selectedMaterial.purchase_price,
    price: price,
    qty: qty
  });
  selectedMaterial = null;
  itemSearch.value = '';
  document.getElementById('itemQty').value = 1;
  document.getElementById('itemPrice').value = '';
  renderLines();
});

function removeLine(i) { lines.splice(i,1); renderLines(); }
document.getElementById('clearAllBtn').addEventListener('click', () => { lines = []; renderLines(); });

function renderLines() {
  const body = document.getElementById('linesBody');
  let total = 0;
  body.innerHTML = lines.map((l,i) => {
    const lt = l.qty * l.price;
    total += lt;
    return `<tr><td>${l.barcode}</td><td>${l.name}</td><td>${l.qty}</td><td>${fmtMoney(l.prev_price)}</td><td>${fmtMoney(l.price)}</td><td>${fmtMoney(lt)}</td>
      <td><button class="btn btn-danger btn-sm" onclick="removeLine(${i})">✖</button></td></tr>`;
  }).join('');
  document.getElementById('grandTotal').textContent = fmtMoney(total);
}

document.getElementById('saveInvoiceBtn').addEventListener('click', () => {
  const companyId = document.getElementById('companyId').value;
  if (!companyId) { alert('تکایە کۆمپانیایەک هەڵبژێرە'); return; }
  if (!lines.length) { alert('تکایە هیچ نەبێت یەک مادە زیاد بکە'); return; }

  azaFetch('save_invoice.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ csrf, company_id: companyId, lines })
  }).then(res => {
    window.location.href = 'invoice_form.php?id=' + res.invoice_id;
  }).catch(err => alert(err.message || 'هەڵەیەک ڕوویدا'));
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
