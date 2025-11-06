<?php
// journal_detail.php
require_once __DIR__ . '/backend/config.php';
require_login();

/* --- identitas --- */
$uid  = (int)($_SESSION['user']['pengguna_id'] ?? 0);
$nama = $_SESSION['user']['nama'] ?? 'Pengguna';

/* --- ambil id --- */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "Bad request.";
  exit;
}

/* --- fetch entri milik user --- */
$sql = "SELECT id, pengguna_id, tanggal, title, content, mood_level, tags, image_path,
               created_at, updated_at
        FROM journal_entries
        WHERE id=? AND pengguna_id=? LIMIT 1";
$st = $mysqli->prepare($sql);
$st->bind_param('ii', $id, $uid);
$st->execute();
$jr = $st->get_result()->fetch_assoc();
$st->close();

if (!$jr) {
  http_response_code(404);
  echo "Jurnal tidak ditemukan atau bukan milik Anda.";
  exit;
}

/* --- handle delete --- */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['j_action'] ?? '') === 'delete')) {
  // ambil path untuk dihapus
  $img = $jr['image_path'] ?? null;

  $del = $mysqli->prepare("DELETE FROM journal_entries WHERE id=? AND pengguna_id=?");
  $del->bind_param('ii', $id, $uid);
  $del->execute();
  $del->close();

  // hapus file gambar jika ada dan berada di folder uploads/journal
  if ($img) {
    $abs = realpath(__DIR__ . '/' . $img);
    $base = realpath(__DIR__ . '/uploads/journal');
    if ($abs && $base && str_starts_with($abs, $base) && file_exists($abs)) {
      @unlink($abs);
    }
  }

  header('Location: home.php#journal');
  exit;
}

/* --- helper tampilan --- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function moodLabel($v){
  $v = (int)$v;
  return $v===0?'—':($v.'/5');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Jurnal • <?= e($jr['title']) ?> • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css"/>
  <link rel="stylesheet" href="css/dashboard.css"/>
  <style>
    body{background:#fff;}
    .wrap{max-width:980px;margin:24px auto;padding:0 14px;}
    .back{display:inline-flex;gap:8px;align-items:center;text-decoration:none;font-weight:800;color:#b91c65}
    .card{background:#fff;border:1px solid #f4cadd;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);padding:18px}
    .title{font-size:22px;font-weight:900;color:#0f172a;margin:6px 0 10px}
    .meta{display:flex;gap:10px;flex-wrap:wrap;color:#9d174d;font-weight:800}
    .pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#fff0f7;border:1px solid #f6c3da}
    .imgwrap{margin:12px 0}
    .imgwrap img{max-width:100%;height:auto;border-radius:14px;border:1px solid #f4cadd}
    .content{color:#334155;line-height:1.7;white-space:pre-wrap}
    .tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
    .tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#ffe8f3;border:1px solid #f3b9d2;color:#9d174d;font-weight:800}
    .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}
    .btn{background:#f472b6;color:#fff;border:0;border-radius:14px;padding:10px 14px;font-weight:800;cursor:pointer;box-shadow:0 6px 14px rgba(236,72,153,.12)}
    .btn.ghost{background:#fff;color:#be185d;border:1px solid #f9a8d4}
    .danger{background:#ef4444}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="back" href="home.php#journal">← Kembali ke Home</a>

    <div class="card" style="margin-top:12px">
      <div class="meta">
        <span class="pill"><?= e(date('d M Y', strtotime($jr['tanggal']))) ?></span>
        <span class="pill">Mood: <?= moodLabel($jr['mood_level']) ?></span>
        <span class="pill">Dibuat: <?= e(date('d M Y H:i', strtotime($jr['created_at']))) ?></span>
      </div>

      <h1 class="title"><?= e($jr['title']) ?></h1>

      <?php if (!empty($jr['image_path'])): ?>
        <div class="imgwrap">
          <img src="<?= e($jr['image_path']) ?>" alt="Gambar Jurnal">
        </div>
      <?php endif; ?>

      <div class="content"><?= nl2br(e($jr['content'])) ?></div>

      <?php if (trim($jr['tags'])!==''): ?>
        <div class="tags">
          <?php foreach (explode(',', $jr['tags']) as $tg): $tg=trim($tg); if(!$tg) continue; ?>
            <span class="tag">#<?= e($tg) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="actions">
        <!-- (Opsional) tombol edit bisa diarahkan ke halaman edit jika nanti dibuat -->
        <!-- <a href="journal_edit.php?id=<?= (int)$jr['id'] ?>" class="btn ghost">Edit</a> -->
        <form method="post" onsubmit="return confirm('Hapus entri ini? Gambar (jika ada) juga akan dihapus.');">
          <input type="hidden" name="j_action" value="delete">
          <button class="btn danger" type="submit">Hapus</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
