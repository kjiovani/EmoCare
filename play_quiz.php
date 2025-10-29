<?php
// play_quiz.php
require_once __DIR__ . '/backend/config.php';
require_login();

/* ==============================
   Konfigurasi label kategori
================================ */
$CAT_LABEL = [
  'self_esteem'    => 'Self-Esteem',
  'social_anxiety' => 'Kecemasan Sosial',
];

/* ==============================
   Ambil kategori yang diminta
================================ */
$cat = $_GET['cat'] ?? $_POST['cat'] ?? 'self_esteem';
if (!isset($CAT_LABEL[$cat])) {
  $cat = 'self_esteem';
}

/* ==============================
   Ambil pertanyaan aktif + opsi
   (maks 5 pertanyaan terbaru)
================================ */
$qs = [];
$ids = [];

$stmt = $mysqli->prepare("
  SELECT q.id, q.question_text
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
  $qs[] = $row;
  $ids[] = (int)$row['id'];
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
    if (!isset($optmap[$qid])) $optmap[$qid] = [];
    $optmap[$qid][] = $o['option_text'];
  }
  $st->close();
}

/* ==============================
   Payload untuk client
================================ */
$payload = [];
foreach ($qs as $q) {
  $opts = $optmap[(int)$q['id']] ?? [];
  if (count($opts) < 2) { // fallback aman
    $opts = ['Tidak Pernah', 'Jarang', 'Sering', 'Sangat Sering'];
  }
  $payload[] = [
    'id'   => (int)$q['id'],
    'q'    => $q['question_text'],
    'opts' => array_values($opts),
  ];
}

/* ==============================
   Submit akhir: hitung & simpan,
   lalu redirect ke halaman hasil
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'finish')) {
  $uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);

  // Jawaban dari client: [{id: question_id, v: 1..4}, ...]
  $answers = json_decode($_POST['answers'] ?? '[]', true);
  if (!is_array($answers) || empty($answers)) {
    header('Location: play_quiz.php?cat=' . urlencode($cat));
    exit;
  }

  // Ambil set pertanyaan aktif (validasi agar tidak ada manipulasi)
  $validIds = [];
  $st = $mysqli->prepare("
    SELECT id
    FROM quiz_questions
    WHERE category=? AND is_active=1
  ");
  $st->bind_param('s', $cat);
  $st->execute();
  $rr = $st->get_result();
  while ($r = $rr->fetch_assoc()) $validIds[(int)$r['id']] = true;
  $st->close();

  // Hitung skor (map 1..4 -> 0..100 linear)
  $count    = 0;
  $sumPct   = 0.0;
  foreach ($answers as $a) {
    $qid = (int)($a['id'] ?? 0);
    $v   = (int)($a['v'] ?? 0);
    if (!isset($validIds[$qid])) continue;     // abaikan soal tidak valid
    if ($v < 1 || $v > 4) continue;            // jawaban tidak valid

    // 1..4 -> 0, 33.33, 66.67, 100
    $pct = (($v - 1) / 3) * 100.0;
    $sumPct += $pct;
    $count++;
  }

  if ($count <= 0) {
    header('Location: play_quiz.php?cat=' . urlencode($cat));
    exit;
  }

  $scorePct = round($sumPct / $count, 2);

  // Interpretasi sesuai logika lama
  if     ($scorePct >= 85) { $label = 'Mental Sehat';   $note = 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.'; }
  elseif ($scorePct >= 75) { $label = 'Sedang';         $note = 'Ada tanda beban psikologis ringan—coba atur tidur, olahraga, dan kelola beban.'; }
  elseif ($scorePct >= 50) { $label = 'Stres';          $note = 'Stres terasa bermakna. Latih relaksasi/napas dalam, kurangi pemicu, dan minta dukungan.'; }
  else                     { $label = 'Depresi Berat';  $note = 'Pertimbangkan berbicara dengan konselor/psikolog. Bila ada pikiran menyakiti diri, segera hubungi layanan darurat.'; }

  // Simpan ke quiz_attempts
  $ins = $mysqli->prepare("
    INSERT INTO quiz_attempts (pengguna_id, category, score, label, notes)
    VALUES (?,?,?,?,?)
  ");
  $ins->bind_param('issss', $uid, $cat, $scorePct, $label, $note);
  $ok = $ins->execute();
  $attemptId = (int)$ins->insert_id;
  $ins->close();

  // (Opsional) simpan detail jawaban ke tabel quiz_attempt_answers bila kamu punya.
  // Lewati saja jika tabelnya tidak ada.

  if ($ok && $attemptId > 0) {
    header('Location: quiz_result.php?attempt=' . $attemptId, true, 303);
    exit;
  }
  // fallback jika gagal simpan
  header('Location: home.php?err=save');
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Main Kuis • <?= htmlspecialchars($CAT_LABEL[$cat]) ?></title>
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
  </style>
</head>
<body>
  <div class="panel">
    <h2 class="title"><?= htmlspecialchars($CAT_LABEL[$cat]) ?></h2>
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
    <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
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
    // Simpan dalam bentuk {id, v} → v = 1..4
    let answers = DATA.map(q => ({ id: q.id, v: null }));

    function render(){
      if (N === 0) {
        wrap.innerHTML = '<div class="muted">Belum ada pertanyaan aktif.</div>';
        prev.disabled = next.disabled = done.disabled = true;
        return;
      }
      const q = DATA[idx];
      const labels = (q.opts && q.opts.length) ? q.opts : ['Tidak Pernah','Jarang','Sering','Sangat Sering'];
      wrap.innerHTML = `
        <div class="muted">Pertanyaan</div>
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
        const el = wrap.querySelector(input[value="\${v}"]\);
        if (el) el.checked = true;
      }
      // progress & nav
      bar.style.width = (N === 1 ? 100 : Math.max(4, (idx / (N - 1)) * 100)) + '%';
      prev.disabled = (idx === 0);
      next.hidden   = (idx === N - 1);
      done.hidden   = (idx !== N - 1);
    }

    function readSel(){
      const c = wrap.querySelector('input[name="opt"]:checked');
      return c ? parseInt(c.value, 10) : null;
    }

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
