<?php
// play_quiz.php
require_once __DIR__ . '/backend/config.php';
require_login();

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ==============================
   Ambil & validasi kategori dari DB
================================ */
$cat = trim($_GET['cat'] ?? $_POST['cat'] ?? '');
$QUIZ_NAME = null;

if ($cat !== '') {
  $st = $mysqli->prepare("SELECT name FROM quiz_list WHERE slug=? AND is_active=1 LIMIT 1");
  $st->bind_param('s', $cat);
  $st->execute();
  $QUIZ_NAME = $st->get_result()->fetch_column();
  $st->close();
}
if (!$QUIZ_NAME) { http_response_code(404); exit('Kuis tidak ditemukan atau non-aktif.'); }

/* ==============================
   Ambil pertanyaan aktif + opsi (maks 5 terbaru)
   NOTE: opsi kosong / '-' diabaikan
================================ */
$qs  = [];
$ids = [];

$stmt = $mysqli->prepare("
  SELECT q.id, q.question_text, q.image_path
  FROM quiz_questions q
  WHERE q.category = ? AND q.is_active = 1
  ORDER BY q.id DESC
  LIMIT 5
");
$stmt->bind_param('s', $cat);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $qs[] = $row; $ids[] = (int)$row['id'];
}
$stmt->close();

$optmap = [];
if ($ids) {
  $in    = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sql   = "SELECT question_id, option_text
            FROM quiz_options
            WHERE question_id IN ($in)
            ORDER BY id ASC";
  $st = $mysqli->prepare($sql);
  $st->bind_param($types, ...$ids);
  $st->execute();
  $rr = $st->get_result();
  while ($o = $rr->fetch_assoc()) {
    $qid = (int)$o['question_id'];
    $t   = trim((string)$o['option_text']);
    if ($t === '' || $t === '-') continue; // skip kosong/placeholder
    $optmap[$qid][] = $t;
  }
  $st->close();
}

/* ==============================
   Payload untuk client
================================ */
$payload = [];
foreach ($qs as $q) {
  $opts = $optmap[(int)$q['id']] ?? [];
  if (count($opts) < 2) { // fallback minimal 2 opsi
    $opts = ['Ya', 'Tidak'];
  }
  $payload[] = [
    'id'   => (int)$q['id'],
    'q'    => $q['question_text'],
    'img'  => $q['image_path'] ?: null,
    'opts' => array_values($opts), // urutan dari DB = opsi-1 paling tinggi
  ];
}

/* ==============================
   Submit akhir: hitung & simpan,
   Skor DINAMIS (opsi-1 tertinggi)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'finish')) {
  $uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);

  // Jawaban dari client: [{id: question_id, v: 1..N}, ...]
  $answers = json_decode($_POST['answers'] ?? '[]', true);
  if (!is_array($answers) || empty($answers)) {
    header('Location: play_quiz.php?cat=' . urlencode($cat)); exit;
  }

  // Validasi: hanya pertanyaan aktif di kategori ini
  $validIds = [];
  $st = $mysqli->prepare("SELECT id FROM quiz_questions WHERE category=? AND is_active=1");
  $st->bind_param('s', $cat);
  $st->execute();
  $rr = $st->get_result();
  while ($r = $rr->fetch_assoc()) $validIds[(int)$r['id']] = true;
  $st->close();

  // Ambil jumlah opsi VALID (skip '' / '-') untuk setiap pertanyaan yang dijawab
  $postedIds = [];
  foreach ($answers as $a) { $postedIds[] = (int)($a['id'] ?? 0); }
  $postedIds = array_values(array_unique(array_filter($postedIds)));

  $qOptCount = []; // qid => N
  if ($postedIds) {
    $in    = implode(',', array_fill(0, count($postedIds), '?'));
    $types = str_repeat('i', count($postedIds));
    $sql   = "SELECT question_id, option_text
              FROM quiz_options
              WHERE question_id IN ($in)
              ORDER BY id ASC";
    $st = $mysqli->prepare($sql);
    $st->bind_param($types, ...$postedIds);
    $st->execute();
    $rs = $st->get_result();
    $tmp = [];
    while ($row = $rs->fetch_assoc()) {
      $qid = (int)$row['question_id'];
      $t   = trim((string)$row['option_text']);
      if ($t === '' || $t === '-') continue;
      $tmp[$qid][] = $t;
    }
    $st->close();
    foreach ($postedIds as $qid) {
      $N = isset($tmp[$qid]) ? count($tmp[$qid]) : 0;
      if ($N < 2) $N = 2; // minimal 2
      $qOptCount[$qid] = $N;
    }
  }

  // Fungsi skor: opsi-1 = 100%, opsi-N = 0%, linear di antaranya
  $scoreCount = 0;
  $sumPct     = 0.0;
  foreach ($answers as $a) {
    $qid = (int)($a['id'] ?? 0);
    $v   = (int)($a['v'] ?? 0); // index opsi yang dipilih (1..N)

    if (!isset($validIds[$qid])) continue; // hanya soal aktif
    $N = max(2, (int)($qOptCount[$qid] ?? 2));
    if ($v < 1 || $v > $N) continue;

    if ($N === 1) { $pct = 100.0; } // aman, tidak terjadi karena N≥2
    else {
      $step = 100.0 / ($N - 1);        // jarak antar opsi
      $pct  = ($N - $v) * $step;       // v=1 -> 100, v=N -> 0
    }
    $sumPct += $pct;
    $scoreCount++;
  }

  if ($scoreCount <= 0) {
    header('Location: play_quiz.php?cat=' . urlencode($cat)); exit;
  }

  $scorePct = round($sumPct / $scoreCount, 2);

  // Interpretasi (tetap)
  if     ($scorePct >= 85) { $label = 'Mental Sehat';   $note = 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.'; }
  elseif ($scorePct >= 75) { $label = 'Sedang';         $note = 'Ada tanda beban psikologis ringan—coba atur tidur, olahraga, dan kelola beban.'; }
  elseif ($scorePct >= 50) { $label = 'Stres';          $note = 'Stres terasa bermakna. Latih relaksasi/napas dalam, kurangi pemicu, dan minta dukungan.'; }
  else                     { $label = 'Depresi Berat';  $note = 'Pertimbangkan berbicara dengan konselor/psikolog. Bila ada pikiran menyakiti diri, segera hubungi layanan darurat.'; }

  // Simpan
  $ins = $mysqli->prepare("
    INSERT INTO quiz_attempts (pengguna_id, category, score, label, notes)
    VALUES (?,?,?,?,?)
  ");
  $ins->bind_param('issss', $uid, $cat, $scorePct, $label, $note);
  $ok = $ins->execute();
  $attemptId = (int)$ins->insert_id;
  $ins->close();

  if ($ok && $attemptId > 0) {
    header('Location: quiz_result.php?attempt=' . $attemptId, true, 303); exit;
  }
  header('Location: home.php?err=save'); exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Main Kuis • <?= h($QUIZ_NAME) ?></title>
  <link rel="stylesheet" href="css/admin.css">
  <style>
    .panel{max-width:760px;margin:24px auto;padding:16px;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6)}
    .title{font-weight:700;margin:0 0 8px}
    .muted{color:#6b7280}
    .qtext{font-weight:600;margin:12px 0}
    .opts label{display:block;padding:10px 12px;border:1px solid #f1b9cd;border-radius:12px;margin:8px 0}
    .opts input{margin-right:8px}
    .progress{height:8px;background:#fde2ea;border-radius:999px;overflow:hidden;margin:8px 0 16px}
    .bar{height:100%;width:4%;background:#f59ab5}
    .center{display:flex;gap:8px;align-items:center}
    .btn[disabled]{opacity:.5;cursor:not-allowed}
    /* gambar rapi */
    .qimg{margin:8px 0 12px;padding:8px;border-radius:16px;background:#fff6f9;box-shadow: inset 0 0 0 1px #f1b9cd33}
    .qimg img{display:block;max-width:100%;height:auto;max-height:clamp(180px,28vw,320px);object-fit:contain;margin:0 auto;border-radius:12px}
  </style>
</head>
<body>
  <div class="panel">
    <h2 class="title"><?= h($QUIZ_NAME) ?></h2>
    <div class="muted">Tes psikologi — tidak ada jawaban benar/salah. Pilih yang paling menggambarkan dirimu.</div>

    <div class="progress"><div id="bar" class="bar"></div></div>

    <div id="qwrap"><!-- pertanyaan by JS --></div>

    <div class="center" style="margin-top:12px">
      <button id="prev" class="btn ghost" type="button">Sebelumnya</button>
      <button id="next" class="btn" type="button">Berikutnya</button>
      <button id="done" class="btn" type="button" hidden>Selesai</button>
      <a href="home.php" class="btn ghost">Kembali</a>
    </div>
  </div>

  <!-- Form tersembunyi untuk submit akhir -->
  <form id="finishForm" method="post" action="play_quiz.php" style="display:none">
    <input type="hidden" name="action" value="finish">
    <input type="hidden" name="cat" value="<?= h($cat) ?>">
    <input type="hidden" name="answers" id="answersField">
  </form>

  <script>
  const DATA = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>;
  const N = DATA.length;

  const bar  = document.getElementById('bar');
  const wrap = document.getElementById('qwrap');
  const prev = document.getElementById('prev');
  const next = document.getElementById('next');
  const done = document.getElementById('done');

  let idx = 0;
  // Simpan {id, v}; v = index opsi yang dipilih (1..N), opsi-1 adalah nilai TERTINGGI
  let answers = DATA.map(q => ({ id: q.id, v: null }));

  function render(){
    if (N === 0) {
      wrap.innerHTML = '<div class="muted">Belum ada pertanyaan aktif.</div>';
      prev.disabled = next.disabled = done.disabled = true;
      return;
    }
    const q = DATA[idx];
    const labels = (q.opts && q.opts.length >= 2) ? q.opts : ['Ya','Tidak']; // minimal 2
    const img = q.img ? `<div class="qimg"><img src="${q.img}" alt="Gambar pertanyaan" loading="lazy"></div>` : '';

    wrap.innerHTML = `
      <div class="muted">Pertanyaan</div>
      ${img}
      <div class="qtext">${idx+1}. ${q.q}</div>
      <div class="opts">
        ${labels.map((t,i)=>`
          <label><input type="radio" name="opt" value="${i+1}"> ${t}</label>
        `).join('')}
      </div>
    `;

    // restore jawaban
    const v = answers[idx].v;
    if (v != null) {
      const el = wrap.querySelector(`input[value="${v}"]`);
      if (el) el.checked = true;
    }

    // progress & nav
    bar.style.width = (N === 1 ? 100 : Math.max(4, (idx / (N - 1)) * 100)) + '%';
    prev.disabled = (idx === 0);
    next.hidden   = (idx === N - 1);
    done.hidden   = (idx !== N - 1);
  }

  function readSel(){ const c = wrap.querySelector('input[name="opt"]:checked'); return c ? parseInt(c.value, 10) : null; }

  prev.onclick = () => { idx = Math.max(0, idx - 1); render(); };
  next.onclick = () => {
    const v = readSel(); if (v == null) return;
    answers[idx].v = v;
    idx = Math.min(N - 1, idx + 1);
    render();
  };
  done.onclick = () => {
    const v = readSel(); if (v == null) return;
    answers[idx].v = v;
    if (answers.some(a => a.v == null)) return; // jangan submit jika belum lengkap
    document.getElementById('answersField').value = JSON.stringify(answers);
    document.getElementById('finishForm').submit();
  };

  render();
  </script>
</body>
</html>
