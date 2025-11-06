<?php
// admin/mood_history.php — History Mood Tracker (tanpa cari & tanggal filter)
require_once __DIR__ . '/_init.php'; // include config + require_admin()
include __DIR__ . '/_head.php';
include __DIR__ . '/_sidebar.php';

/* ====== Mapping kolom sesuai DB kamu ====== */
$MOOD_TABLE = 'moodtracker';
$FIELD_ID   = 'mood_id';     // PK
$FIELD_SCALE= 'mood_level';  // skala mood
$FIELD_NOTE = 'catatan';
$FIELD_DATE = 'tanggal';     // tanggal entri
$LIMIT      = 500;           // tampilkan maks 500 terbaru

/* ====== Ambil data ====== */
$sqlData = "
  SELECT
    m.$FIELD_ID   AS id,
    p.nama,
    p.email,
    m.$FIELD_SCALE AS scale,
    DATE_FORMAT(m.$FIELD_DATE, '%Y-%m-%d') AS tgl,
    m.$FIELD_NOTE AS note
  FROM {$MOOD_TABLE} m
  JOIN pengguna p ON p.pengguna_id = m.pengguna_id
  ORDER BY m.$FIELD_DATE DESC, m.$FIELD_ID DESC
  LIMIT {$LIMIT}
";
$st = $mysqli->prepare($sqlData);
$st->execute();
$res  = $st->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ====== Helper label mood ====== */
function mood_label($x) {
  $map = [
    1 => 'Senang Banget',
    2 => 'Senang',
    3 => 'Biasa',
    4 => 'Cemas',
    5 => 'Stress',
  ];
  return $map[(int)$x] ?? (string)$x;
}
?>
<link rel="stylesheet" href="../css/admin.css">
<style>
  /* ====== Tampilan tabel meniru gaya di kiri (tema pink) ====== */
  .hist-wrap{max-width:1100px;margin:24px auto}
  .hist-card{
    background:#fff;border-radius:18px;padding:16px;
    box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6);
    border:1px solid #f6d6e3;
  }
  .hist-title{font-weight:800;font-size:1.25rem;margin:0 0 12px;color:#111}
  .hist-table{
    width:100%;border-collapse:separate;border-spacing:0;border:1px solid #f6d6e3;
    border-radius:14px; overflow:hidden;
  }
  .hist-table thead th{
    background:#fff0f7; color:#c12b70; font-weight:700;
    padding:12px 14px; text-align:left; border-bottom:1px solid #f6d6e3;
  }
  .hist-table thead th:first-child{border-top-left-radius:14px}
  .hist-table thead th:last-child{border-top-right-radius:14px}

  .hist-table tbody td{
    padding:11px 14px; border-bottom:1px solid #f6d6e3; color:#222; vertical-align:top;
    background:#fff;
  }
  .hist-table tbody tr:last-child td{border-bottom:0}
  .hist-table tbody tr:hover td{background:#fff9fb}

  /* pill skala seperti contoh kiri */
  .scale-pill{
    display:inline-flex; align-items:center; gap:8px;
    background:#ffe8f1; border:1px solid #f7c3d8; color:#b82668;
    padding:6px 10px; border-radius:999px; font-weight:700;
  }
  .scale-pill .num{
    display:inline-grid; place-items:center;
    width:24px; height:24px; border-radius:999px;
    background:#fff; border:1px solid #f7c3d8; font-weight:700;
  }
  .hist-foot{
    margin-top:10px; color:#666; font-size:.95rem;
  }

  /* kolom lebar agar rapi */
  .col-no{width:64px}
  .col-scale{width:170px}
  .col-date{width:170px}
</style>

<main class="hist-wrap">
  <div class="hist-card">
    <h2 class="hist-title">History Mood Tracker</h2>

    <!-- TABEL (tanpa form cari & tanggal) -->
    <div style="overflow:auto;border-radius:14px">
      <table class="hist-table">
        <thead>
          <tr>
            <th class="col-no">No</th>
            <th>Nama User</th>
            <th>Email</th>
            <th class="col-scale">Skala Mood</th>
            <th class="col-date">Tanggal</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" style="text-align:center;color:#888;padding:18px">Belum ada data.</td></tr>
        <?php else:
          $no = 1;
          foreach ($rows as $r):
            $scale = (int)$r['scale'];
        ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td>
              <span class="scale-pill">
                <span class="num"><?= $scale ?></span> <?= htmlspecialchars(mood_label($scale)) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($r['tgl']) ?></td>
            <td><?= nl2br(htmlspecialchars((string)$r['note'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="hist-foot">
      Total: <b><?= count($rows) ?></b> entri • Menampilkan maks. <?= $LIMIT ?> baris terbaru.
    </div>
  </div>
</main>

<?php include __DIR__ . '/_foot.php'; ?>
