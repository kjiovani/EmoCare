<?php
// my_quiz_history.php — History Kuis untuk user
require_once __DIR__ . '/backend/config.php';
require_login();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

/* ===== helper include aman ===== */
function include_first_or_fallback(array $candidates, string $fallbackHtml = ''): void {
  foreach ($candidates as $f) { if (is_string($f) && file_exists($f)) { include $f; return; } }
  if ($fallbackHtml !== '') echo $fallbackHtml;
}
$HEADER_CANDIDATES = [
  __DIR__ . '/public/_header.php', __DIR__ . '/_header.php',
  __DIR__ . '/partials/_header.php', __DIR__ . '/partials/header.php',
  __DIR__ . '/components/_header.php', __DIR__ . '/components/header.php',
  __DIR__ . '/includes/_header.php',  __DIR__ . '/includes/header.php',
  __DIR__ . '/layout/_header.php',    __DIR__ . '/layout/header.php',
];
$FOOTER_CANDIDATES = [
  __DIR__ . '/public/_footer.php', __DIR__ . '/_footer.php',
  __DIR__ . '/partials/_footer.php', __DIR__ . '/partials/footer.php',
  __DIR__ . '/components/_footer.php', __DIR__ . '/components/footer.php',
  __DIR__ . '/includes/_footer.php',  __DIR__ . '/includes/footer.php',
  __DIR__ . '/layout/_footer.php',    __DIR__ . '/layout/footer.php',
];

/* ===== data ===== */
$uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);

$DB = '';
if ($rs=$mysqli->query("SELECT DATABASE()")){ $DB=(string)$rs->fetch_row()[0]; $rs->close(); }
function has_col(mysqli $mysqli,string $db,string $t,string $c): bool {
  $st=$mysqli->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->bind_param('sss',$db,$t,$c); $st->execute(); $n=(int)$st->get_result()->fetch_column(); $st->close(); return $n>0;
}
$orderCol = 'qa.id';
if ($DB) {
  if (has_col($mysqli,$DB,'quiz_attempts','created_at'))      $orderCol='qa.created_at';
  elseif (has_col($mysqli,$DB,'quiz_attempts','attempt_id'))  $orderCol='qa.attempt_id';
}

$sql = "
  SELECT
    COALESCE(ql.name, qa.category) AS quiz_name,
    qa.score                        AS score_pct,
    qa.label                        AS rec_label,
    qa.notes                        AS rec_note,
    DATE(qa.created_at)             AS tgl
  FROM quiz_attempts qa
  LEFT JOIN quiz_list ql ON ql.slug = qa.category
  WHERE qa.pengguna_id = ?
  ORDER BY {$orderCol} DESC
  LIMIT 500
";
$st = $mysqli->prepare($sql);
$st->bind_param('i', $uid);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ===== view ===== */
include_first_or_fallback($HEADER_CANDIDATES, "<!doctype html><meta charset='utf-8'><title>History Kuis</title>");
?>
<link rel="stylesheet" href="css/admin.css">
<style>
  .qh-wrap{max-width:1100px;margin:24px auto}
  .qh-card{background:#fff;border-radius:18px;padding:16px;border:1px solid #f6d6e3;box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6)}
  .qh-title{font-weight:800;font-size:1.25rem;margin:0 0 12px}
  .qh-sub{margin:-6px 0 12px;color:#888}
  .qh-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #f6d6e3;border-radius:14px;overflow:hidden;background:#fff}
  .qh-table thead th{background:#fff0f7;color:#c12b70;font-weight:700;padding:12px 14px;text-align:left;border-bottom:1px solid #f6d6e3}
  .qh-table tbody td{padding:11px 14px;border-bottom:1px solid #f6d6e3;vertical-align:top}
  .qh-table tbody tr:last-child td{border-bottom:0}
  .qh-table tbody tr:hover td{background:#fff9fb}
  .col-no{width:64px}
  .col-quiz{width:220px}
  .col-date{width:130px}
  .col-score{width:210px;text-align:center}
  .score-pill{display:inline-flex;align-items:center;justify-content:center;padding:6px 8px;border-radius:999px;background:linear-gradient(180deg,#ffe8f1,#ffdcec);border:1px solid #f7c3d8;box-shadow:0 1px 0 rgba(255,255,255,.8) inset}
  .score-pill .num{min-width:92px;height:32px;padding:0 10px;border-radius:999px;background:#fff;border:1px solid #f7c3d8;display:flex;align-items:center;justify-content:center;color:#b82668;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:.2px}
  .rec{display:block;background:#fff6fa;border:1px solid #f7c3d8;color:#9b2c63;padding:8px 12px;border-radius:12px}
  .rec .rlabel{font-weight:800}
  .rec .rnote{display:block;color:#8a5875;margin-top:2px;font-size:.92rem}
  .backbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .btn.tiny{padding:6px 10px;border-radius:999px;font-size:.92rem}
</style>

<main class="qh-wrap">
  <div class="backbar">
    <a class="btn ghost tiny" href="home.php">← Kembali</a>
  </div>

  <div class="qh-card">
    <h2 class="qh-title">History Kuis Saya</h2>
    <div class="qh-sub">Menampilkan maks. 500 entri terbaru.</div>

    <div style="overflow:auto;border-radius:14px">
      <table class="qh-table">
        <thead>
          <tr>
            <th class="col-no">No</th>
            <th class="col-quiz">Nama Kuis</th>
            <th class="col-date">Tanggal</th>
            <th class="col-score">Hasil (Persentase)</th>
            <th>Disarankan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;padding:18px">Belum ada data.</td></tr>
          <?php else: $no=1; foreach ($rows as $r):
            $pct  = is_numeric($r['score_pct']) ? number_format((float)$r['score_pct'],2) : h((string)$r['score_pct']);
            $lbl  = trim((string)$r['rec_label']) ?: '-';
            $note = trim((string)$r['rec_note']);
            $tgl  = $r['tgl'] ?: '-';
          ?>
            <tr>
              <td><?= $no++ ?></td>
              <td><?= h($r['quiz_name'] ?: '-') ?></td>
              <td><?= h($tgl) ?></td>
              <td class="col-score">
                <span class="score-pill"><span class="num"><?= $pct ?>%</span></span>
              </td>
              <td>
                <span class="rec">
                  <span class="rlabel"><?= h($lbl) ?></span>
                  <?php if ($note!==''): ?><span class="rnote"><?= nl2br(h($note)) ?></span><?php endif; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php include_first_or_fallback($FOOTER_CANDIDATES); ?>
