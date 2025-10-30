<?php
require_once __DIR__ . '/backend/config.php';
require_login();

/* --- identitas & role --- */
$uid = (int) ($_SESSION['user']['pengguna_id'] ?? 0);
$nama = $_SESSION['user']['nama'] ?? 'Pengguna';

$stmt = $mysqli->prepare("SELECT role FROM pengguna WHERE pengguna_id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$role = $stmt->get_result()->fetch_column();
$stmt->close();
$isAdmin = ($role === 'admin');

/* ------------------------------------
   Kuis Psikologi (auto dari database)
------------------------------------- */
$quizzes = [];
$sql = "
  SELECT l.id, l.name, l.slug, l.icon,
         COALESCE(l.description,'') AS description,
         COALESCE((SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug),0) AS total_q,
         COALESCE((SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug AND q.is_active=1),0) AS active_q
  FROM quiz_list l
  WHERE l.is_active=1
  ORDER BY l.sort_order ASC, l.id ASC
";
if ($res = $mysqli->query($sql)) {
  $quizzes = $res->fetch_all(MYSQLI_ASSOC);
}

/* =========================================================
   Rekapan Bulanan Mood Tracker (tanpa ubah skema database)
   - Tabel: moodtracker (mood_id, tanggal, mood_level, catatan)
   - KPI: avg, total entri, hari aktif
   - Distribusi + Insight (mode, standar deviasi, komposisi)
========================================================= */
function ym_bounds(string $ym): array
{
  $start = $ym . '-01';
  $end = date('Y-m-t', strtotime($start));
  return [$start, $end];
}

function get_month_recap(mysqli $db, int $uid, string $ym): array
{
  [$start, $end] = ym_bounds($ym);

  // Agg inti + avg2 untuk standar deviasi
  $st = $db->prepare("
    SELECT COUNT(*) c,
           AVG(mood_level) a,
           MIN(mood_level) mi,
           MAX(mood_level) ma,
           AVG(mood_level*mood_level) a2
    FROM moodtracker
    WHERE pengguna_id=? AND tanggal BETWEEN ? AND ?
  ");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $agg = $st->get_result()->fetch_assoc() ?: ['c' => 0, 'a' => null, 'mi' => null, 'ma' => null, 'a2' => null];
  $st->close();

  // Hari aktif (distinct tanggal)
  $st = $db->prepare("SELECT COUNT(DISTINCT tanggal) d FROM moodtracker WHERE pengguna_id=? AND tanggal BETWEEN ? AND ?");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $days_active = (int) ($st->get_result()->fetch_column() ?? 0);
  $st->close();

  // Distribusi 1..5
  $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
  $st = $db->prepare("
    SELECT mood_level, COUNT(*) ct
    FROM moodtracker
    WHERE pengguna_id=? AND tanggal BETWEEN ? AND ?
    GROUP BY mood_level
  ");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) {
    $lvl = (int) $row['mood_level'];
    if ($lvl >= 1 && $lvl <= 5)
      $dist[$lvl] = (int) $row['ct'];
  }
  $st->close();

  // Mode (paling dominan) – tie-break: yang paling dekat rata-rata
  $mode = null;
  $modeCt = -1;
  $avgFloat = (float) ($agg['a'] ?? 0);
  foreach ($dist as $lvl => $ct) {
    if ($ct > $modeCt || ($ct === $modeCt && abs($lvl - $avgFloat) < abs(($mode ?? $lvl) - $avgFloat))) {
      $mode = $lvl;
      $modeCt = $ct;
    }
  }

  // Standar deviasi
  $sd = null;
  if (!is_null($agg['a2']) && !is_null($agg['a']) && (int) $agg['c'] > 1) {
    $sd = sqrt(max(0, (float) $agg['a2'] - ((float) $agg['a'] * (float) $agg['a'])));
    $sd = round($sd, 2);
  }

  return [
    'ym' => $ym,
    'label' => date('F Y', strtotime($start)),
    'start' => $start,
    'end' => $end,
    'total' => (int) $agg['c'],
    'avg' => is_null($agg['a']) ? null : round((float) $agg['a'], 2),
    'min' => is_null($agg['mi']) ? null : (int) $agg['mi'],
    'max' => is_null($agg['ma']) ? null : (int) $agg['ma'],
    'days_active' => $days_active,
    'days_total' => (int) date('t', strtotime($start)),
    'dist' => $dist,
    'mode' => $mode,
    'sd' => $sd,
  ];
}

/* Resolve bulan */
$ymParam = trim($_GET['month'] ?? '');
$ym = preg_match('/^\d{4}\-\d{2}$/', $ymParam) ? $ymParam : date('Y-m');
$recap = get_month_recap($mysqli, $uid, $ym);

/* Navigasi bulan */
$ymTime = strtotime($ym . '-01');
$prevYm = date('Y-m', strtotime('-1 month', $ymTime));
$nextYm = date('Y-m', strtotime('+1 month', $ymTime));
$nextIsFuture = (strtotime($nextYm . '-01') > strtotime(date('Y-m-01')));

/* --- Handlers Mood Tracker --- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create')) {
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($mood < 1 || $mood > 5) {
    $flash = 'Skala mood harus 1..5';
  } else {
    $stmt = $mysqli->prepare("INSERT INTO moodtracker(pengguna_id,tanggal,mood_level,catatan) VALUES(?,CURDATE(),?,?)");
    $stmt->bind_param('iis', $uid, $mood, $note);
    if ($stmt->execute()) {
      header('Location: home.php?saved=1');
      exit;
    } else {
      $flash = 'Gagal menyimpan: ' . $stmt->error;
    }
    $stmt->close();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete')) {
  $ids = array_values(array_filter(array_map('intval', $_POST['delete_ids'] ?? [])));
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM moodtracker WHERE pengguna_id=? AND mood_id IN ($in)";
    $stmt = $mysqli->prepare($sql);
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$uid], $ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    header('Location: home.php?deleted=1#top');
    exit;
  } else
    $flash = 'Pilih minimal satu baris untuk dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'edit')) {
  $id = (int) ($_POST['mood_id'] ?? 0);
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($id <= 0)
    $flash = 'Data tidak valid.';
  elseif ($mood < 1 || $mood > 5)
    $flash = 'Skala mood harus 1..5';
  else {
    $sql = "UPDATE moodtracker SET mood_level=?, catatan=? WHERE mood_id=? AND pengguna_id=?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('isii', $mood, $note, $id, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: home.php?updated=1#top');
    exit;
  }
}

/* --- ambil riwayat mood --- */
$items = [];
$s = trim($_GET['s'] ?? '');
$conds = ['pengguna_id = ?'];
$types = 'i';
$vals = [$uid];
if ($s !== '') {
  $conds[] = '(tanggal LIKE ? OR catatan LIKE ? OR CAST(mood_level AS CHAR) LIKE ?)';
  $types .= 'sss';
  $like = '%' . $s . '%';
  array_push($vals, $like, $like, $like);
}
$sql = "SELECT mood_id, tanggal, mood_level, catatan
        FROM moodtracker
        WHERE " . implode(' AND ', $conds) . "
        ORDER BY tanggal DESC, mood_id DESC";
$q = $mysqli->prepare($sql);
$q->bind_param($types, ...$vals);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc())
  $items[] = $row;
$q->close();

// ==== Statistik tambahan: streak & rata-rata keseluruhan ====
$streak = 0;
// ambil tanggal unik (maks 120 hari ke belakang)
$__st = $mysqli->prepare("SELECT DISTINCT tanggal FROM moodtracker WHERE pengguna_id=? AND tanggal <= CURDATE() ORDER BY tanggal DESC LIMIT 120");
$__st->bind_param('i', $uid);
$__st->execute();
$__rs = $__st->get_result();
$__dates = [];
while ($__row = $__rs->fetch_assoc()) {
  $__dates[$__row['tanggal']] = true;
}
$__st->close();
// hitung streak mundur dari hari ini
$__cur = new DateTimeImmutable('today');
while (isset($__dates[$__cur->format('Y-m-d')])) {
  $streak++;
  $__cur = $__cur->modify('-1 day');
}

// rata-rata keseluruhan (untuk tile)
$avgOverall = 0;
if ($items) {
  $sum = 0;
  foreach ($items as $it)
    $sum += (int) $it['mood_level'];
  $avgOverall = round($sum / count($items), 2);
}

/* ============================
   Kuis Selesai untuk Statistik
   - Menghitung DISTINCT kategori yang sudah selesai
   - Adaptif: cek beberapa nama tabel umum
============================ */
function table_exists(mysqli $db, string $name): bool
{
  $st = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name=?");
  $st->bind_param('s', $name);
  $st->execute();
  $ok = (bool) ($st->get_result()->fetch_column() ?? 0);
  $st->close();
  return $ok;
}

/* hitung kuis selesai (lifetime) */
function count_quiz_done(mysqli $db, int $uid): int
{
  // kandidat tabel & kondisi “selesai”
  $candidates = [
    // rekomendasi/standar
    ['quiz_result', 'finished_at IS NOT NULL', true],   // true => hitung DISTINCT category
    // alternatif nama skema
    ['quiz_results', 'finished_at IS NOT NULL', true],
    ['quiz_sessions', 'finished_at IS NOT NULL', true],
    ['quiz_attempts', '(status="finished" OR status="done" OR is_finished=1)', true],
  ];

  foreach ($candidates as [$tbl, $cond, $distinctCat]) {
    if (table_exists($db, $tbl)) {
      $sql = $distinctCat
        ? "SELECT COUNT(DISTINCT category) FROM `$tbl` WHERE pengguna_id=? AND ($cond)"
        : "SELECT COUNT(*) FROM `$tbl` WHERE pengguna_id=? AND ($cond)";
      $st = $db->prepare($sql);
      $st->bind_param('i', $uid);
      $st->execute();
      $n = (int) ($st->get_result()->fetch_column() ?? 0);
      $st->close();
      return $n;
    }
  }
  return 0;
}

$quizDone = count_quiz_done($mysqli, $uid);

/* Kalau mau “BULAN INI”, ganti dengan:
$ymNow = date('Y-m');
$quizDone = 0;
if (table_exists($mysqli,'quiz_result')) {
  $st = $mysqli->prepare("
    SELECT COUNT(DISTINCT category)
    FROM quiz_result
    WHERE pengguna_id=? AND DATE_FORMAT(finished_at,'%Y-%m')=?
  ");
  $st->bind_param('is', $uid, $ymNow);
  $st->execute();
  $quizDone = (int)($st->get_result()->fetch_column() ?? 0);
  $st->close();
}
*/


?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Home • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/mood.css" />
  <style>
    .bg-hero {
      background: linear-gradient(135deg, #ffe0ea 0%, #e7dcff 50%, #dfe9ff 100%)
    }

    .quiz-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px
    }

    .quiz-card {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: #fff;
      border: 1px solid var(--bd);
      border-radius: 18px;
      padding: 16px;
      box-shadow: var(--shadow)
    }

    .quiz-ico {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      flex: 0 0 48px;
      display: grid;
      place-items: center;
      font-size: 22px;
      background: linear-gradient(180deg, #ffe7f2, #fff);
      border: 1px solid var(--bd)
    }

    .quiz-name {
      font-weight: 900;
      color: #111;
      margin-bottom: 4px
    }

    .quiz-desc {
      color: #6b7280;
      line-height: 1.5;
      margin-bottom: 10px
    }

    .btn {
      background: #f472b6;
      color: #fff;
      border: 0;
      border-radius: 14px;
      padding: 10px 14px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 6px 14px rgba(236, 72, 153, .12)
    }

    .btn[disabled] {
      opacity: .55;
      cursor: not-allowed
    }

    .btn.ghost {
      background: #fff;
      color: #be185d;
      border: 1px solid #f9a8d4
    }

    .empty {
      padding: 18px;
      border: 1px dashed #f4cadd;
      border-radius: 14px;
      background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
      color: #6b7280;
      text-align: center;
      font-weight: 600
    }

    .role-user .qp-meta {
      display: none !important
    }

    /* ===== Rekapan: clean + fokus ===== */
    .recap-card {
      margin: 16px 0;
      padding: 20px 22px;
      border-radius: 22px;
      background: linear-gradient(180deg, rgba(255, 255, 255, .88), rgba(255, 255, 255, .72));
      border: 1px solid rgba(236, 72, 153, .12);
      box-shadow: 0 10px 24px rgba(99, 102, 241, .08);
      backdrop-filter: saturate(1.05) blur(8px);
    }

    .recap-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap
    }

    .recap-title {
      margin: 0;
      font-size: 19px;
      font-weight: 900;
      color: #0f172a
    }

    .pill-month {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #fff;
      border: 1px solid #f4cadd;
      font-weight: 800;
      color: #be185d
    }

    .recap-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap
    }

    .ec-btn {
      background: #f472b6;
      color: #fff;
      padding: 9px 12px;
      border-radius: 12px;
      border: 0;
      font-weight: 800
    }

    .ec-btn-outline {
      background: #fff;
      color: #be185d;
      border: 1px solid #f9a8d4;
      padding: 9px 12px;
      border-radius: 12px;
      font-weight: 800
    }

    .ec-btn[disabled] {
      opacity: .5;
      cursor: not-allowed
    }

    .kpis {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
      margin-top: 14px
    }

    .kpi {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: 16px;
      padding: 12px 14px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, .04)
    }

    .kpi .k {
      font-size: 12px;
      color: #6b7280
    }

    .kpi .v {
      font-weight: 900;
      font-size: 22px;
      line-height: 1.1;
      margin-top: 2px
    }

    /* Distribusi & insight */
    .dist-wrap {
      margin-top: 12px
    }

    .dist-head {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px
    }

    .legend {
      display: flex;
      gap: 6px;
      flex-wrap: wrap
    }

    .legend .chip {
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #6b7280
    }

    .dist-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 8px 0
    }

    .dist-row .idx {
      width: 24px;
      text-align: right;
      font-weight: 700;
      color: #374151
    }

    .dist-row .bar {
      flex: 1;
      height: 12px;
      background: #f1f5f9;
      border-radius: 999px;
      overflow: hidden
    }

    .dist-row .bar>span {
      display: block;
      height: 12px;
      background: linear-gradient(90deg, #f472b6, #6366f1)
    }

    .dist-row .val {
      width: 80px;
      text-align: right;
      color: #6b7280;
      font-weight: 600
    }

    .insight {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px
    }

    .insight .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #fff;
      border: 1px solid #e5e7eb;
      font-weight: 700
    }

    .pill.warn {
      border-color: #fde68a;
      background: #fffbeb;
      color: #92400e
    }

    .pill.good {
      border-color: #bbf7d0;
      background: #f0fdf4;
      color: #065f46
    }

    .pill.info {
      border-color: #c7d2fe;
      background: #eef2ff;
      color: #3730a3
    }

    .note {
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid #fde68a;
      background: #fffbeb;
      color: #92400e;
      font-size: 14px
    }

    .note.good {
      border-color: #bbf7d0;
      background: #f0fdf4;
      color: #065f46
    }

    .recap-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-top: 10px
    }

    .muted {
      color: #6b7280;
      font-size: 13px
    }

    .range {
      font-weight: 800;
      color: #334155
    }

    /* PRINT: hanya kartu rekapan yang dicetak */
    @media print {
      body * {
        visibility: hidden !important
      }

      #mood-recap,
      #mood-recap * {
        visibility: visible !important
      }

      #mood-recap {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 24px;
        box-shadow: none;
        border: 0
      }

      .no-print {
        display: none !important
      }
    }

    /* ===== Statistik & Progress (grid rapi 4 kolom) ===== */
    .ec-tiles {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      /* mobile default */
    }

    /* ≥640px: 2 kolom */
    @media (min-width:640px) {
      .ec-tiles {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    /* ≥900px: 3 kolom */
    @media (min-width:900px) {
      .ec-tiles {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    /* ≥1200px: 4 kolom (sejajar) */
    @media (min-width:1200px) {
      .ec-tiles {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .ec-tile {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .06);
      border-radius: 16px;
      padding: 16px 18px;
      box-shadow: 0 8px 30px rgba(15, 23, 42, .06);

      /* bikin tinggi konsisten & konten center */
      min-height: 110px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .ec-tile .k {
      font-size: 14px;
      color: #64748b
    }

    .ec-tile .v {
      font-size: 26px;
      font-weight: 900;
      margin-top: 6px;
      color: #0f172a
    }
  </style>
</head>

<body class="ec-body <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
  <header class="ec-nav">
    <div class="ec-nav-inner">
      <div class="ec-brand"><span class="ec-brand-name" style="font-weight:500;color:#6b7280;">EmoCare</span></div>
      <nav class="ec-nav-links">
        <a href="#top">Beranda</a>
        <a href="#features">Fitur</a>
        <a href="#stats">Statistik</a>
        <?php if ($isAdmin): ?><a href="admin/dashboard.php" class="btn ghost"
            style="margin-left:8px">Admin</a><?php endif; ?>
      </nav>
      <form action="backend/auth_logout.php" method="post" style="margin:0"><button
          class="ec-btn-outline">Keluar</button></form>
    </div>
  </header>

  <main id="top" class="ec-container">
    <!-- Greeting / Hero -->
    <section class="ec-card ec-hero" id="greetingCard">
      <div class="ec-hero-left">
        <div class="ec-hero-title">Selamat <span id="greetTimeWord">Pagi</span>, <span
            id="greetUsername"><?= htmlspecialchars($nama) ?></span>! 🙌🏻</div>
        <div class="ec-hero-sub">Gimana hari ini?</div>
      </div>
      <div class="ec-hero-right">
        <div class="ec-clock"><span id="greetClock"></span></div>
        <div class="ec-streak">🔥 <span id="greetStreak">0</span> hari beruntun</div>
      </div>
    </section>

    <!-- VIDEO QUOTES -->
    <section class="ec-card ec-video">
      <h3 class="ec-section-title">Tonton dulu Yuk😻</h3>
      <div class="ec-video-frame" id="video-frame">
        <iframe width="560" height="315" src="https://www.youtube.com/embed/WWloIAQpMcQ" title="Quotes" frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
      </div>
    </section>

    <!-- KUIS PSIKOLOGI -->
    <section class="ec-card" id="quiz-cards">
      <h2 class="ec-section-title">Kuis Psikologi</h2>
      <?php if (empty($quizzes)): ?>
        <div class="empty">Belum ada kuis aktif. Admin dapat menambahkannya di Kelola Kuis.</div>
      <?php else: ?>
        <div class="quiz-grid">
          <?php foreach ($quizzes as $q): ?>
            <article class="quiz-card">
              <div class="quiz-ico"><?= htmlspecialchars($q['icon'] ?: '✨') ?></div>
              <div class="quiz-body">
                <div class="quiz-name"><?= htmlspecialchars($q['name']) ?></div>
                <?php if (!empty($q['description'])): ?>
                  <div class="quiz-desc"><?= htmlspecialchars($q['description']) ?></div>
                <?php endif; ?>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                  <?php if ($isAdmin): ?><span class="pill-month"><?= (int) $q['active_q'] ?>/<?= (int) $q['total_q'] ?>
                      aktif</span><?php endif; ?>
                  <?php if ((int) $q['active_q'] > 0): ?>
                    <a class="btn" href="play_quiz.php?cat=<?= urlencode($q['slug']) ?>">Mulai Kuis</a>
                  <?php else: ?><button class="btn" disabled>Belum ada soal</button><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div style="margin-top:12px; display:flex; justify-content:flex-end;">
        <a class="btn ghost" href="quiz_history.php">Riwayat Hasil Kuis »</a>
      </div>
    </section>

    <!-- ===== REKAPAN BULANAN ===== -->
    <section class="ec-card recap-card" id="mood-recap" data-label="<?= htmlspecialchars($recap['label']) ?>">
      <div class="recap-header">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h2 class="recap-title">Rekapan Bulanan</h2>
          <span class="pill-month">📅 <?= htmlspecialchars($recap['label']) ?></span>
        </div>
        <div class="recap-actions">
          <a class="ec-btn-outline" href="home.php?month=<?= htmlspecialchars($prevYm) ?>">« Bulan Sebelumnya</a>
          <form id="monthForm" action="home.php" method="get" class="no-print"
            style="display:flex;gap:8px;align-items:center">
            <input id="monthSelect" type="month" name="month" value="<?= htmlspecialchars($ym) ?>"
              style="padding:8px;border:1px solid #ddd;border-radius:10px">
          </form>
          <a class="ec-btn-outline <?= $nextIsFuture ? 'disabled' : '' ?>"
            href="<?= $nextIsFuture ? '#' : ('home.php?month=' . htmlspecialchars($nextYm)) ?>"
            style="<?= $nextIsFuture ? 'pointer-events:none;opacity:.5;' : '' ?>">Bulan Berikutnya »</a>
          <button type="button" class="ec-btn-outline" onclick="window.print()">Unduh PDF</button>
        </div>
      </div>

      <?php if ($recap['total'] === 0): ?>
        <p class="muted" style="margin-top:10px">Belum ada data untuk
          <strong><?= htmlspecialchars($recap['label']) ?></strong>.
        </p>
      <?php else: ?>
        <!-- KPI ringkas -->
        <div class="kpis">
          <div class="kpi">
            <div class="k">Rata-rata</div>
            <div class="v"><?= $recap['avg'] === null ? '–' : number_format((float) $recap['avg'], 2) ?></div>
          </div>
          <div class="kpi">
            <div class="k">Entri</div>
            <div class="v"><?= (int) $recap['total'] ?></div>
          </div>
          <div class="kpi">
            <div class="k">Hari Aktif</div>
            <div class="v"><?= (int) $recap['days_active'] ?>/<?= (int) $recap['days_total'] ?></div>
          </div>
        </div>

        <!-- Distribusi + Insight -->
        <?php
        $MOOD_LABEL = [1 => 'Senang Banget', 2 => 'Senang', 3 => 'Biasa', 4 => 'Cemas', 5 => 'Stress'];
        $total = max(1, (int) $recap['total']);
        $pos = round((($recap['dist'][1] + $recap['dist'][2]) * 100) / $total);
        $net = round(($recap['dist'][3] * 100) / $total);
        $neg = round((($recap['dist'][4] + $recap['dist'][5]) * 100) / $total);
        ?>
        <div class="dist-wrap">
          <div class="dist-head">
            <div>Distribusi Mood</div>
            <div class="legend">
              <span class="chip">1 = Senang Banget</span>
              <span class="chip">2 = Senang</span>
              <span class="chip">3 = Biasa</span>
              <span class="chip">4 = Cemas</span>
              <span class="chip">5 = Stress</span>
            </div>
          </div>

          <?php for ($i = 1; $i <= 5; $i++):
            $cnt = (int) $recap['dist'][$i];
            $pct = round($cnt * 100 / $total);
            ?>
            <div class="dist-row">
              <div class="idx"><?= $i ?></div>
              <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
              <div class="val"><?= $cnt ?> (<?= $pct ?>%)</div>
            </div>
          <?php endfor; ?>

          <div class="insight">
            <span class="pill info">Dominan: <?= (int) $recap['mode'] ?> •
              <?= $MOOD_LABEL[(int) $recap['mode']] ?? '' ?></span>
            <?php if (!is_null($recap['sd'])): ?>
              <span class="pill <?= ($recap['sd'] >= 1.2 ? 'warn' : 'good') ?>">Variasi:
                <?= ($recap['sd'] >= 1.2 ? 'Fluktuatif' : 'Stabil') ?> (SD <?= number_format($recap['sd'], 2) ?>)</span>
            <?php endif; ?>
            <span class="pill">Komposisi: Positif <?= $pos ?>% • Netral <?= $net ?>% • Negatif <?= $neg ?>%</span>
          </div>

          <?php if ($recap['avg'] !== null && $recap['avg'] < 3): ?>
            <div class="note"><strong>Catatan:</strong> rata-rata cenderung rendah. Coba rutinkan self-care (tidur cukup,
              journaling 5 menit, jalan sore, batasi screen-time).</div>
          <?php elseif ($recap['avg'] !== null && $recap['avg'] >= 4): ?>
            <div class="note good"><strong>Nice!</strong> Mood konsisten baik. Pertahankan kebiasaan yang terasa membantu.
            </div>
          <?php endif; ?>
        </div>

        <!-- Periode & Range -->
        <div class="recap-foot">
          <div class="muted">Periode: <?= htmlspecialchars($recap['start']) ?> – <?= htmlspecialchars($recap['end']) ?>
          </div>
          <div class="range">Range: ↑ <?= (int) $recap['max'] ?> • ↓ <?= (int) $recap['min'] ?></div>
        </div>
      <?php endif; ?>
    </section>

    <!-- Mood Tracker -->
    <section id="mood-tracker" class="ec-mood-grid">
      <article class="card ec-mood-card">
        <div class="card-bar">
          <div class="bar-dot"></div>
          <h3 class="bar-title">Mood Tracker</h3>
        </div>

        <?php if (!empty($_GET['saved'])): ?>
          <p class="form-success" role="status">Mood tersimpan!</p>
        <?php elseif (!empty($_GET['updated'])): ?>
          <p class="form-success" role="status">Riwayat berhasil diperbarui.</p>
        <?php elseif (!empty($_GET['deleted'])): ?>
          <p class="form-success" role="status">Riwayat terpilih berhasil dihapus.</p>
        <?php elseif ($flash): ?>
          <p class="form-error" role="alert"><?= htmlspecialchars($flash) ?></p>
        <?php endif; ?>

        <form action="home.php" method="POST" class="ec-form" autocomplete="off">
          <input type="hidden" name="action" value="create">
          <div class="form-row">
            <label>Skala Mood</label>
            <div class="scale-group" role="radiogroup" aria-label="Skala Mood">
              <label class="scale-pill"><input type="radio" name="mood_level" value="1" required>1. Senang
                Banget</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="2">2. Senang</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="3">3. Biasa</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="4">4. Cemas</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="5">5. Stress</label>
            </div>
            <small class="helper">Tanggal otomatis (hari ini).</small>
          </div>

          <div class="form-row">
            <label for="mood-note">Catatan</label>
            <textarea id="mood-note" name="catatan" rows="3" placeholder="Tulis catatan singkat…"></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <button type="reset" class="btn btn-ghost">Reset</button>
          </div>
        </form>
      </article>

      <article class="card ec-history-card">
        <div class="card-bar">
          <div class="bar-dot"></div>
          <h3 class="bar-title">Riwayat Mood</h3>
        </div>

        <div class="ec-toolbar" style="display:flex; align-items:center; gap:8px; width:100%; margin:12px 0;">
          <form action="home.php#top" method="get" style="display:flex; align-items:center; gap:8px; flex:1;">
            <input type="text" name="s" placeholder="Cari…" value="<?= htmlspecialchars($_GET['s'] ?? '') ?>"
              style="flex:1; min-width:0; height:36px;">
            <button type="submit" class="btn btn-primary">Cari</button>
          </form>
          <button type="button" id="btnEdit" class="btn btn-ghost" disabled>Edit</button>
          <button type="submit" form="del-form" id="btnDelete" class="btn btn-ghost" disabled
            onclick="return confirm('Hapus baris yang dipilih?');">Hapus</button>
        </div>

        <form id="edit-form" action="home.php#top" method="post"
          style="display:none; border:1px dashed #e5e7eb; padding:10px; border-radius:10px; margin:-4px 0 12px;">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="mood_id" id="edit-id">
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <label> Mood:
              <select name="mood_level" id="edit-mood" required>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
              </select>
            </label>
            <label style="flex:1; min-width:240px;">
              Catatan:
              <input type="text" name="catatan" id="edit-note" placeholder="Ubah catatan…" style="width:100%;">
            </label>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <button type="button" id="edit-cancel" class="btn btn-ghost">Batal</button>
          </div>
        </form>

        <form id="del-form" action="home.php#top" method="post">
          <input type="hidden" name="action" value="delete">
          <div class="table-wrapper">
            <table class="ec-table" aria-label="Tabel Riwayat Mood">
              <thead>
                <tr>
                  <th style="width:42px;text-align:center;"><input type="checkbox" id="chkAll"></th>
                  <th>No</th>
                  <th>Tanggal</th>
                  <th>Mood</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody id="tbody-history">
                <?php if (empty($items)): ?>
                  <tr class="empty">
                    <td colspan="5">Belum ada data.</td>
                  </tr>
                <?php else:
                  foreach ($items as $i => $it): ?>
                    <tr>
                      <td style="text-align:center;">
                        <input type="checkbox" class="rowchk" name="delete_ids[]" value="<?= (int) $it['mood_id'] ?>"
                          data-mood="<?= (int) $it['mood_level'] ?>"
                          data-note="<?= htmlspecialchars($it['catatan'] ?? '', ENT_QUOTES) ?>">
                      </td>
                      <td><?= $i + 1 ?></td>
                      <td><?= htmlspecialchars($it['tanggal']) ?></td>
                      <td><?= (int) $it['mood_level'] ?></td>
                      <td><?= nl2br(htmlspecialchars($it['catatan'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </form>
      </article>
    </section>

    <section id="stats" class="ec-card">
      <h2 class="ec-section-title">Statistik &amp; Progress</h2>
      <div class="ec-tiles">
        <div class="ec-tile">
          <div class="k">Total Aktivitas</div>
          <div class="v"><?= count($items) ?></div>
        </div>
        <div class="ec-tile">
          <div class="k">Streak Harian</div>
          <div class="v"><?= $streak ?> hari</div>
        </div>
        <div class="ec-tile">
          <div class="k">Rata-rata Mood</div>
          <div class="v"><?= $avgOverall ?>/5</div>
        </div>
        <?php
        $activeQuizCount = 0;
        foreach ($quizzes as $q)
          if ((int) $q['active_q'] > 0)
            $activeQuizCount++;
        ?>
        <div class="ec-tile">
          <div class="k">Kuis Selesai</div>
          <div class="v"><?= (int) $quizDone ?><?= $activeQuizCount ? '/' . $activeQuizCount : '' ?></div>
        </div>
      </div>
    </section>

  </main>

  <script>
    // Jam & ucapan sederhana
    const clock = document.getElementById('greetClock'),
      word = document.getElementById('greetTimeWord');
    function tick() {
      const d = new Date();
      clock.textContent = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
      const h = d.getHours(); word.textContent = (h < 11) ? 'Pagi' : (h < 15) ? 'Siang' : (h < 18) ? 'Sore' : 'Malam';
    } tick(); setInterval(tick, 30000);

    // Auto-submit saat bulan diganti
    document.getElementById('monthSelect')?.addEventListener('change', e => e.target.form.submit());
  </script>

  <script>
    (function () {
      const btnDelete = document.getElementById('btnDelete');
      const btnEdit = document.getElementById('btnEdit');
      const chkAll = document.getElementById('chkAll');
      const editForm = document.getElementById('edit-form');
      const editId = document.getElementById('edit-id');
      const editMood = document.getElementById('edit-mood');
      const editNote = document.getElementById('edit-note');
      const editCancel = document.getElementById('edit-cancel');

      function getChecked() { return Array.from(document.querySelectorAll('.rowchk:checked')) }
      function refresh() {
        const rows = getChecked();
        if (btnDelete) btnDelete.disabled = (rows.length === 0);
        if (btnEdit) btnEdit.disabled = (rows.length !== 1);
        if (rows.length !== 1 && editForm) editForm.style.display = 'none';
      }

      chkAll?.addEventListener('change', () => {
        document.querySelectorAll('.rowchk').forEach(c => c.checked = chkAll.checked);
        refresh();
      });
      document.addEventListener('change', e => {
        if (e.target?.classList.contains('rowchk')) {
          if (!e.target.checked && chkAll) chkAll.checked = false;
          refresh();
        }
      });
      btnEdit?.addEventListener('click', () => {
        const r = getChecked(); if (r.length !== 1) return;
        editId.value = r[0].value; editMood.value = r[0].dataset.mood || ''; editNote.value = r[0].dataset.note || '';
        editForm.style.display = ''; setTimeout(() => editNote?.focus(), 0);
      });
      editCancel?.addEventListener('click', () => { editForm.style.display = 'none'; });
      refresh();
    })();
  </script>

  <?php if (!empty($_GET['updated']) || !empty($_GET['deleted'])): ?>
    <script>
      (function () {
        const url = new URL(location.href);
        const msg = url.searchParams.has('updated') ? 'Riwayat berhasil diperbarui.' : 'Riwayat terpilih berhasil dihapus.';
        alert(msg);
        ['deleted', 'saved', 'updated'].forEach(k => url.searchParams.delete(k));
        const qs = url.searchParams.toString();
        history.replaceState(null, '', url.pathname + (qs ? ('?' + qs) : '') + url.hash);
      })();
    </script>
  <?php endif; ?>

  <!-- HIDE: Rekapan Bulanan + Riwayat Hasil Kuis -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // 1) Hapus kartu/section "Rekapan Bulanan"
  const heads = document.querySelectorAll('h1, h2, h3, .title, .section-title');
  heads.forEach(h => {
    const t = (h.textContent || '').trim().toLowerCase();
    if (t.includes('rekapan bulanan')) {
      // cari container besar terdekat (section/panel/card)
      let node = h;
      let removed = false;
      while (node && node !== document.body) {
        if (
          (node.tagName && ['SECTION','ARTICLE'].includes(node.tagName)) ||
          (node.classList && (node.classList.contains('panel') ||
                              node.classList.contains('card')  ||
                              node.classList.contains('recap-card')))
        ) {
          node.remove();
          removed = true;
          break;
        }
        node = node.parentElement;
      }
      if (!removed) { // fallback: buang parent 3 tingkat
        let p = h.parentElement;
        for (let i=0; i<3 && p && p!==document.body; i++) p = p.parentElement;
        if (p) p.remove();
      }
    }
  });

  // 2) Hapus tombol/link "Riwayat Hasil Kuis"
  const els = document.querySelectorAll('a, button');
  els.forEach(el => {
    const txt = (el.textContent || '').trim().toLowerCase();
    if (txt.includes('riwayat hasil kuis')) el.remove();
  });
});
</script>

</body>

</html>