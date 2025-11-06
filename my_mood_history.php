<?php
// my_mood_history.php — History Mood untuk user (aman tanpa warning include)
require_once __DIR__ . '/backend/config.php';
require_login();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

/* ===== helper include aman (header/footer) ===== */
function include_first_or_fallback(array $candidates, string $fallbackHtml = ''): void {
  foreach ($candidates as $f) {
    if (is_string($f) && file_exists($f)) { include $f; return; }
  }
  if ($fallbackHtml !== '') echo $fallbackHtml;
}

$HEADER_CANDIDATES = [
  __DIR__ . '/public/_header.php',
  __DIR__ . '/_header.php',
  __DIR__ . '/partials/_header.php',
  __DIR__ . '/partials/header.php',
  __DIR__ . '/components/_header.php',
  __DIR__ . '/components/header.php',
  __DIR__ . '/includes/_header.php',
  __DIR__ . '/includes/header.php',
  __DIR__ . '/layout/_header.php',
  __DIR__ . '/layout/header.php',
];

$FOOTER_CANDIDATES = [
  __DIR__ . '/public/_footer.php',
  __DIR__ . '/_footer.php',
  __DIR__ . '/partials/_footer.php',
  __DIR__ . '/partials/footer.php',
  __DIR__ . '/components/_footer.php',
  __DIR__ . '/components/footer.php',
  __DIR__ . '/includes/_footer.php',
  __DIR__ . '/includes/footer.php',
  __DIR__ . '/layout/_footer.php',
  __DIR__ . '/layout/footer.php',
];

/* ===== data ===== */
$uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);
$sql = "SELECT mood_level, tanggal, catatan
        FROM moodtracker
        WHERE pengguna_id=?
        ORDER BY tanggal DESC, mood_id DESC
        LIMIT 500";
$st = $mysqli->prepare($sql);
$st->bind_param('i', $uid);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

function mood_label($x){
  $map=[1=>'Senang Banget',2=>'Senang',3=>'Biasa',4=>'Cemas',5=>'Stress'];
  return $map[(int)$x] ?? (string)$x;
}

/* ===== view ===== */
include_first_or_fallback($HEADER_CANDIDATES, "<!doctype html><meta charset='utf-8'><title>History Mood</title>");
?>
<link rel="stylesheet" href="css/admin.css">
<style>
  .hist-wrap{max-width:1100px;margin:24px auto}
  .hist-card{background:#fff;border-radius:18px;padding:16px;border:1px solid #f6d6e3;box-shadow:0 10px 30px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,.6)}
  .hist-title{font-weight:800;font-size:1.25rem;margin:0 0 12px}
  .hist-sub{margin:-6px 0 12px;color:#888}
  .hist-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #f6d6e3;border-radius:14px;overflow:hidden;background:#fff}
  .hist-table thead th{background:#fff0f7;color:#c12b70;font-weight:700;padding:12px 14px;text-align:left;border-bottom:1px solid #f6d6e3}
  .hist-table tbody td{padding:11px 14px;border-bottom:1px solid #f6d6e3;vertical-align:top}
  .hist-table tbody tr:last-child td{border-bottom:0}
  .hist-table tbody tr:hover td{background:#fff9fb}
  .scale-pill{display:inline-flex;gap:8px;align-items:center;background:#ffe8f1;border:1px solid #f7c3d8;color:#b82668;padding:6px 10px;border-radius:999px;font-weight:700}
  .scale-pill .num{width:24px;height:24px;display:inline-grid;place-items:center;border-radius:999px;background:#fff;border:1px solid #f7c3d8}
  .backbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .btn.tiny{padding:6px 10px;border-radius:999px;font-size:.92rem}
</style>

<main class="hist-wrap">
  <div class="backbar">
    <a class="btn ghost tiny" href="home.php">← Kembali</a>
  </div>

  <div class="hist-card">
    <h2 class="hist-title">History Mood Saya</h2>
    <div class="hist-sub">Menampilkan maks. 500 entri terbaru.</div>

    <div style="overflow:auto;border-radius:14px">
      <table class="hist-table">
        <thead>
          <tr>
            <th style="width:64px">No</th>
            <th style="width:170px">Tanggal</th>
            <th style="width:190px">Skala Mood</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="4" style="text-align:center;color:#888;padding:18px">Belum ada data.</td></tr>
          <?php else: $no=1; foreach ($rows as $r): $s=(int)$r['mood_level']; ?>
            <tr>
              <td><?= $no++ ?></td>
              <td><?= h($r['tanggal']) ?></td>
              <td>
                <span class="scale-pill"><span class="num"><?= $s ?></span> <?= h(mood_label($s)) ?></span>
              </td>
              <td><?= nl2br(h((string)$r['catatan'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php include_first_or_fallback($FOOTER_CANDIDATES); ?>
