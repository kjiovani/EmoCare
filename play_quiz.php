<?php
// play_quiz.php
require_once __DIR__ . '/backend/config.php';
require_login();

/* ============================
   Ambil meta kuis dari slug
============================ */
$cat = $_GET['cat'] ?? $_POST['cat'] ?? '';
$cat = trim($cat);

$QUIZ = null;
$st = $mysqli->prepare("SELECT id, name, slug, COALESCE(description,'') AS description, is_active
                        FROM quiz_list WHERE slug = ? LIMIT 1");
$st->bind_param('s', $cat);
$st->execute();
$QUIZ = $st->get_result()->fetch_assoc();
$st->close();

if (!$QUIZ) {
  http_response_code(404);
  exit('Kuis tidak ditemukan.');
}
$QUIZ_NAME = $QUIZ['name'];
$CATEGORY  = $QUIZ['slug']; // dipakai untuk query pertanyaan

/* ============================
   Ambil pertanyaan aktif + opsi
============================ */
$qs  = [];
$ids = [];

$st = $mysqli->prepare("
  SELECT id, question_text, COALESCE(image_path,'') AS image_path
  FROM quiz_questions
  WHERE category = ? AND is_active = 1
  ORDER BY id DESC
  LIMIT 20
");
$st->bind_param('s', $CATEGORY);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $qs[]  = $row;
  $ids[] = (int)$row['id'];
}
$st->close();

$optmap = [];
if ($ids) {
  $in    = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sql   = "SELECT question_id, option_text
            FROM quiz_options
            WHERE question_id IN ($in)
            ORDER BY option_order ASC, id ASC";
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

/* payload ke klien */
$payload = [];
foreach ($qs as $q) {
  $opts = $optmap[(int)$q['id']] ?? [];
  if (count($opts) < 2) {
    $opts = ['Tidak Pernah', 'Jarang', 'Sering', 'Sangat Sering'];
  }
  $img = trim((string)$q['image_path']);
  $payload[] = [
    'id'   => (int)$q['id'],
    'q'    => $q['question_text'],
    'img'  => $img ? ('../' . $img) : null, // path relatif dari dokumen root
    'opts' => array_values($opts),
  ];
}

/* ============================
   Submit: hitung & simpan
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'finish')) {
  $uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);
  $catPost = trim($_POST['cat'] ?? '');

  // ambil kembali slug valid (hindari manipulasi)
  $chk = $mysqli->prepare("SELECT slug FROM quiz_list WHERE slug=? LIMIT 1");
  $chk->bind_param('s', $catPost);
  $chk->execute();
  $validSlug = $chk->get_result()->fetch_column();
  $chk->close();
  if (!$validSlug) {
    header('Location: home.php?err=quiz');
    exit;
  }

  $answers = json_decode($_POST['answers'] ?? '[]', true);
  if (!is_array($answers) || empty($answers)) {
    header('Location: play_quiz.php?cat=' . urlencode($catPost));
    exit;
  }

  // validasi id pertanyaan aktif
  $validIds = [];
  $st = $mysqli->prepare("SELECT id FROM quiz_questions WHERE category=? AND is_active=1");
  $st->bind_param('s', $catPost);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) $validIds[(int)$r['id']] = true;
  $st->close();

  $sumPct = 0.0; $count = 0;
  foreach ($answers as $a) {
    $qid = (int)($a['id'] ?? 0);
    $v   = (int)($a['v']  ?? 0);
    if (!isset($validIds[$qid])) continue;
    if ($v < 1 || $v > 4) continue;
    $sumPct += (($v - 1) / 3) * 100.0;
    $count++;
  }
  if ($count <= 0) {
    header('Location: play_quiz.php?cat=' . urlencode($catPost));
    exit;
  }
  $scorePct = round($sumPct / $count, 2);

  // interpretasi
  if     ($scorePct >= 85) { $label='Mental Sehat';  $note='Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.'; }
  elseif ($scorePct >= 75) { $label='Sedang';        $note='Ada tanda beban psikologis ringan—atur tidur, olahraga, dan kelola beban.'; }
  elseif ($scorePct >= 50) { $label='Stres';         $note='Stres bermakna. Latih relaksasi/napas dalam, kurangi pemicu, minta dukungan.'; }
  else                     { $label='Depresi Berat'; $note='Pertimbangkan konselor/psikolog. Bila ada pikiran menyakiti diri, cari bantuan darurat.'; }

  // simpan attempts
  $ins = $mysqli->prepare("INSERT INTO quiz_attempts (pengguna_id, category, score, label, notes)
                           VALUES (?,?,?,?,?)");
  // i s d s s  → score numeric lebih aman sebagai double/decimal
  $ins->bind_param('isdss', $uid, $catPost, $scorePct, $label, $note);
  $ok = $ins->execute();
  $attemptId = (int)$ins->insert_id;
  $ins->close();

  if ($ok && $attemptId > 0) {
    header('Location: quiz_result.php?attempt=' . $attemptId, true, 303);
    exit;
  }
  header('Location: home.php?err=save');
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Main Kuis • <?= htmlspecialchars($QUIZ_NAME) ?></title>
  <link rel="stylesheet" href="css/admin.css" />
  <style>
    .panel{max-width:760px;margin:24px auto;padding:16px;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6)}
    .title{font-weight:700;margin:0 0 8px}
    .muted{color:#6b7280}
    .qtext{font-weight:600;margin:12px 0}
    .qimg{margin:8px 0 10px}
    .qimg img{max-width:100%;border-radius:12px;border:1px solid #f1b9cd}
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
    <h2 class="title"><?= htmlspecialchars($QUIZ_NAME) ?></h2>
    <div class="muted">Tes psikologi — tidak ada jawaban benar/salah. Pilih yang paling menggambarkan dirimu.</div>

    <div class="progress"><div id="bar" class="bar"></div></div>
    <div id="qwrap"></div>

    <div class="center" style="margin-top:12px">
      <button id="prev" class="btn ghost" type="button">Sebelumnya</button>
      <button id="next" class="btn" type="button">Berikutnya</button>
      <button id="done" class="btn" type="button" hidden>Selesai</button>
      <a href="home.php" class="btn ghost">Kembali</a>
    </div>
  </div>

  <!-- form submit akhir -->
  <form id="finishForm" method="post" action="play_quiz.php" style="display:none">
    <input type="hidden" name="action" value="finish">
    <input type="hidden" name="cat" value="<?= htmlspecialchars($CATEGORY) ?>">
    <input type="hidden" name="answers" id="answersField">
  </form>

  <script>
    const DATA = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>;
    const N    = DATA.length;

    const bar  = document.getElementById('bar');
    const wrap = document.getElementById('qwrap');
    const prev = document.getElementById('prev');
    const next = document.getElementById('next');
    const done = document.getElementById('done');

    let idx = 0;
    // simpan jawaban dalam {id, v}
    let answers = DATA.map(q => ({ id: q.id, v: null }));

    function render(){
      if (N === 0){
        wrap.innerHTML = '<div class="muted">Belum ada pertanyaan aktif.</div>';
        prev.disabled = next.disabled = done.disabled = true;
        return;
      }
      const q = DATA[idx];
      const labels = (q.opts && q.opts.length) ? q.opts : ['Tidak Pernah','Jarang','Sering','Sangat Sering'];

      let imgHtml = '';
      if (q.img){
        imgHtml = `<div class="qimg"><img src="${q.img}" alt="Gambar pertanyaan"></div>`;
      }

      wrap.innerHTML = `
        <div class="muted">Pertanyaan</div>
        <div class="qtext">${idx+1}. ${q.q}</div>
        ${imgHtml}
        <div class="opts">
          ${labels.map((t,i)=>`
            <label><input type="radio" name="opt" value="${i+1}"> ${t}</label>
          `).join('')}
        </div>
      `;

      // restore pilihan
      const v = answers[idx].v;
      if (v != null){
        const el = wrap.querySelector('input[name="opt"][value="' + v + '"]');
        if (el) el.checked = true;
      }

      // progres & tombol
      bar.style.width = (N === 1 ? 100 : Math.max(4, (idx/(N-1))*100)) + '%';
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
      if (answers.some(a => a.v == null)) return;
      document.getElementById('answersField').value = JSON.stringify(answers);
      document.getElementById('finishForm').submit();
    };

    render();
  </script>
</body>
</html>
