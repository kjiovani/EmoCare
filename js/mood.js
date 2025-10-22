// ============ Mood Tracker v2.1 Logic (works: save/search/edit/delete) ============
const KEY = 'ec_mood_entries';
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const LABELS = {
  1: 'Senang',
  2: 'Biasa',
  3: 'Cemas',
  4: 'Stress',
  5: 'Senang Sekali'
};

const formatDate = (iso) => {
  const d = new Date(iso);
  if (isNaN(d)) return iso;
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

function readStore() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
function writeStore(arr) { localStorage.setItem(KEY, JSON.stringify(arr)); }

function getFormData() {
  const scaleEl = document.querySelector('input[name="mood-scale"]:checked');
  const note = document.querySelector('#mood-note').value.trim();
  return { skala: scaleEl ? Number(scaleEl.value) : null, catatan: note };
}
function setFormData(entry) {
  const scale = entry.skala ? String(entry.skala) : '';
  if (scale) {
    const el = document.querySelector(`.scale-group input[value="${scale}"]`);
    if (el) el.checked = true;
  }
  document.querySelector('#mood-note').value = entry.catatan || '';
}
function resetForm() {
  document.querySelector('#mood-form').reset();
  document.querySelector('#editing-id').value = '';
  document.querySelector('#form-error').textContent = '';
  document.querySelector('#form-success').textContent = '';
}
function validate({ skala }) {
  if (!skala) return 'Pilih skala mood 1–5.';
  return '';
}

function renderTable(data) {
  const tbody = document.querySelector('#tbody-history');
  tbody.innerHTML = '';
  if (!data.length) {
    tbody.innerHTML = '<tr class="empty"><td colspan="5">Belum ada data.</td></tr>';
    return;
  }
  data.forEach((row, idx) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${idx + 1}</td>
      <td>${formatDate(row.tanggal)}</td>
      <td>${row.skala} — ${LABELS[row.skala] || ''}</td>
      <td>${row.catatan ? row.catatan.replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])) : '-'}</td>
      <td class="action">
        <button class="btn btn-ghost btn-edit" data-id="${row.id}">Edit</button>
        <button class="btn btn-ghost btn-del" data-id="${row.id}">Hapus</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function applySearch(rows) {
  const qEl = document.querySelector('#flt-q');
  const q = qEl ? qEl.value.toLowerCase().trim() : '';
  if (!q) return rows;
  return rows.filter(r => {
    const dateText = formatDate(r.tanggal).toLowerCase();
    const scaleText = String(r.skala) + ' ' + (LABELS[r.skala] || '');
    const noteText = (r.catatan || '').toLowerCase();
    return dateText.includes(q) || scaleText.toLowerCase().includes(q) || noteText.includes(q);
  });
}
function sortRows(rows, key, asc) {
  const copy = rows.slice();
  copy.sort((a, b) => {
    if (key === 'tanggal') {
      return asc ? (new Date(a.tanggal) - new Date(b.tanggal)) : (new Date(b.tanggal) - new Date(a.tanggal));
    }
    if (key === 'skala') {
      return asc ? (a.skala - b.skala) : (b.skala - a.skala);
    }
    return 0;
  });
  return copy;
}

// Global state
let rows = [];
let view = [];
let sortKey = 'tanggal';
let sortAsc = false;

function refresh() {
  view = applySearch(rows);
  view = sortRows(view, sortKey, sortAsc);
  renderTable(view);
}

function init() {
  rows = readStore();
  refresh();

  // submit form
  const form = document.querySelector('#mood-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = getFormData();
      const err = validate(data);
      if (err) { document.querySelector('#form-error').textContent = err; document.querySelector('#form-success').textContent = ''; return; }

      const editingId = document.querySelector('#editing-id').value;
      if (editingId) {
        rows = rows.map(r => r.id === editingId ? { ...r, ...data } : r);
        document.querySelector('#form-success').textContent = 'Entry berhasil diupdate.';
      } else {
        const id = Math.random().toString(36).slice(2);
        rows.push({ id, tanggal: new Date().toISOString(), ...data });
        document.querySelector('#form-success').textContent = 'Entry berhasil disimpan.';
      }
      writeStore(rows);
      refresh();
      resetForm();
    });
  }

  const resetBtn = document.querySelector('#btn-reset');
  if (resetBtn) resetBtn.addEventListener('click', resetForm);

  // table actions
  const tbody = document.querySelector('#tbody-history');
  if (tbody) {
    tbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      const id = btn.dataset.id;
      const entry = rows.find(r => r.id === id);
      if (btn.classList.contains('btn-edit')) {
        if (entry) {
          setFormData(entry);
          document.querySelector('#editing-id').value = entry.id;
          document.querySelector('#form-success').textContent = '';
          document.querySelector('#form-error').textContent = '';
          window.scrollTo({ top: document.querySelector('#mood-tracker').offsetTop - 20, behavior: 'smooth' });
        }
      } else if (btn.classList.contains('btn-del')) {
        if (confirm('Hapus entry ini?')) {
          rows = rows.filter(r => r.id !== id);
          writeStore(rows);
          refresh();
        }
      }
    });
  }

  // search
  const search = document.querySelector('#flt-q');
  if (search) search.addEventListener('input', refresh);

  // sort header
  document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.key;
      if (sortKey === key) { sortAsc = !sortAsc; } else { sortKey = key; sortAsc = false; }
      refresh();
    });
  });
}

document.addEventListener('DOMContentLoaded', init);
