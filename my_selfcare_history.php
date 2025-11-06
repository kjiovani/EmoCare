<?php
require_once __DIR__ . '/backend/config.php';
require_login();

$uid = (int) ($_SESSION['user']['pengguna_id'] ?? 0);
$limit = 500;

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');
$status = trim($_GET['status'] ?? ''); // '', 'done', 'missed'

$where = ["r.pengguna_id=?"];
$types = 'i';
$args  = [$uid];

/* Filter tanggal (opsional) */
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $where[] = "d.done_date >= ?";
  $types  .= 's';
  $args[]  = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $where[] = "d.done_date <= ?";
  $types  .= 's';
  $args[]  = $to;
}

/* Catatan:
   Riwayat yang kita simpan hanya 'yang selesai' di self_care_done.
   Jika ingin menampilkan 'missed' (terlewat), perlu kalender generator harian.
   Untuk versi ringan: status filter hanya 'done' (riil). 'missed' kita sembunyikan. */
if ($status === 'done' || $status === '') {
  // nothing, karena tabel ini memang done log.
}

$sql = "
  SELECT d.done_date, DATE_FORMAT(r.time_at,'%H:%i') hhmm,
         r.title, COALESCE(r.note,'') note,
         d.done_at
  FROM self_care_done d
  INNER JOIN self_care_reminders r ON r.id = d.reminder_id
  WHERE ".implode(' AND ',$where)."
  ORDER BY d.done_date DESC, d.id DESC
  LIMIT $limit
";
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$args);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>History Self-Care • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .page { max-width: 1100px; margin: 22px auto; padding: 0 14px; }
    .card { background:#fff; border:1px solid #f6d6e3; border-radius:18px; padding:16px; box-shadow:0 10px 28px rgba(245,154,181,.12); }
    .title { font-weight:900; font-size:22px; margin:4px 0 8px; }
    .muted { color:#6b7280; }
    .btn { background:#f472b6; color:#fff; border:0; border-radius:12px; padding:9px 12px; font-weight:800; cursor:pointer; box-shadow:0 6px 14px rgba(236,72,153,.12) }
    .btn.ghost { background:#fff; color:#be185d; border:1px solid #f9a8d4 }
    .btn:hover { transform: translateY(-1px); box-shadow:0 10px 22px rgba(236,72,153,.28), 0 0 0 2px rgba(236,72,153,.18) }
    .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:10px 0 14px }
    .toolbar input { height:36px; border:1px solid #f4cadd; border-radius:10px; padding:6px 10px }
    .table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid #f6d6e3; border-radius:14px; overflow:hidden }
    .table th { background:#fff0f7; color:#c12b70; font-weight:800; text-align:left; padding:10px 12px; border-bottom:1px solid #f6d6e3 }
    .table td { padding:10px 12px; border-bottom:1px solid #f6d6e3; vertical-align:top }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#f0fdf4; border:1px solid #bbf7d0; color:#065f46; font-weight:800 }
    .badge { display:inline-block; background:#ffe4ef; border:1px solid #f6c3da; color:#b91c65; border-radius:999px; padding:6px 10px; font-weight:800 }
  </style>
</head>
<body class="ec-body">
  <div class="page">
    <a href="home.php#stats" class="btn ghost">« Kembali</a>
    <div class="card" style="margin-top:12px">
      <div class="title">History Self-Care Saya</div>
      <div class="muted">Menampilkan maks. <?= $limit ?> entri terbaru.</div>

      <form class="toolbar" method="get" action="">
        <label>Tanggal dari:
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        </label>
        <label>s/d:
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        </label>
        <button class="btn" type="submit">Terapkan</button>
        <a class="btn ghost" href="my_selfcare_history.php">Reset</a>
        <span style="margin-left:auto" class="badge"><?= count($rows) ?> entri</span>
      </form>

      <?php if (empty($rows)): ?>
        <div class="muted" style="padding:14px">Belum ada riwayat selesai.</div>
      <?php else: ?>
        <div style="overflow:auto; border-radius:14px">
          <table class="table" aria-label="Tabel Riwayat Self-Care">
            <thead>
              <tr>
                <th style="width:60px">No</th>
                <th>Tanggal</th>
                <th style="width:90px">Pukul</th>
                <th>Judul</th>
                <th>Catatan</th>
                <th style="width:180px">Selesai pada</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i=>$r): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= htmlspecialchars($r['done_date']) ?></td>
                  <td><?= htmlspecialchars($r['hhmm']) ?></td>
                  <td><strong><?= htmlspecialchars($r['title']) ?></strong></td>
                  <td><?= nl2br(htmlspecialchars($r['note'])) ?></td>
                  <td><span class="pill">✔ <?= htmlspecialchars($r['done_at']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
