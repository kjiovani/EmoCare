<?php
// /public/quiz_result.php — versi kompatibel (tanpa fitur PHP modern yang riskan)
require_once __DIR__ . '/backend/config.php';
require_login();

$uid = isset($_SESSION['user']['pengguna_id']) ? (int) $_SESSION['user']['pengguna_id'] : 0;
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';

/* -------- helper cek kolom (tanpa type-hint) -------- */
function col_exists($db, $table, $col)
{
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  if (!$res)
    return false;
  $ok = ($res->num_rows > 0);
  $res->close();
  return $ok;
}
function pick_col($db, $table, $cands)
{
  foreach ($cands as $c) {
    if (col_exists($db, $table, $c))
      return $c;
  }
  return null;
}

/* -------- mapping kolom fleksibel -------- */
$colCat = pick_col($mysqli, 'quiz_attempts', array('category', 'quiz_slug', 'quiz', 'cat'));
$colPct = pick_col($mysqli, 'quiz_attempts', array('percent', 'score_pct', 'pct', 'percentage'));
$colRaw = pick_col($mysqli, 'quiz_attempts', array('correct_count', 'correct', 'score_raw', 'score', 'raw_score', 'benar'));
$colTot = pick_col($mysqli, 'quiz_attempts', array('total_count', 'total', 'total_questions', 'question_count', 'max_score', 'jumlah_soal'));
$colLabel = pick_col($mysqli, 'quiz_attempts', array('label', 'status', 'level', 'grade'));
$colFin = pick_col($mysqli, 'quiz_attempts', array('finished_at', 'completed_at', 'submitted_at', 'created_at', 'updated_at', 'end_time', 'ended_at', 'date'));
$colIntr = pick_col($mysqli, 'quiz_attempts', array('interpretation', 'note', 'remark', 'insight', 'description', 'hasil_text'));

/* -------- build SELECT alias manual -------- */
$SEL_CAT = $colCat ? "qa.`{$colCat}` AS category" : "NULL AS category";
$SEL_PCT = $colPct ? "qa.`{$colPct}` AS score_pct" : "NULL AS score_pct";
$SEL_RAW = $colRaw ? "qa.`{$colRaw}` AS score_raw" : "NULL AS score_raw";
$SEL_TOT = $colTot ? "qa.`{$colTot}` AS total_count" : "NULL AS total_count";
$SEL_LBL = $colLabel ? "qa.`{$colLabel}` AS status_label" : "NULL AS status_label";
$SEL_FIN = $colFin ? "qa.`{$colFin}` AS finished_at" : "NULL AS finished_at";
$SEL_INT = $colIntr ? "qa.`{$colIntr}` AS interpretation" : "NULL AS interpretation";

/* -------- query attempt: by id / by cat (latest) -------- */
if ($id > 0) {
  $sql = "
    SELECT qa.id, qa.pengguna_id,
           {$SEL_CAT}, {$SEL_PCT}, {$SEL_RAW}, {$SEL_TOT}, {$SEL_LBL}, {$SEL_FIN}, {$SEL_INT},
           COALESCE(l.name, " . ($colCat ? "qa.`{$colCat}`" : "NULL") . ") AS quiz_name,
           COALESCE(l.icon,'✨') AS icon
    FROM quiz_attempts qa
    LEFT JOIN quiz_list l ON " . ($colCat ? "l.slug = qa.`{$colCat}`" : "1=0") . "
    WHERE qa.id=? AND qa.pengguna_id=?
    LIMIT 1";
  $st = $mysqli->prepare($sql);
  $st->bind_param('ii', $id, $uid);
} elseif ($cat !== '') {
  if ($colCat) {
    $sql = "
      SELECT qa.id, qa.pengguna_id,
             {$SEL_CAT}, {$SEL_PCT}, {$SEL_RAW}, {$SEL_TOT}, {$SEL_LBL}, {$SEL_FIN}, {$SEL_INT},
             COALESCE(l.name, qa.`{$colCat}`) AS quiz_name,
             COALESCE(l.icon,'✨') AS icon
      FROM quiz_attempts qa
      LEFT JOIN quiz_list l ON l.slug = qa.`{$colCat}`
      WHERE qa.`{$colCat}`=? AND qa.pengguna_id=?
      ORDER BY " . ($colFin ? "qa.`{$colFin}` DESC, " : "") . "qa.id DESC
      LIMIT 1";
    $st = $mysqli->prepare($sql);
    $st->bind_param('si', $cat, $uid);
  } else {
    // fallback: latest attempt by user (kalau tidak ada kolom kategori)
    $sql = "
      SELECT qa.id, qa.pengguna_id,
             {$SEL_CAT}, {$SEL_PCT}, {$SEL_RAW}, {$SEL_TOT}, {$SEL_LBL}, {$SEL_FIN}, {$SEL_INT},
             NULL AS quiz_name,
             '✨' AS icon
      FROM quiz_attempts qa
      WHERE qa.pengguna_id=?
      ORDER BY " . ($colFin ? "qa.`{$colFin}` DESC, " : "") . "qa.id DESC
      LIMIT 1";
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $uid);
  }
} else {
  header('Location: quiz_history.php');
  exit;
}

$st->execute();
$res = $st->get_result();
$data = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$data) {
  header('Location: quiz_history.php');
  exit;
}

/* -------- normalisasi nilai (robust) -------- */
$correct = isset($data['score_raw']) && $data['score_raw'] !== null ? (float) $data['score_raw'] : null;
$total = isset($data['total_count']) && $data['total_count'] !== null ? (float) $data['total_count'] : null;
$percent = isset($data['score_pct']) && $data['score_pct'] !== null ? (float) $data['score_pct'] : null;

$hasCorrect = ($correct !== null);
$hasTotal = ($total !== null && $total > 0);
$hasPctCol = ($percent !== null);

/* Jika kolom percent tidak ada:
   - hitung dari correct/total bila total>0
   - atau, bila 'score' (correct) berada di rentang 0..100 dan total kosong/0,
     perlakukan 'score' sebagai persentase. */
if (!$hasPctCol) {
  if ($hasTotal && $hasCorrect) {
    $percent = round(($correct / $total) * 100);
  } else if ($hasCorrect && !$hasTotal && $correct >= 0 && $correct <= 100) {
    $percent = (float) $correct; // 'score' ternyata persentase
  } else {
    $percent = 0;
  }
}

/* Default & penjagaan batas */
if ($correct === null)
  $correct = 0;
if ($total === null)
  $total = 0;
if ($percent === null)
  $percent = 0;
if ($percent < 0)
  $percent = 0;
if ($percent > 100)
  $percent = 100;

$pct = (int) round($percent);
$pair = $hasTotal ? ((int) $correct) . '/' . ((int) $total) : '—';


if ($percent === null && $correct !== null && $total !== null && $total > 0) {
  $percent = round(($correct / $total) * 100);
}
if ($correct === null)
  $correct = 0;
if ($total === null)
  $total = 0;
if ($percent === null)
  $percent = 0;
if ($percent < 0)
  $percent = 0;
if ($percent > 100)
  $percent = 100;

$quizName = isset($data['quiz_name']) && $data['quiz_name'] !== '' ? $data['quiz_name']
  : (isset($data['category']) ? $data['category'] : 'Kuis');
$quizSlug = isset($data['category']) ? $data['category'] : '';
$icon = isset($data['icon']) && $data['icon'] !== '' ? $data['icon'] : '✨';
$finishedAt = !empty($data['finished_at']) ? date('Y-m-d H:i:s', strtotime($data['finished_at'])) : '-';
$interp = isset($data['interpretation']) ? trim($data['interpretation']) : '';

$statusLabel = isset($data['status_label']) ? trim($data['status_label']) : '';
if ($statusLabel === '') {
  if ($percent >= 70)
    $statusLabel = 'Tinggi';
  elseif ($percent >= 40)
    $statusLabel = 'Sedang';
  else
    $statusLabel = 'Rendah';
}
if ($interp === '') {
  if ($percent >= 70)
    $interp = 'Skor tinggi. Pertahankan kebiasaan baik.';
  elseif ($percent >= 40)
    $interp = 'Skor sedang. Jaga rutinitas sehat & evaluasi pemicu.';
  else
    $interp = 'Coba luangkan waktu untuk self-care: tidur cukup, kurangi screen time, dan journaling.';
}

$pct = (int) round($percent);
$pair = ((int) $correct) . '/' . ((int) $total);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Hasil: <?= htmlspecialchars($quizName) ?> • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <style>
    body {
      background: linear-gradient(180deg, #fff, #faf5ff)
    }

    .wrap {
      max-width: 980px;
      margin: 20px auto;
      padding: 0 14px
    }

    .card {
      border: 1px solid rgba(0, 0, 0, .06);
      background: #fff;
      border-radius: 22px;
      padding: 18px;
      box-shadow: 0 14px 36px rgba(0, 0, 0, .06)
    }

    .title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 8px
    }

    .ico {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: linear-gradient(180deg, #ffe7f2, #fff);
      border: 1px solid #f1c7de;
      font-size: 22px
    }

    .title h2 {
      margin: 0;
      font-weight: 900
    }

    .grid {
      display: grid;
      grid-template-columns: 360px 1fr 1fr;
      gap: 14px
    }

    .k {
      font-size: 13px;
      color: #6b7280
    }

    .box {
      border: 1px solid #f1f5f9;
      border-radius: 16px;
      padding: 14px;
      background: #fff
    }

    .btn {
      background: #f472b6;
      color: #fff;
      border: 0;
      border-radius: 12px;
      padding: 9px 12px;
      font-weight: 800
    }

    .btn.ghost {
      background: #fff;
      color: #be185d;
      border: 1px solid #f9a8d4
    }

    .ring {
      width: 170px;
      height: 170px;
      border-radius: 50%;
      background: conic-gradient(#f472b6
          <?= $pct ?>
          %, #e5e7eb 0);
      display: grid;
      place-items: center;
      margin: auto
    }

    .ring .in {
      width: 132px;
      height: 132px;
      border-radius: 50%;
      background: #fff;
      display: grid;
      place-items: center;
      border: 6px solid #f3f4f6
    }

    .perc {
      font-size: 32px;
      font-weight: 900;
      color: #0f172a
    }

    .pill {
      display: inline-flex;
      gap: 6px;
      align-items: center;
      border: 1px solid #e5e7eb;
      border-radius: 999px;
      padding: 4px 10px;
      font-weight: 800
    }

    .foot {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 14px
    }

    @media print {
      body * {
        visibility: hidden !important
      }

      #printArea,
      #printArea * {
        visibility: visible !important
      }

      #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
        border: 0
      }

      .no-print {
        display: none !important
      }
    }
  </style>
</head>

<body class="ec-body">
  <header class="ec-nav">
    <div class="ec-nav-inner">
      <div class="ec-brand"><span class="ec-brand-name" style="font-weight:600;color:#6b7280">EmoCare</span></div>
      <nav class="ec-nav-links">
        <a href="home.php#quiz-cards">Kuis</a>
        <a href="quiz_history.php">Riwayat</a>
      </nav>
      <form action="backend/auth_logout.php" method="post" style="margin:0"><button
          class="ec-btn-outline no-print">Keluar</button></form>
    </div>
  </header>

  <main class="wrap" id="printArea">
    <section class="card">
      <div class="title">
        <div class="ico"><?= htmlspecialchars($icon) ?></div>
        <h2>Hasil: <?= htmlspecialchars($quizName) ?></h2>
      </div>

      <div class="grid">
        <div class="box" style="display:grid;place-items:center;gap:10px">
          <div class="ring">
            <div class="in">
              <div class="perc"><?= (int) $pct ?>%</div>
            </div>
          </div>
          <div class="k">
            Skor • <strong><?= (int) $pct ?>%</strong>
            <?php if ($pair !== '—'): ?> • <?= htmlspecialchars($pair) ?><?php endif; ?>
          </div>

          <div class="pill">Status: <?= htmlspecialchars($statusLabel) ?></div>
        </div>

        <div class="box">
          <div class="k">Tanggal Selesai</div>
          <div style="font-weight:900;margin-top:4px"><?= htmlspecialchars($finishedAt) ?></div>
        </div>

        <div class="box">
          <div class="k">Interpretasi</div>
          <div style="margin-top:6px;line-height:1.5"><?= nl2br(htmlspecialchars($interp)) ?></div>
        </div>
      </div>

      <div class="foot no-print">
        <a class="btn" href="play_quiz.php?cat=<?= urlencode($quizSlug) ?>">Ulangi Kuis</a>
        <a class="btn ghost" href="quiz_history.php">Kembali ke Riwayat</a>
        <button class="btn ghost" onclick="window.print()">Unduh PDF</button>
      </div>
    </section>
  </main>
</body>

</html>