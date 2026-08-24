// ==== yardîdercar giştî — helper خشتیاری گشتی ====

function azaFetch(url, options = {}) {
  return fetch(url, options).then(async (r) => {
    let data;
    try { data = await r.json(); } catch (e) { data = null; }
    if (!r.ok) throw (data || { message: 'هەڵەیەک ڕوویدا' });
    return data;
  });
}

function debounce(fn, delay = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), delay);
  };
}

function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

function fmtMoney(n) {
  n = Number(n || 0);
  return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
}

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-bg')) {
    e.target.classList.remove('open');
  }
});
