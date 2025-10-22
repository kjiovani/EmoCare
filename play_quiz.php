<?php
require_once __DIR__ . '/backend/config.php';
require_login();

$CAT_LABEL = [
  'self_esteem' => 'Self-Esteem',
  'social_anxiety' => 'Kecemasan Sosial'
];
$cat = $_GET['cat'] ?? 'self_esteem';
if (!isset($CAT_LABEL[$cat]))
  $cat = 'self_esteem';

$qs = [];
// ambil 5 pertanyaan aktif terbaru di kategori ini
$stmt = $mysqli->prepare("
  SELECT q.id, q.question_text
  FROM quiz_questions q
  WHERE q.category=? AND q.is_active=1
  ORDER BY q.id DESC
  LIMIT 5
");
$stmt->bind_param('s', $cat);
$stmt->execute();
$res = $stmt->get_result();
$ids = [];
while ($row = $res->fetch_assoc()) {
  $qs[] = $row;
  $ids[] = (int) $row['id'];
}
$stmt->close();

$optmap = [];
if ($ids) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sql = "SELECT question_id, option_text FROM quiz_options WHERE question_id IN ($in) ORDER BY id";
  $st = $mysqli->prepare($sql);
  $st->bind_param($types, ...$ids);
  $st->execute();
  $rr = $st->get_result();
  while ($o = $rr->fetch_assoc()) {
    $qid = (int) $o['question_id'];
    $optmap[$qid][] = $o['option_text'];
  }
  $st->close();
}

// siapkan payload JS
$payload = [];
foreach ($qs as $q) {
  $opts = $optmap[(int) $q['id']] ?? [];
  // fallback aman 4 level jika admin lupa isi
  if (count($opts) < 2)
    $opts = ['Tidak Pernah', 'Jarang', 'Sering', 'Sangat Sering'];
  $payload[] = ['q' => $q['question_text'], 'opts' => array_values($opts)];
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
    .panel {
      max-width: 760px;
      margin: 24px auto;
      padding: 16px;
      border-radius: 18px;
      background: #fff;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .05), inset 0 1px 0 rgba(255, 255, 255, .6);
    }

    .title {
      font-weight: 700;
      margin: 0 0 8px;
    }

    .muted {
      color: #6b7280
    }

    .qtext {
      font-weight: 600;
      margin: 12px 0
    }

    .opts label {
      display: block;
      padding: 10px 12px;
      border: 1px solid #f1b9cd;
      border-radius: 12px;
      margin: 8px 0
    }

    .opts input {
      margin-right: 8px
    }

    .progress {
      height: 8px;
      background: #fde2ea;
      border-radius: 999px;
      overflow: hidden;
      margin: 8px 0 16px
    }

    .bar {
      height: 100%;
      width: 4%;
      background: #f59ab5
    }

    .center {
      display: flex;
      gap: 8px;
      align-items: center
    }

    .result {
      background: linear-gradient(180deg, #fff0f6, #fff);
      border: 1px solid #f6c3d3;
      border-radius: 16px;
      padding: 14px;
      margin-top: 12px
    }

    .score {
      font-size: 28px;
      font-weight: 800
    }

    .tag {
      padding: 4px 10px;
      border: 1px solid #f1b9cd;
      border-radius: 999px;
      font-weight: 600
    }
  </style>
</head>

<body>
  <div class="panel">
    <h2 class="title"><?= htmlspecialchars($CAT_LABEL[$cat]) ?></h2>
    <div class="muted">Tes psikologi — tidak ada jawaban benar/salah. Pilih yang paling menggambarkan dirimu.</div>

    <div class="progress">
      <div id="bar" class="bar"></div>
    </div>

    <div id="qwrap">
      <!-- konten pertanyaan diisi JS -->
    </div>

    <div class="center" style="margin-top:12px">
      <button id="prev" class="btn ghost" type="button">Sebelumnya</button>
      <button id="next" class="btn" type="button">Berikutnya</button>
      <button id="done" class="btn" type="button" hidden>Selesai</button>
      <a href="home.php" class="btn ghost">Kembali</a>
    </div>

    <div id="result" class="result" hidden>
      <div><span class="score"><span id="pct">0</span>%</span></div>
      <div style="margin-top:6px"><span id="label" class="tag">-</span></div>
      <div id="note" class="muted" style="margin-top:8px"></div>
    </div>
  </div>

  <script>
    const DATA = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>;
    const N = DATA.length;
    const bar = document.getElementById('bar');
    const wrap = document.getElementById('qwrap');
    const prev = document.getElementById('prev');
    const next = document.getElementById('next');
    const done = document.getElementById('done');
    const resBox = document.getElementById('result');
    const pctEl = document.getElementById('pct');
    const lblEl = document.getElementById('label');
    const noteEl = document.getElementById('note');

    let idx = 0;
    let answers = new Array(N).fill(null); // 1..4

    function render() {
      if (N === 0) {
        wrap.innerHTML = '<div class="muted">Belum ada pertanyaan aktif.</div>';
        prev.disabled = next.disabled = done.disabled = true;
        return;
      }
      const q = DATA[idx];
      const labels = q.opts.length ? q.opts : ['Tidak Pernah', 'Jarang', 'Sering', 'Sangat Sering'];
      const opts = labels.map((t, i) => `
    <label><input type="radio" name="opt" value="${i + 1}">${t}</label>
  `).join('');
      wrap.innerHTML = `
    <div class="muted">Pertanyaan</div>
    <div class="qtext">${idx + 1}. ${q.q}</div>
    <div class="opts">${opts}</div>
  `;
      // restore
      if (answers[idx] != null) {
        const el = wrap.querySelector(`input[value="${answers[idx]}"]`); if (el) el.checked = true;
      }
      // progress
      bar.style.width = Math.max(4, (idx / N) * 100) + '%';
      // nav
      prev.disabled = (idx === 0);
      next.hidden = (idx === N - 1);
      done.hidden = (idx !== N - 1);
      resBox.hidden = true;
    }

    function readSel() {
      const c = wrap.querySelector('input[name="opt"]:checked');
      return c ? parseInt(c.value, 10) : null;
    }

    function calcPercent() {
      // map 1..4 -> 0, 33, 66, 100 lalu rata-rata
      let sum = 0;
      for (const v of answers) {
        const x = (v - 1) * (100 / 3); // 0..100
        sum += x;
      }
      return Math.round(sum / answers.length);
    }

    function interpret(p) {
      // 100-85 sehat; 85-75 sedang; 75-50 stres; 50-0 depresi berat
      if (p >= 85) return ['Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.'];
      if (p >= 75) return ['Sedang', 'Ada tanda beban psikologis ringan—coba atur tidur, olahraga, dan kelola beban.'];
      if (p >= 50) return ['Stres', 'Stres terasa bermakna. Latih relaksasi/napas dalam, kurangi pemicu, dan minta dukungan.'];
      return ['Depresi Berat', 'Pertimbangkan berbicara dengan konselor/psikolog. Bila ada pikiran menyakiti diri, segera hubungi layanan darurat.'];
    }

    prev.onclick = () => { idx = Math.max(0, idx - 1); render(); };
    next.onclick = () => {
      const v = readSel(); if (v == null) return; // no alert benar/salah
      answers[idx] = v; idx++; render();
    };
    done.onclick = () => {
      const v = readSel(); if (v == null) return;
      answers[idx] = v;
      if (answers.some(x => x == null)) return; // masih ada yang kosong—diamkan saja
      const p = calcPercent();
      const [lbl, note] = interpret(p);
      pctEl.textContent = p;
      lblEl.textContent = lbl;
      noteEl.textContent = note;
      resBox.hidden = false;
      bar.style.width = '100%';
    };

    render();
  </script>
</body>

</html>