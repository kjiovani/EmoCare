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
   Self-Care: handler & data hari ini
------------------------------------- */
// Tandai selesai dari Home
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['sc_action'] ?? '') === 'done')) {
  $rid = (int) ($_POST['rid'] ?? 0);
  if ($rid > 0) {
    // UNIQUE(reminder_id, done_date) pada self_care_done harus ada
    $st = $mysqli->prepare("INSERT IGNORE INTO self_care_done(reminder_id,done_date,done_at) VALUES(?,CURDATE(),NOW())");
    $st->bind_param('i', $rid);
    $st->execute();
    $st->close();
  }
  header('Location: home.php#selfcare-manage');
  exit;
}

// Ambil pengingat hari ini (daily / weekly dengan days_mask)
function __sc_today_mask(): int {
  $w = (int) date('N');
  return 1 << (($w - 1) & 7);
} // Sen=bit0..Min=bit6
$__SC_today = [];
$__SC_mask = __sc_today_mask();
$st = $mysqli->prepare("
  SELECT
    r.id, r.title, COALESCE(r.note,'') AS note,
    DATE_FORMAT(r.time_at,'%H:%i') AS hhmm,
    r.freq, COALESCE(r.days_mask,0) AS days_mask,
    EXISTS(SELECT 1 FROM self_care_done d WHERE d.reminder_id=r.id AND d.done_date=CURDATE()) AS is_done
  FROM self_care_reminders r
  WHERE r.pengguna_id=? AND r.is_active=1
    AND (r.freq='daily' OR (r.freq='weekly' AND (COALESCE(r.days_mask,0) & ?)<>0))
  ORDER BY r.time_at ASC, r.id ASC
");
$st->bind_param('ii', $uid, $__SC_mask);
$st->execute();
$__SC_today = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
$__SC_done_count = 0;
foreach ($__SC_today as $__t) if (!empty($__t['is_done'])) $__SC_done_count++;

/* ======== [SC-MANAGE HELPERS] ======== */
function sc_build_mask_from_post(array $arr): int {
  $mask = 0;
  foreach ($arr as $v) {
    $i = (int)$v; // 1..7
    if ($i >= 1 && $i <= 7) $mask |= (1 << ($i-1));
  }
  return $mask;
}
function sc_days_label(int $mask): string {
  $names = [1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'];
  $out = [];
  foreach ($names as $i=>$n) if ($mask & (1<<($i-1))) $out[] = $n;
  return $out ? implode(', ',$out) : '—';
}

/* ======== [SC-MANAGE HANDLERS] ======== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['sc_manage'])) {
  $act = $_POST['sc_manage'];

  if ($act === 'create') {
    $title = trim($_POST['scm_title'] ?? '');
    $time  = preg_replace('~[^0-9:]~','', $_POST['scm_time'] ?? '08:00');
    $note  = trim($_POST['scm_note'] ?? '');
    $freq  = (($_POST['scm_freq'] ?? 'daily') === 'weekly') ? 'weekly' : 'daily';
    if (!preg_match('~^\d{2}:\d{2}$~', $time)) $time = '08:00';
    $mask  = ($freq==='weekly') ? sc_build_mask_from_post((array)($_POST['scm_days'] ?? [])) : 0;

    if ($title !== '') {
      $sql="INSERT INTO self_care_reminders(pengguna_id,title,note,freq,time_at,days_mask,is_active,created_at,updated_at)
            VALUES(?,?,?,?,?,?,1,NOW(),NOW())";
      $st = $mysqli->prepare($sql);
      $st->bind_param('issssi', $uid,$title,$note,$freq,$time,$mask);
      $st->execute(); $st->close();
    }
    header('Location: home.php#selfcare-manage'); exit;
  }

  if ($act === 'toggle') {
    $rid = (int)($_POST['rid'] ?? 0);
    if ($rid>0) {
      $st = $mysqli->prepare("UPDATE self_care_reminders SET is_active=IF(is_active=1,0,1), updated_at=NOW() WHERE id=? AND pengguna_id=?");
      $st->bind_param('ii',$rid,$uid); $st->execute(); $st->close();
    }
    header('Location: home.php#selfcare-manage'); exit;
  }

  if ($act === 'delete') {
    $rid = (int)($_POST['rid'] ?? 0);
    if ($rid>0) {
      @$mysqli->query("DELETE FROM self_care_done WHERE reminder_id=".$rid);
      $st = $mysqli->prepare("DELETE FROM self_care_reminders WHERE id=? AND pengguna_id=?");
      $st->bind_param('ii',$rid,$uid); $st->execute(); $st->close();
    }
    header('Location: home.php#selfcare-manage'); exit;
  }
}

/* Pagination (tetap ada, walau tabelnya tak dipakai) */
$SCM_rows = []; $SCM_total=0; $SCM_page=max(1,(int)($_GET['sc_page']??1)); $SCM_per=5;
$st = $mysqli->prepare("SELECT COUNT(*) FROM self_care_reminders WHERE pengguna_id=?");
$st->bind_param('i',$uid); $st->execute(); $SCM_total = (int)($st->get_result()->fetch_column() ?? 0); $st->close();
$SCM_pages = max(1,(int)ceil($SCM_total/$SCM_per));
if ($SCM_page>$SCM_pages) $SCM_page = $SCM_pages;
$SCM_off = ($SCM_page-1)*$SCM_per;
$st = $mysqli->prepare("
  SELECT id,title,COALESCE(note,'') note,freq,COALESCE(days_mask,0) days_mask,
         TIME_FORMAT(time_at,'%H:%i') hhmm,is_active
  FROM self_care_reminders
  WHERE pengguna_id=?
  ORDER BY is_active DESC, time_at ASC, id ASC
  LIMIT ? OFFSET ?");
$st->bind_param('iii',$uid,$SCM_per,$SCM_off);
$st->execute();
$SCM_rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ------------------------------------
   Kuis Psikologi
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
   Rekapan Bulanan Mood Tracker (fungsi dibiarkan, UI dihapus)
========================================================= */
function ym_bounds(string $ym): array { $start=$ym.'-01'; $end=date('Y-m-t', strtotime($start)); return [$start,$end]; }
function get_month_recap(mysqli $db, int $uid, string $ym): array
{
  [$start, $end] = ym_bounds($ym);
  $st = $db->prepare("SELECT COUNT(*) c, AVG(mood_level) a, MIN(mood_level) mi, MAX(mood_level) ma, AVG(mood_level*mood_level) a2 FROM moodtracker WHERE pengguna_id=? AND tanggal BETWEEN ? AND ?");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $agg = $st->get_result()->fetch_assoc() ?: ['c'=>0,'a'=>null,'mi'=>null,'ma'=>null,'a2'=>null];
  $st->close();

  $st = $db->prepare("SELECT COUNT(DISTINCT tanggal) d FROM moodtracker WHERE pengguna_id=? AND tanggal BETWEEN ? AND ?");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $days_active = (int) ($st->get_result()->fetch_column() ?? 0);
  $st->close();

  $dist=[1=>0,2=>0,3=>0,4=>0,5=>0];
  $st = $db->prepare("SELECT mood_level, COUNT(*) ct FROM moodtracker WHERE pengguna_id=? AND tanggal BETWEEN ? AND ? GROUP BY mood_level");
  $st->bind_param('iss', $uid, $start, $end);
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) { $lvl=(int)$row['mood_level']; if ($lvl>=1 && $lvl<=5) $dist[$lvl]=(int)$row['ct']; }
  $st->close();

  $mode=null; $modeCt=-1; $avgFloat=(float)($agg['a'] ?? 0);
  foreach ($dist as $lvl=>$ct) {
    if ($ct>$modeCt || ($ct===$modeCt && abs($lvl-$avgFloat)<abs(($mode ?? $lvl)-$avgFloat))) { $mode=$lvl; $modeCt=$ct; }
  }
  $sd=null;
  if (!is_null($agg['a2']) && !is_null($agg['a']) && (int)$agg['c']>1) {
    $sd = sqrt(max(0, (float)$agg['a2'] - ((float)$agg['a']*(float)$agg['a']))); $sd=round($sd,2);
  }

  return ['ym'=>$ym,'label'=>date('F Y', strtotime($start)),'start'=>$start,'end'=>$end,
          'total'=>(int)$agg['c'],'avg'=>is_null($agg['a'])?null:round((float)$agg['a'],2),
          'min'=>is_null($agg['mi'])?null:(int)$agg['mi'],'max'=>is_null($agg['ma'])?null:(int)$agg['ma'],
          'days_active'=>$days_active,'days_total'=>(int)date('t', strtotime($start)),
          'dist'=>$dist,'mode'=>$mode,'sd'=>$sd];
}
$ymParam = trim($_GET['month'] ?? '');
$ym = preg_match('/^\d{4}\-\d{2}$/', $ymParam) ? $ymParam : date('Y-m');
$recap = get_month_recap($mysqli, $uid, $ym);
$ymTime = strtotime($ym . '-01');
$prevYm = date('Y-m', strtotime('-1 month', $ymTime));
$nextYm = date('Y-m', strtotime('+1 month', $ymTime));
$nextIsFuture = (strtotime($nextYm . '-01') > strtotime(date('Y-m-01')));

/* --- Handlers Mood Tracker & data riwayat --- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create')) {
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($mood < 1 || $mood > 5) {
    $flash = 'Skala mood harus 1..5';
  } else {
    $stmt = $mysqli->prepare("INSERT INTO moodtracker(pengguna_id,tanggal,mood_level,catatan) VALUES(?,CURDATE(),?,?)");
    $stmt->bind_param('iis', $uid, $mood, $note);
    if ($stmt->execute()) { header('Location: home.php?saved=1'); exit; }
    else { $flash = 'Gagal menyimpan: ' . $stmt->error; }
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
    $stmt->execute(); $stmt->close();
    header('Location: home.php?deleted=1#top'); exit;
  } else $flash = 'Pilih minimal satu baris untuk dihapus.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'edit')) {
  $id = (int) ($_POST['mood_id'] ?? 0);
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($id <= 0) $flash = 'Data tidak valid.';
  elseif ($mood < 1 || $mood > 5) $flash = 'Skala mood harus 1..5';
  else {
    $sql = "UPDATE moodtracker SET mood_level=?, catatan=? WHERE mood_id=? AND pengguna_id=?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('isii', $mood, $note, $id, $uid);
    $stmt->execute(); $stmt->close();
    header('Location: home.php?updated=1#top'); exit;
  }
}
$items = [];
$s = trim($_GET['s'] ?? '');
$conds = ['pengguna_id = ?'];
$types = 'i';
$vals = [$uid];
if ($s !== '') {
  $conds[] = '(tanggal LIKE ? OR catatan LIKE ? OR CAST(mood_level AS CHAR) LIKE ?)';
  $types .= 'sss'; $like = '%' . $s . '%'; array_push($vals, $like, $like, $like);
}
$sql = "SELECT mood_id, tanggal, mood_level, catatan FROM moodtracker
        WHERE " . implode(' AND ', $conds) . " ORDER BY tanggal DESC, mood_id DESC";
$q = $mysqli->prepare($sql);
$q->bind_param($types, ...$vals);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) $items[] = $row;
$q->close();

// statistik ringkas
$streak = 0;
$__st = $mysqli->prepare("SELECT DISTINCT tanggal FROM moodtracker WHERE pengguna_id=? AND tanggal <= CURDATE() ORDER BY tanggal DESC LIMIT 120");
$__st->bind_param('i', $uid);
$__st->execute();
$__rs = $__st->get_result();
$__dates = [];
while ($__row = $__rs->fetch_assoc()) $__dates[$__row['tanggal']] = true;
$__st->close();
$__cur = new DateTimeImmutable('today');
while (isset($__dates[$__cur->format('Y-m-d')])) { $streak++; $__cur = $__cur->modify('-1 day'); }

$avgOverall = 0;
if ($items) { $sum = 0; foreach ($items as $it) $sum += (int) $it['mood_level']; $avgOverall = round($sum / count($items), 2); }

/* ============================
   Kuis Selesai untuk Statistik
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
function count_quiz_done(mysqli $db, int $uid): int
{
  $candidates = [
    ['quiz_result', 'finished_at IS NOT NULL', true],
    ['quiz_results', 'finished_at IS NOT NULL', true],
    ['quiz_sessions', 'finished_at IS NOT NULL', true],
    ['quiz_attempts', '(status=\"finished\" OR status=\"done\" OR is_finished=1)', true],
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
    .bg-hero { background: linear-gradient(135deg, #ffe0ea 0%, #e7dcff 50%, #dfe9ff 100%) }
    .quiz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
    .quiz-card{display:flex;gap:14px;align-items:flex-start;background:#fff;border:1px solid var(--bd);border-radius:18px;padding:16px;box-shadow:var(--shadow)}
    .quiz-ico{width:48px;height:48px;border-radius:14px;flex:0 0 48px;display:grid;place-items:center;font-size:22px;background:linear-gradient(180deg,#ffe7f2,#fff);border:1px solid var(--bd)}
    .quiz-name{font-weight:900;color:#111;margin-bottom:4px}
    .quiz-desc{color:#6b7280;line-height:1.5;margin-bottom:10px}
    .btn{background:#f472b6;color:#fff;border:0;border-radius:14px;padding:10px 14px;font-weight:800;cursor:pointer;box-shadow:0 6px 14px rgba(236,72,153,.12);transition:box-shadow .2s ease, transform .2s ease}
    .btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(236,72,153,.28),0 0 0 2px rgba(236,72,153,.18)}
    .btn[disabled]{opacity:.55;cursor:not-allowed}
    .btn.ghost{background:#fff;color:#be185d;border:1px solid #f9a8d4}
    .btn.ghost:hover{box-shadow:0 10px 22px rgba(236,72,153,.18),0 0 0 2px rgba(236,72,153,.14)}

    .empty{padding:18px;border:1px dashed #f4cadd;border-radius:14px;background:linear-gradient(180deg,#fff,#fff 70%,#fff8fc);color:#6b7280;text-align:center;font-weight:600}
    .role-user .qp-meta{display:none!important}

    /* ===== Self-Care styles ===== */
    .sc-card{border:1px solid #f9d6e6;border-radius:20px;margin-top:16px}
    .sc-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
    .sc-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff0f7;border:1px solid #f6c3da;color:#b91c65;font-weight:800}
    .sc-list{list-style:none;margin:0;padding:0 2px 10px 2px;display:flex;flex-direction:column;gap:10px}
    .sc-item{display:grid;grid-template-columns:90px 1fr auto;gap:12px;align-items:center;padding:12px;border:1px solid #f4cadd;border-radius:14px;background:linear-gradient(180deg,#fff,#fff7fb)}
    .sc-item:hover{background:#fffafd}
    .sc-item.is-done{opacity:.65}
    .sc-time{font-weight:900;color:#9d174d;background:#ffe6f3;border:1px solid #f7c3d8;border-radius:999px;padding:6px 10px;text-align:center}
    .sc-title{font-weight:900;color:#0f172a}
    .sc-note{color:#6b7280}
    .sc-actions{display:flex;align-items:center;gap:8px}
    .sc-chip{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eefdf3;border:1px solid #bbf7d0;color:#065f46;font-weight:800}

    /* ===== Kelola Self-Care ===== */
    .sc-form{display:grid;grid-template-columns:1fr 140px;gap:10px;margin-top:10px}
    .sc-form textarea{grid-column:1/-1;height:80px}
    .sc-form input[type="text"],.sc-form input[type="time"],.sc-form textarea{width:100%;height:38px;border:1px solid #e5e7eb;border-radius:10px;padding:8px}
    .sc-dow{display:flex;flex-wrap:wrap;gap:8px}
    .sc-dow label{border:1px solid #f7c3d8;border-radius:10px;padding:6px 8px;background:#fff0f7}

    /* ===== Tiles ===== */
    .ec-tiles{display:grid;gap:16px;grid-template-columns:repeat(1,minmax(0,1fr))}
    @media (min-width:640px){.ec-tiles{grid-template-columns:repeat(2,1fr)}}
    @media (min-width:900px){.ec-tiles{grid-template-columns:repeat(3,1fr)}}
    @media (min-width:1200px){.ec-tiles{grid-template-columns:repeat(4,1fr)}}
    .ec-tile{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:16px;padding:16px 18px;box-shadow:0 8px 30px rgba(15,23,42,.06);min-height:110px;display:flex;flex-direction:column;justify-content:center}
    .ec-tile .k{font-size:14px;color:#64748b}.ec-tile .v{font-size:26px;font-weight:900;margin-top:6px;color:#0f172a}
    .ec-tile.ec-tile--link{display:block;text-decoration:none;color:inherit;cursor:pointer;position:relative;transition:transform .18s cubic-bezier(.2,.8,.2,1),box-shadow .18s cubic-bezier(.2,.8,.2,1),border-color .18s,background-color .18s;will-change:transform}
    .ec-tile.ec-tile--link:hover{transform:translateY(-3px);box-shadow:0 18px 36px rgba(0,0,0,.06),0 0 0 2px rgba(245,154,181,.25),0 10px 28px rgba(245,154,181,.18)}
    .ec-tile.ec-tile--link:active{transform:translateY(0) scale(.985);box-shadow:0 12px 22px rgba(0,0,0,.05),0 0 0 2px rgba(245,154,181,.25),0 8px 20px rgba(245,154,181,.14)}
    .ec-tile.ec-tile--link:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(245,154,181,.45),0 18px 36px rgba(0,0,0,.06)}
    .ec-tile.ec-tile--link::after{content:"";position:absolute;pointer-events:none;border-radius:50%;width:140px;height:140px;opacity:0;top:var(--ripple-y,-100%);left:var(--ripple-x,-100%);transform:translate(-50%,-50%) scale(.8);background:radial-gradient(70px 70px at center,rgba(245,154,181,.22),rgba(245,154,181,0) 60%);transition:opacity .6s,transform .6s}
    .ec-tile.ec-tile--link.is-press::after{opacity:1;transform:translate(-50%,-50%) scale(1.65)}
    @media (prefers-reduced-motion:reduce){.ec-tile.ec-tile--link{transition:none}.ec-tile.ec-tile--link:hover,.ec-tile.ec-tile--link:active{transform:none;box-shadow:none}.ec-tile.ec-tile--link::after{display:none}}
  </style>
</head>

<body class="ec-body <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
  <header class="ec-nav">
    <div class="ec-nav-inner">
      <div class="ec-brand"><span class="ec-brand-name" style="font-weight:500;color:#6b7280;">EmoCare</span></div>
      <nav class="ec-nav-links">
        <a href="#top">Beranda</a>
        <a href="#features">Fitur</a>
        <a href="self_care.php">Self-Care</a>
        <a href="#stats">Statistik</a>
        <?php if ($isAdmin): ?><a href="admin/dashboard.php" class="btn ghost" style="margin-left:8px">Admin</a><?php endif; ?>
        <a href="home.php#selfcare">Self-Care</a>
      </nav>
      <form action="backend/auth_logout.php" method="post" style="margin:0"><button class="ec-btn-outline">Keluar</button></form>
    </div>
  </header>

  <main id="top" class="ec-container">
    <!-- Greeting / Hero -->
    <section class="ec-card ec-hero" id="greetingCard">
      <div class="ec-hero-left">
        <div class="ec-hero-title">Selamat <span id="greetTimeWord">Pagi</span>, <span id="greetUsername"><?= htmlspecialchars($nama) ?></span>! 🙌🏻</div>
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
                  <?php if ($isAdmin): ?><span class="pill-month"><?= (int)$q['active_q'] ?>/<?= (int)$q['total_q'] ?> aktif</span><?php endif; ?>
                  <?php if ((int)$q['active_q'] > 0): ?>
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

    <!-- ====== ANCHOR AGAR LINK LAMA TETAP JALAN ====== -->
    <span id="selfcare"></span>

    <!-- === [SC-MANAGE UI] Kelola + Self-Care Hari Ini === -->
    <section id="selfcare-manage" class="ec-card sc-card">
      <h3 class="ec-section-title" style="margin:0 0 8px">Kelola Self-Care</h3>

      <!-- Form Buat Pengingat -->
      <form class="sc-form" method="post" action="home.php#selfcare-manage" autocomplete="off">
        <input type="hidden" name="sc_manage" value="create">
        <div>
          <label style="font-weight:700;color:#374151">Judul*</label>
          <input type="text" name="scm_title" required placeholder="Contoh: Minum air 2 gelas">
        </div>
        <div>
          <label style="font-weight:700;color:#374151">Waktu</label>
          <input type="time" name="scm_time" value="08:00" required>
        </div>
        <textarea name="scm_note" placeholder="Catatan (opsional)"></textarea>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
          <label style="display:inline-flex;align-items:center;gap:6px;"><input type="radio" name="scm_freq" value="daily" checked> Harian</label>
          <label style="display:inline-flex;align-items:center;gap:6px;"><input type="radio" name="scm_freq" value="weekly"> Mingguan</label>
        </div>
        <div style="grid-column:1/-1">
          <div style="font-weight:700;color:#374151;margin:6px 0 4px">Hari (untuk Mingguan)</div>
          <div class="sc-dow">
            <?php $lbl=[1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min']; foreach($lbl as $i=>$t): ?>
              <label><input type="checkbox" name="scm_days[]" value="<?= $i ?>"> <?= $t ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;margin-top:6px">
          <button type="reset" class="btn ghost">Reset</button>
          <button type="submit" class="btn">Simpan</button>
        </div>
      </form>

      <!-- Self-Care Hari Ini -->
      <div class="sc-head" style="margin-top:14px">
        <h2 class="ec-section-title" style="margin:0">Self-Care Hari Ini</h2>
        <span class="sc-badge"><?= (int)$__SC_done_count ?>/<?= count($__SC_today) ?> selesai</span>
      </div>
      <?php if (empty($__SC_today)): ?>
        <div class="empty">Belum ada pengingat untuk hari ini. Tambahkan di form di atas.</div>
      <?php else: ?>
        <ul class="sc-list">
          <?php foreach ($__SC_today as $r): ?>
            <li class="sc-item <?= $r['is_done'] ? 'is-done' : '' ?>">
              <div class="sc-time"><?= htmlspecialchars($r['hhmm']) ?></div>
              <div class="sc-main">
                <div class="sc-title"><?= htmlspecialchars($r['title']) ?></div>
                <?php if (trim($r['note']) !== ''): ?>
                  <div class="sc-note"><?= nl2br(htmlspecialchars($r['note'])) ?></div>
                <?php endif; ?>
              </div>
              <div class="sc-actions">
                <?php if (!$r['is_done']): ?>
                  <form method="post" action="home.php#selfcare-manage">
                    <input type="hidden" name="sc_action" value="done">
                    <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
                    <button class="btn">Tandai Selesai</button>
                  </form>
                <?php else: ?>
                  <span class="sc-chip">Selesai</span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <!-- === [/SC-MANAGE UI] === -->

    <!-- (Rekapan Bulanan: DIHAPUS DARI TAMPILAN SESUAI PERMINTAAN) -->

    <!-- Mood Tracker -->
    <section id="mood-tracker" class="ec-mood-grid">
      <article class="card ec-mood-card">
        <div class="card-bar"><div class="bar-dot"></div><h3 class="bar-title">Mood Tracker</h3></div>

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
              <label class="scale-pill"><input type="radio" name="mood_level" value="1" required>1. Senang Banget</label>
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
        <div class="card-bar"><div class="bar-dot"></div><h3 class="bar-title">Riwayat Mood</h3></div>
        <div class="ec-toolbar" style="display:flex; align-items:center; gap:8px; width:100%; margin:12px 0;">
          <form action="home.php#top" method="get" style="display:flex; align-items:center; gap:8px; flex:1;">
            <input type="text" name="s" placeholder="Cari…" value="<?= htmlspecialchars($_GET['s'] ?? '') ?>" style="flex:1; min-width:0; height:36px;">
            <button type="submit" class="btn btn-primary">Cari</button>
          </form>
          <button type="button" id="btnEdit" class="btn btn-ghost" disabled>Edit</button>
          <button type="submit" form="del-form" id="btnDelete" class="btn btn-ghost" disabled onclick="return confirm('Hapus baris yang dipilih?');">Hapus</button>
        </div>

        <form id="edit-form" action="home.php#top" method="post" style="display:none; border:1px dashed #e5e7eb; padding:10px; border-radius:10px; margin:-4px 0 12px;">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="mood_id" id="edit-id">
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <label> Mood:
              <select name="mood_level" id="edit-mood" required>
                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option>
              </select>
            </label>
            <label style="flex:1; min-width:240px;">Catatan:
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
                <tr><th style="width:42px;text-align:center;"><input type="checkbox" id="chkAll"></th><th>No</th><th>Tanggal</th><th>Mood</th><th>Catatan</th></tr>
              </thead>
              <tbody id="tbody-history">
                <?php if (empty($items)): ?>
                  <tr class="empty"><td colspan="5">Belum ada data.</td></tr>
                <?php else: foreach ($items as $i=>$it): ?>
                  <tr>
                    <td style="text-align:center;">
                      <input type="checkbox" class="rowchk" name="delete_ids[]" value="<?= (int)$it['mood_id'] ?>"
                        data-mood="<?= (int)$it['mood_level'] ?>" data-note="<?= htmlspecialchars($it['catatan'] ?? '', ENT_QUOTES) ?>">
                    </td>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($it['tanggal']) ?></td>
                    <td><?= (int)$it['mood_level'] ?></td>
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
      <?php
      $uid = (int) ($_SESSION['user']['pengguna_id'] ?? 0);
      $quizDone = 0;
      if ($uid > 0) {
        $st = $mysqli->prepare("SELECT COUNT(DISTINCT qa.category) AS n
                                  FROM quiz_attempts qa
                                  INNER JOIN quiz_list ql ON ql.slug = qa.category AND ql.is_active = 1
                                  WHERE qa.pengguna_id = ?");
        $st->bind_param('i', $uid);
        $st->execute();
        $quizDone = (int) ($st->get_result()->fetch_column() ?? 0);
        $st->close();
      }
      $activeQuizCount = 0;
      foreach ($quizzes as $q) if ((int) $q['active_q'] > 0) $activeQuizCount++;
      ?>
      <h2 class="ec-section-title">Statistik &amp; Progress</h2>
      <div class="ec-tiles">
        <a class="ec-tile ec-tile--link" href="home.php#activity" aria-label="Lihat Aktivitas"><div class="k">Total Aktivitas</div><div class="v"><?= count($items) ?></div></a>
        <a class="ec-tile ec-tile--link" href="home.php#streak_tips" aria-label="Lihat Streak Harian"><div class="k">Streak Harian</div><div class="v"><?= $streak ?> hari</div></a>
        <a class="ec-tile ec-tile--link" href="my_mood_history.php" aria-label="Lihat History Mood"><div class="k">Rata-rata Mood</div><div class="v"><?= $avgOverall ?>/5</div></a>
        <a class="ec-tile ec-tile--link" href="my_quiz_history.php" aria-label="Lihat History Kuis"><div class="k">Kuis Selesai</div><div class="v"><?= (int) $quizDone ?><?= $activeQuizCount?'/'.$activeQuizCount:'' ?></div></a>
        <a class="ec-tile ec-tile--link" href="my_selfcare_history.php" aria-label="Buka History Self-Care">
          <div class="k">Self-Care</div><div class="v">Riwayat</div>
        </a>
      </div>
    </section>
  </main>

  <script>
    // Jam & ucapan sederhana
    const clock=document.getElementById('greetClock'), word=document.getElementById('greetTimeWord');
    function tick(){ const d=new Date();
      clock.textContent=d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
      const h=d.getHours(); word.textContent=(h<11)?'Pagi':(h<15)?'Siang':(h<18)?'Sore':'Malam';
    } tick(); setInterval(tick,30000);
    document.getElementById('monthSelect')?.addEventListener('change', e=>e.target.form.submit());
  </script>

  <script>
    (function(){
      const btnDelete=document.getElementById('btnDelete');
      const btnEdit=document.getElementById('btnEdit');
      const chkAll=document.getElementById('chkAll');
      const editForm=document.getElementById('edit-form');
      const editId=document.getElementById('edit-id');
      const editMood=document.getElementById('edit-mood');
      const editNote=document.getElementById('edit-note');
      const editCancel=document.getElementById('edit-cancel');
      function getChecked(){ return Array.from(document.querySelectorAll('.rowchk:checked')); }
      function refresh(){ const rows=getChecked();
        if(btnDelete) btnDelete.disabled=(rows.length===0);
        if(btnEdit) btnEdit.disabled=(rows.length!==1);
        if(rows.length!==1 && editForm) editForm.style.display='none';
      }
      chkAll?.addEventListener('change',()=>{ document.querySelectorAll('.rowchk').forEach(c=>c.checked=chkAll.checked); refresh(); });
      document.addEventListener('change',e=>{ if(e.target?.classList.contains('rowchk')){ if(!e.target.checked && chkAll) chkAll.checked=false; refresh(); }});
      btnEdit?.addEventListener('click',()=>{ const r=getChecked(); if(r.length!==1) return;
        editId.value=r[0].value; editMood.value=r[0].dataset.mood||''; editNote.value=r[0].dataset.note||'';
        editForm.style.display=''; setTimeout(()=>editNote?.focus(),0);
      });
      editCancel?.addEventListener('click',()=>{ editForm.style.display='none';});
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

  <!-- ALERT: pop-up saat jam Self-Care tiba -->
  <script>
    (function(){
      const scToday = <?= json_encode(array_map(function ($r) {
        return ['id'=>(int)$r['id'],'title'=>(string)$r['title'],'note'=>(string)$r['note'],'hhmm'=>(string)$r['hhmm'],'done'=>(bool)$r['is_done']];
      }, $__SC_today), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      if (!scToday.length) return;

      function toMin(s){ const [h,m]=s.split(':').map(Number); return h*60+m; }
      const waits = scToday.filter(x=>!x.done).map(x=>({...x,min:toMin(x.hhmm)}));
      if (!waits.length) return;

      const host=document.createElement('div'); Object.assign(host.style,{position:'fixed',right:'18px',bottom:'18px',zIndex:'10000',display:'grid',gap:'10px'}); document.body.appendChild(host);

      function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
      function toast(item){
        const el=document.createElement('div');
        el.innerHTML=`
          <div style="font-size:.85rem;font-weight:800;color:#b91c65;margin-bottom:2px">⏰ Self-Care</div>
          <div style="font-weight:900">${escapeHtml(item.title)}</div>
          ${item.note?`<div style="color:#6b7280;margin-top:2px">${escapeHtml(item.note)}</div>`:''}
          <form method="post" action="home.php#selfcare-manage" style="margin-top:10px;display:flex;gap:8px;align-items:center">
            <input type="hidden" name="sc_action" value="done"/>
            <input type="hidden" name="rid" value="${item.id}"/>
            <button class="btn" style="background:#f472b6">Tandai Selesai</button>
            <button type="button" class="btn ghost sc-close">Tutup</button>
          </form>`;
        Object.assign(el.style,{width:'min(360px,92vw)',padding:'14px 16px',border:'1px solid #f6c3d8',borderRadius:'16px',background:'linear-gradient(180deg,#fff0f7,#ffffff)',boxShadow:'0 16px 40px rgba(245,154,181,.25)',color:'#0f172a'});
        host.appendChild(el);
        el.querySelector('.sc-close').onclick=()=>el.remove();
        try{ const ctx=new (window.AudioContext||window.webkitAudioContext)(); const o=ctx.createOscillator(), g=ctx.createGain(); o.type='sine'; o.frequency.value=880; g.gain.value=.001; o.connect(g); g.connect(ctx.destination); o.start(); g.gain.exponentialRampToValueAtTime(.00001, ctx.currentTime+0.8); o.stop(ctx.currentTime+0.82);}catch(e){}
      }

      const fired=new Set();
      function tick(){
        const now=new Date(); const cur=now.getHours()*60+now.getMinutes();
        waits.forEach(w=>{ if(fired.has(w.id)) return; if (cur===w.min){ fired.add(w.id); toast(w); } });
      }
      tick(); setInterval(tick,15000);
    })();
  </script>
</body>
</html>
