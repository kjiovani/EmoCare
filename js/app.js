/* ===========================
   AUTH GUARD (dashboard terlindungi)
=========================== */
function getAuthUser() {
  try { return JSON.parse(localStorage.getItem('auth_user')); } catch { return null; }
}
function ensureAuth() {
  const u = getAuthUser();
  if (!u) { location.replace('login.html'); }
}
function hydrateGreeting() {
  const u = getAuthUser();
  const nameEl = document.getElementById('greetUsername');
  if (nameEl && u?.username) nameEl.textContent = u.username.split(' ')[0];

  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    const d = new Date();
    const h = d.getHours();
    document.getElementById('greetClock').textContent = `${pad(h)}:${pad(d.getMinutes())}`;
    document.getElementById('greetTimeWord').textContent =
      h < 11 ? 'Pagi' : h < 15 ? 'Siang' : h < 19 ? 'Sore' : 'Malam';
  }
  tick(); setInterval(tick, 1000);

  // streak dari activity harian
  const streak = calcStreak();
  const el = document.getElementById('greetStreak');
  if (el) el.textContent = streak;
}

/* ===========================
   HELPERS & STORAGE
=========================== */
function uid() { return Math.random().toString(36).slice(2) + Date.now().toString(36); }
function read(key, fallback = null) { try { const v = JSON.parse(localStorage.getItem(key)); return v == null ? fallback : v; } catch { return fallback; } }
function write(key, val) { localStorage.setItem(key, JSON.stringify(val)); }
function todayISO() {
  const d = new Date(); const m = String(d.getMonth() + 1).padStart(2, '0'); const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}
function formatDate(iso) { if (!iso) return ''; const [y, m, d] = iso.split('-'); return `${d}/${m}/${y}`; }
function timeAgo(iso) {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return `${s} detik lalu`;
  const m = Math.floor(s / 60); if (m < 60) return `${m} menit lalu`;
  const h = Math.floor(m / 60); if (h < 24) return `${h} jam lalu`;
  const d = Math.floor(h / 24); return `${d} hari lalu`;
}

/* keys */
const MOOD_KEY = 'ec_moods';
const JOURNAL_KEY = 'ec_journals';
const REM_KEY = 'ec_reminders';
const ACT_KEY = 'ec_activity';
const QUIZ_LAST_KEY = 'ec_quiz_results';
const QUIZ_HIST_KEY = 'ec_quiz_history';

/* ===========================
   VIDEO QUOTES (fixed link)
=========================== */
const QUOTE_EMBED = 'https://www.youtube.com/embed/_50igeHW7vw?rel=0&modestbranding=1';
function renderVideoQuote() {
  const el = document.getElementById('video-frame');
  if (!el) return;
  el.innerHTML = `
    <iframe src="${QUOTE_EMBED}" title="Motivational Video"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
}

/* ===========================
   ACTIVITY LOG
=========================== */
function pushActivity({ type, title }) {
  const list = read(ACT_KEY, []);
  list.unshift({ id: uid(), type, title, atISO: new Date().toISOString() });
  write(ACT_KEY, list.slice(0, 100));
  renderActivityList();
  renderStats();
}
function renderActivityList() {
  const host = document.getElementById('activityList'); if (!host) return;
  const list = read(ACT_KEY, []);
  if (!list.length) { host.innerHTML = '<li class="ec-muted">Belum ada aktivitas.</li>'; return; }
  const dot = t => t === 'mood' ? 'dot-green' : t === 'journal' ? 'dot-purple' : t === 'quiz' ? 'dot-orange' : 'dot-blue';
  host.innerHTML = list.slice(0, 5).map(a => `
    <li>
      <div class="dot ${dot(a.type)}"></div>
      <div>
        <div class="t">${a.title}</div>
        <div class="meta">${timeAgo(a.atISO)}</div>
      </div>
    </li>`).join('');
}
function calcStreak() {
  const acts = read(ACT_KEY, []);
  if (!acts.length) return 0;
  let streak = 0;
  const daySet = new Set(acts.map(a => new Date(a.atISO).toDateString()));
  let d = new Date();
  while (daySet.has(d.toDateString())) { streak++; d.setDate(d.getDate() - 1); }
  return streak;
}

/* ===========================
   STATS BAR
=========================== */
function renderStats() {
  // mood hari ini
  const today = todayISO();
  const moods = read(MOOD_KEY, []);
  const todayMood = moods.find(m => m.dateISO === today);
  const moodLabel = ['😭', '😟', '🙂', '😊', '😁'];
  document.getElementById('statMoodToday').textContent = todayMood ? moodLabel[todayMood.mood - 1] : 'Belum Diisi';

  // jurnal count
  const j = read(JOURNAL_KEY, []);
  document.getElementById('statJournals').textContent = j.length;

  // reminders aktif
  const r = read(REM_KEY, []).filter(x => x.active);
  document.getElementById('statReminders').textContent = r.length;

  // quiz selesai
  const q = read(QUIZ_HIST_KEY, []);
  document.getElementById('statQuiz').textContent = q.length;

  // tiles
  const activity = read(ACT_KEY, []);
  const tileActivity = document.getElementById('tileActivity'); if (tileActivity) tileActivity.textContent = activity.length;
  const tileStreak = document.getElementById('tileStreak'); if (tileStreak) tileStreak.textContent = `${calcStreak()} hari`;
  const avg = moods.length ? (moods.reduce((s, x) => s + x.mood, 0) / moods.length).toFixed(1) : '0';
  const tileAvgMood = document.getElementById('tileAvgMood'); if (tileAvgMood) tileAvgMood.textContent = `${avg}/5`;
  const tileQuizDone = document.getElementById('tileQuizDone'); if (tileQuizDone) tileQuizDone.textContent = (q || []).length;

  // Self-Care Today section
  renderRemToday();
}

/* ===========================
   MOOD TRACKER + TANGGAL + CRUD
=========================== */
let moodEditingId = null;
function bindMoodForm() {
  const date = document.getElementById('moodDate');
  const note = document.getElementById('moodNote');
  const count = document.getElementById('moodCount');
  const form = document.getElementById('moodForm');
  const wrap = document.getElementById('moodChoices');

  if (date) date.value = todayISO();
  let selected = 3; // default

  wrap?.querySelectorAll('.ec-mood').forEach(btn => {
    btn.addEventListener('click', () => {
      selected = Number(btn.dataset.value);
      wrap.querySelectorAll('.ec-mood').forEach(b => b.classList.remove('ec-active'));
      btn.classList.add('ec-active');
    });
  });

  note?.addEventListener('input', () => count.textContent = note.value.length);

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const payload = {
      id: moodEditingId || uid(),
      dateISO: date?.value || todayISO(),
      mood: selected,
      note: note?.value?.trim() || '',
      createdAt: new Date().toISOString()
    };

    let list = read(MOOD_KEY, []);
    const existIdx = list.findIndex(x => x.id === payload.id);
    if (existIdx > -1) { list[existIdx] = { ...list[existIdx], ...payload }; }
    else { list.push(payload); }
    write(MOOD_KEY, list);

    pushActivity({ type: 'mood', title: `Mencatat mood: ${['Sedih sekali', 'Sedih', 'Biasa', 'Bahagia', 'Sangat bahagia'][selected - 1]}` });

    note.value = ''; count.textContent = '0';
    renderStats();
  });
}

/* ===========================
   JURNAL DIGITAL + CRUD
=========================== */
let journalEditingId = null;
function journalCard(j) {
  const body = (j.body || '').replace(/</g, '&lt;');
  return `
    <div class="ec-j-card" data-id="${j.id}">
      <div class="ec-j-title">${j.title ? j.title.replace(/</g, '&lt;') : '(Tanpa judul)'}</div>
      <div class="ec-j-date">${formatDate(j.dateISO)}</div>
      <div class="ec-j-body" style="margin:8px 0">${body.length > 220 ? body.slice(0, 220) + '…' : body}</div>
      <div class="ec-j-actions">
        <button class="ec-btn-ghost j-edit">Edit</button>
        <button class="ec-btn-ghost j-del">Hapus</button>
      </div>
    </div>`;
}
function renderJournalList() {
  const host = document.getElementById('journalList'); if (!host) return;
  const items = (read(JOURNAL_KEY, []) || []).sort((a, b) => b.dateISO.localeCompare(a.dateISO));
  host.innerHTML = items.length ? items.map(journalCard).join('') : '<div class="ec-muted">Belum ada jurnal.</div>';
}
function bindJournalForm() {
  const form = document.getElementById('journalForm');
  const date = document.getElementById('journalDate');
  const title = document.getElementById('journalTitle');
  const body = document.getElementById('journalBody');
  const cancel = document.getElementById('journalCancelEditBtn');
  if (date) date.value = todayISO();

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const items = read(JOURNAL_KEY, []) || [];
    const payload = {
      id: journalEditingId || uid(),
      dateISO: date.value || todayISO(),
      title: title.value.trim(),
      body: body.value.trim(),
      updatedAt: new Date().toISOString(),
      createdAt: new Date().toISOString()
    };
    const idx = items.findIndex(x => x.id === payload.id);
    if (idx > -1) { items[idx] = { ...items[idx], ...payload }; journalEditingId = null; cancel.style.display = 'none'; }
    else { items.push(payload); pushActivity({ type: 'journal', title: `Menulis jurnal: “${payload.title || 'Tanpa judul'}”` }); }
    write(JOURNAL_KEY, items);
    title.value = ''; body.value = '';
    renderJournalList(); renderStats();
  });

  cancel?.addEventListener('click', () => { journalEditingId = null; cancel.style.display = 'none'; title.value = ''; body.value = ''; });

  document.addEventListener('click', (e) => {
    const card = e.target.closest?.('.ec-j-card'); if (!card) return;
    const id = card.getAttribute('data-id');
    const items = read(JOURNAL_KEY, []) || [];
    if (e.target.classList.contains('j-del')) {
      if (confirm('Hapus jurnal ini?')) { write(JOURNAL_KEY, items.filter(x => x.id !== id)); renderJournalList(); renderStats(); }
    }
    if (e.target.classList.contains('j-edit')) {
      const j = items.find(x => x.id === id); if (!j) return;
      journalEditingId = id;
      date.value = j.dateISO; title.value = j.title; body.value = j.body;
      cancel.style.display = 'inline-block';
      window.scrollTo({ top: form.offsetTop - 60, behavior: 'smooth' });
    }
  });
}

/* ===========================
   REMINDERS + custom + alarm
=========================== */
let remEditId = null; let remTicker = null;
async function ensureNotifPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    try { await Notification.requestPermission(); } catch { }
  }
}
function renderReminderList() {
  const host = document.getElementById('reminderList'); if (!host) return;
  const items = read(REM_KEY, []) || [];
  host.innerHTML = items.length ? items.map(r => `
    <div class="ec-rem-card" data-id="${r.id}">
      <div>
        <div><strong>${r.title.replace(/</g, '&lt;')}</strong></div>
        <div class="meta">${r.timeHHMM} • ${r.active ? 'Aktif' : 'Nonaktif'}</div>
      </div>
      <div class="ec-actions">
        <label class="ec-switch">
          <input type="checkbox" class="rem-toggle" ${r.active ? 'checked' : ''}>
          <span class="track"></span><span class="thumb"></span>
        </label>
        <button class="ec-btn-ghost rem-edit">Edit</button>
        <button class="ec-btn-ghost rem-del">Hapus</button>
      </div>
    </div>`).join('') : '<div class="ec-muted">Belum ada pengingat.</div>';
}
function bindReminderForm() {
  const form = document.getElementById('reminderForm');
  const preset = document.getElementById('reminderPreset');
  const title = document.getElementById('reminderTitle');
  const time = document.getElementById('reminderTime');

  preset?.addEventListener('change', () => {
    const v = preset.value; title.style.display = (v === '__custom') ? 'block' : 'none';
  });

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const items = read(REM_KEY, []) || [];
    const name = preset.value === '__custom' ? (title.value.trim() || 'Pengingat') : preset.value;
    const t = time.value || '09:00';
    if (remEditId) {
      const i = items.findIndex(x => x.id === remEditId);
      if (i > -1) items[i] = { ...items[i], title: name, timeHHMM: t };
      remEditId = null;
    } else {
      items.push({ id: uid(), title: name, timeHHMM: t, active: true, doneToday: false, lastTriggered: null });
      pushActivity({ type: 'reminder', title: `Tambah pengingat: ${name}` });
    }
    write(REM_KEY, items);
    title.value = ''; time.value = '';
    renderReminderList(); renderStats();
  });

  // Delegasi
  document.addEventListener('click', (e) => {
    const card = e.target.closest?.('.ec-rem-card'); if (!card) return;
    const id = card.getAttribute('data-id');
    const items = read(REM_KEY, []) || [];
    if (e.target.classList.contains('rem-del')) {
      if (confirm('Hapus pengingat ini?')) { write(REM_KEY, items.filter(x => x.id !== id)); renderReminderList(); renderStats(); }
    }
    if (e.target.classList.contains('rem-edit')) {
      const r = items.find(x => x.id === id); if (!r) return;
      remEditId = id;
      const options = Array.from(preset.options).map(o => o.value);
      if (options.includes(r.title)) { preset.value = r.title; title.style.display = 'none'; }
      else { preset.value = '__custom'; title.style.display = 'block'; title.value = r.title; }
      time.value = r.timeHHMM;
      window.scrollTo({ top: form.offsetTop - 60, behavior: 'smooth' });
    }
    if (e.target.classList.contains('rem-toggle')) {
      const r = items.find(x => x.id === id); if (!r) return;
      r.active = !r.active; write(REM_KEY, items); renderStats();
    }
  });
}
function startReminderTicker() {
  if (remTicker) clearInterval(remTicker);
  remTicker = setInterval(() => {
    const items = (read(REM_KEY, []) || []).filter(r => r.active);
    const now = new Date();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    const key = `${hh}:${mm}`;
    let changed = false;
    items.forEach(r => {
      if (r.timeHHMM === key && r.lastTriggered !== now.toDateString()) {
        try { if ('Notification' in window) new Notification(`Pengingat: ${r.title}`); } catch { }
        alert(`Waktunya: ${r.title}`);
        r.lastTriggered = now.toDateString(); changed = true;
      }
    });
    if (changed) {
      const all = read(REM_KEY, []) || [];
      items.forEach(up => {
        const i = all.findIndex(a => a.id === up.id);
        if (i > -1) all[i].lastTriggered = up.lastTriggered;
      });
      write(REM_KEY, all);
    }
  }, 30000);
}
function renderRemToday() {
  const host = document.getElementById('remTodayList'); if (!host) return;
  const items = (read(REM_KEY, []) || []).filter(x => x.active);
  host.innerHTML = items.length ? items.map(r => `
    <li class="ec-rem-item" data-id="${r.id}">
      <div>
        <strong>${r.title.replace(/</g, '&lt;')}</strong>
        <div class="meta">${r.timeHHMM}</div>
      </div>
      <label class="ec-switch">
        <input type="checkbox" class="rem-done" ${r.doneToday ? 'checked' : ''}>
        <span class="track"></span><span class="thumb"></span>
      </label>
    </li>`).join('') : '<li class="ec-muted">Belum ada pengingat aktif.</li>';

  // progress
  const done = items.filter(i => i.doneToday).length;
  const total = items.length || 0;
  document.getElementById('remProgressCount').textContent = `${done}/${total}`;
  document.getElementById('remProgressBar').style.width = total ? `${Math.round(done / total * 100)}%` : '0%';

  document.querySelectorAll('.rem-done').forEach(chk => {
    chk.addEventListener('change', (e) => {
      const id = e.target.closest('.ec-rem-item').getAttribute('data-id');
      const list = read(REM_KEY, []) || [];
      const r = list.find(x => x.id === id); if (!r) return;
      r.doneToday = e.target.checked; write(REM_KEY, list);
      renderRemToday();
    });
  });
}

/* ===========================
   QUIZ (grid 4 kartu + modal 10 soal)
=========================== */
const QUESTIONS = [
  'Saya merasa termotivasi hari ini.',
  'Saya bisa mengelola stres saya.',
  'Saya merasa didukung oleh orang sekitar.',
  'Saya tidur cukup dalam seminggu terakhir.',
  'Saya merasa optimis tentang masa depan.',
  'Saya dapat berkonsentrasi dengan baik.',
  'Saya menikmati aktivitas harian.',
  'Saya merasa mampu mengatasi masalah.',
  'Saya merasa tenang dan rileks.',
  'Saya merasa berharga sebagai individu.'
];
const QUIZ_DEFS = [
  { key: 'stress', title: 'Tes Tingkat Stress', desc: 'Evaluasi tingkat stress harianmu', icon: '🧯' },
  { key: 'anxiety', title: 'Kecemasan Sosial', desc: 'Ukur tingkat kecemasan dalam interaksi', icon: '😬' },
  { key: 'general', title: 'Kesehatan Mental Umum', desc: 'Penilaian menyeluruh kondisi saat ini', icon: '🫶' },
  { key: 'esteem', title: 'Self-Esteem Assessment', desc: 'Kepercayaan diri & harga diri', icon: '🌟' }
];

function mountQuizGrid() {
  const grid = document.getElementById('quizGrid'); if (!grid) return;
  const hist = read(QUIZ_HIST_KEY, []) || [];
  grid.innerHTML = QUIZ_DEFS.map(q => {
    const last = hist.filter(h => h.key === q.key).slice(0, 1)[0];
    const status = last ? '✅ Selesai' : '—';
    const scoreTxt = last ? `Skor terakhir: ${last.score}/50` : '';
    return `
      <div class="ec-quiz-card">
        <div class="h">${q.icon} ${q.title}</div>
        <div class="s">${q.desc}</div>
        <div class="ec-small">${status} ${scoreTxt}</div>
        <div style="display:flex; gap:8px">
          <button class="ec-btn-primary q-start"
  style="background:#FBCFE8!important;border-color:#FBCFE8!important;color:#6B214E!important"
   data-key="${q.key}">${last ? 'Ulangi Kuis' : 'Mulai Kuis'}</button>
        </div>
      </div>`;
  }).join('');

  grid.querySelectorAll('.q-start').forEach(btn => {
    btn.addEventListener('click', () => openQuizModal(btn.dataset.key));
  });
}

function openQuizModal(key) {
  const dlg = document.getElementById('quizModal');
  const form = document.getElementById('quizForm');
  document.getElementById('quizModalTitle').textContent = QUIZ_DEFS.find(q => q.key === key)?.title || 'Kuis';
  form.innerHTML = QUESTIONS.map((q, i) => `
    <fieldset class="ec-q-field">
      <legend>${i + 1}) ${q}</legend>
      <div style="display:flex; gap:14px; flex-wrap:wrap">
        ${[1, 2, 3, 4, 5].map(v => `
          <label style="display:flex; gap:6px; align-items:center">
            <input type="radio" name="q${i}" value="${v}">
            ${['Sangat tidak setuju', 'Tidak setuju', 'Netral', 'Setuju', 'Sangat setuju'][v - 1]}
          </label>`).join('')}
      </div>
    </fieldset>`).join('') + `
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px">
      <button id="quizSubmit" class="ec-btn-primary" type="button" disabled>Lihat Hasil</button>
    </div>`;

  const resultHost = document.getElementById('quizResult'); resultHost.innerHTML = '';
  dlg.showModal();

  function check() {
    const ok = QUESTIONS.every((_, i) => form.querySelector(`input[name="q${i}"]:checked`));
    document.getElementById('quizSubmit').disabled = !ok;
  }
  form.addEventListener('change', check);
  check();

  document.getElementById('quizSubmit').onclick = () => {
    let score = 0; const answers = [];
    QUESTIONS.forEach((_, i) => { const v = Number(form.querySelector(`input[name="q${i}"]:checked`).value); score += v; answers.push(v); });
    write(QUIZ_LAST_KEY, { score, answers, ts: new Date().toISOString() });
    const hist = read(QUIZ_HIST_KEY, []) || [];
    hist.unshift({ id: uid(), key, score, ts: new Date().toISOString() });
    write(QUIZ_HIST_KEY, hist.slice(0, 100));
    pushActivity({ type: 'quiz', title: `Selesai kuis: ${QUIZ_DEFS.find(q => q.key === key)?.title || 'Kuis'}` });
    renderQuizResult(score);
    mountQuizGrid();
    renderStats();
  };
}

function scoreToDeg(score) { return ((score - 10) / 40) * 180; }
function interpret(score) {
  if (score <= 19) return { label: 'Sehat', color: '#22c55e', msg: 'Kesehatan mentalmu berada pada zona aman. Pertahankan kebiasaan baik dan self-care rutin.' };
  if (score <= 29) return { label: 'Waspada ringan', color: '#f59e0b', msg: 'Ada tanda kelelahan/tekanan ringan. Coba atur istirahat dan teknik relaksasi.' };
  if (score <= 39) return { label: 'Moderat', color: '#ff9900', msg: 'Perlu perhatian lebih. Pertimbangkan berbagi cerita atau berkonsultasi.' };
  return { label: 'Depresi berat', color: '#ef4444', msg: 'Dapatkan bantuan ahli kesehatan mental sekarang untuk diperiksa lebih lanjut. Kamu tidak sendirian; bantuan tersedia.' };
}
function renderQuizResult(score) {
  const host = document.getElementById('quizResult'); if (!host) return;
  const deg = Math.max(0, Math.min(180, scoreToDeg(score)));
  const info = interpret(score);
  host.innerHTML = `
    <div class="ec-card">
      <div style="font-weight:800; font-size:18px">SKOR KAMU ${score}</div>
      <svg class="ec-gauge" viewBox="0 0 200 120">
        <defs>
          <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#22c55e"/>
            <stop offset="33%" stop-color="#facc15"/>
            <stop offset="66%" stop-color="#fb923c"/>
            <stop offset="100%" stop-color="#ef4444"/>
          </linearGradient>
        </defs>
        <path d="M10,110 A90,90 0 0 1 190,110" stroke="url(#g)" stroke-width="18" fill="none"/>
        <circle cx="100" cy="110" r="4" fill="#334155"/>
        <g transform="rotate(${180 - deg} 100 110)">
          <rect x="98.5" y="20" width="3" height="90" fill="#334155"/>
          <polygon points="100,6 94,22 106,22" fill="#334155"/>
        </g>
      </svg>
      <div style="margin-top:8px; font-weight:800; color:${info.color}">Status: ${info.label}</div>
      <p style="margin-top:4px">${info.msg}</p>
    </div>`;
}

/* ===========================
   CHARTS (Chart.js)
=========================== */
function renderCharts() {
  if (!window.Chart) return;
  const moods = read(MOOD_KEY, []) || [];
  const last7 = [...moods].sort((a, b) => b.dateISO.localeCompare(a.dateISO)).slice(0, 7).reverse();
  const labels = last7.map(m => m.dateISO.slice(5).replace('-', '/'));
  const data = last7.map(m => m.mood);

  const ctx1 = document.getElementById('chartMood');
  if (ctx1) {
    new Chart(ctx1.getContext('2d'), {
      type: 'line',
      data: { labels, datasets: [{ label: 'Mood', data, fill: true, tension: .3 }] },
      options: { scales: { y: { min: 1, max: 5, ticks: { stepSize: 1 } } } }
    });
  }

  const acts = read(ACT_KEY, []) || [];
  const counts = { mood: 0, journal: 0, quiz: 0, reminder: 0 };
  acts.forEach(a => { if (counts[a.type] != null) counts[a.type]++; });
  const ctx2 = document.getElementById('chartPie');
  if (ctx2) {
    new Chart(ctx2.getContext('2d'), {
      type: 'pie',
      data: {
        labels: ['Kuis', 'Jurnal', 'Pengingat', 'Mood'],
        datasets: [{ data: [counts.quiz, counts.journal, counts.reminder, counts.mood] }]
      }
    });
  }
}

/* ===========================
   LOGOUT BTN (dari auth.js pun bisa)
=========================== */
document.addEventListener('click', (e) => {
  if (e.target.id === 'logoutBtn') {
    localStorage.removeItem('auth_user');
    location.replace('login.html');
  }
});

/* ===========================
   INIT
=========================== */
document.addEventListener('DOMContentLoaded', async () => {
  ensureAuth();               // proteksi halaman
  hydrateGreeting();
  renderVideoQuote();
  renderActivityList();
  renderStats();

  // Mood
  bindMoodForm();

  // Journal
  bindJournalForm(); renderJournalList();

  // Reminders
  bindReminderForm(); renderReminderList(); await ensureNotifPermission(); startReminderTicker();

  // Quiz
  mountQuizGrid();

  // Charts
  renderCharts();
});