<?php
// admin/quiz_history.php — History Kuis (Admin)
require_once __DIR__ . '/_init.php'; // sudah include config + require_admin()
include __DIR__ . '/_head.php';
include __DIR__ . '/_sidebar.php';

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ============ Konfigurasi ============ */
$LIMIT = 500; // tampilkan maks 500 entri terbaru

// Deteksi kolom untuk ORDER BY yang aman (created_at / attempt_id / id)
$DBNAME = '';
if ($rs = $mysqli->query("SELECT DATABASE()")) {
  $DBNAME = (string)$rs->fetch_row()[0];
  $rs->close();
}
function has_col($mysqli, $db, $table, $col){
  $st = $mysqli->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->bind_param('sss', $db, $table, $col);
  $st->execute();
  $c = (int)$st->get_result()->fetch_column();
  $st->close();
  return $c > 0;
}
$orderCol = null;
if ($DBNAME) {
  if (has_col($mysqli, $DBNAME, 'quiz_attempts', 'created_at')) $orderCol = 'qa.created_at';
  elseif (has_col($mysqli, $DBNAME, 'quiz_attempts', 'attempt_id')) $orderCol = 'qa.attempt_id';
  elseif (has_col($mysqli, $DBNAME, 'quiz_attempts', 'id')) $orderCol = 'qa.id';
}
// fallback aman bila tidak ada kolom di atas
$ORDER_SQL = $orderCol ? "ORDER BY $orderCol DESC" : "ORDER BY qa.pengguna_id DESC, qa.score DESC";

/* ============ Ambil data ============ */
$sql = "
  SELECT
    p.nama                                  AS user_name,
    COALESCE(ql.name, qa.category)          AS quiz_name,
    qa.score                                 AS score_pct,
    qa.label                                 AS rec_label,
    qa.notes                                 AS rec_note
  FROM quiz_attempts qa
  JOIN pengguna p   ON p.pengguna_id = qa.pengguna_id
  LEFT JOIN quiz_list ql ON ql.slug = qa.category
  $ORDER_SQL
  LIMIT {$LIMIT}
";
$st = $mysqli->prepare($sql);
$st->execute();
$res  = $st->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$st->close();

?>
<link rel="stylesheet" href="../css/admin.css">
<style>
  /* ====== Kartu & Tabel — tema pink glossy ====== */
  .qh-wrap{max-width:1100px;margin:24px auto}
  .qh-card{
    background:#fff;border-radius:18px;padding:16px;
    box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6);
    border:1px solid #f6d6e3;
  }
  .qh-title{font-weight:800;font-size:1.25rem;margin:0 0 12px;color:#111}
  .qh-sub{margin:-6px 0 12px;color:#888}

  .qh-table{
    width:100%;border-collapse:separate;border-spacing:0;border:1px solid #f6d6e3;
    border-radius:14px; overflow:hidden; background:#fff;
  }
  .qh-table thead th{
    background:#fff0f7; color:#c12b70; font-weight:700;
    padding:12px 14px; text-align:left; border-bottom:1px solid #f6d6e3;
  }
  .qh-table tbody td{
    padding:11px 14px; border-bottom:1px solid #f6d6e3; color:#222; vertical-align:top;
  }
  .qh-table tbody tr:last-child td{border-bottom:0}
  .qh-table tbody tr:hover td{background:#fff9fb}

  /* ====== Score chip yang lebih rapi ====== */
.col-score{ width:210px; text-align:center }

.score-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:6px 8px;
  border-radius:999px;
  background:linear-gradient(180deg,#ffe8f1,#ffdcec);
  border:1px solid #f7c3d8;
  box-shadow:0 1px 0 rgba(255,255,255,.8) inset;
}

.score-pill .num{
  /* oval di dalam pill: cukup untuk "100.00%" */
  min-width:92px;               /* lebar konsisten */
  height:32px;
  padding:0 10px;
  border-radius:999px;
  background:#fff;
  border:1px solid #f7c3d8;
  display:flex; align-items:center; justify-content:center;
  color:#b82668; font-weight:800;

  /* angka tidak goyang */
  font-variant-numeric: tabular-nums;
  letter-spacing:.2px;
}


  /* Rekomendasi (label + note) */
  .rec{
    display:block;
    background:#fff6fa; border:1px solid #f7c3d8; color:#9b2c63;
    padding:8px 12px; border-radius:12px;
  }
  .rec .rlabel{font-weight:800}
  .rec .rnote{display:block;color:#8a5875;margin-top:2px;font-size:.92rem}

  /* Kolom lebar */
  .col-no{width:64px}
  .col-quiz{width:220px}
  .col-score{width:210px}
</style>

<main class="qh-wrap">
  <div class="qh-card">
    <h2 class="qh-title">History Kuis</h2>
    <div class="qh-sub">Rekap hasil pengerjaan kuis oleh pengguna. Menampilkan maks. <?= (int)$LIMIT ?> entri terbaru.</div>

    <div style="overflow:auto;border-radius:14px">
      <table class="qh-table">
        <thead>
          <tr>
            <th class="col-no">No</th>
            <th>Nama User</th>
            <th class="col-quiz">Nama Kuis</th>
            <th class="col-score">Hasil (Persentase)</th>
            <th>Disarankan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;padding:18px">Belum ada data.</td></tr>
          <?php else:
            $no = 1;
            foreach ($rows as $r):
              $quiz = $r['quiz_name'] ?: '-';
              $pct  = is_numeric($r['score_pct']) ? number_format((float)$r['score_pct'], 2) : h((string)$r['score_pct']);
              $lbl  = trim((string)$r['rec_label']) ?: '-';
              $note = trim((string)$r['rec_note']);
          ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= h($r['user_name']) ?></td>
            <td><?= h($quiz) ?></td>
           <td class="col-score">
  <span class="score-pill">
    <span class="num"><?= $pct ?>%</span>
  </span>
</td>

            <td>
              <span class="rec">
                <span class="rlabel"><?= h($lbl) ?></span>
                <?php if ($note !== ''): ?>
                  <span class="rnote"><?= nl2br(h($note)) ?></span>
                <?php endif; ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="qh-sub" style="margin-top:10px">
      Total ditampilkan: <b><?= count($rows) ?></b> entri.
    </div>
  </div>
</main>

<?php include __DIR__ . '/_foot.php'; ?>
