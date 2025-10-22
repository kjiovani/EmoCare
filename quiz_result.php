<?php
// quiz_result.php
require_once __DIR__ . '/backend/config.php';
require_login();

$CAT_LABEL = [
  'self_esteem'    => 'Self-Esteem',
  'social_anxiety' => 'Kecemasan Sosial',
];

$uid = (int)($_SESSION['user']['pengguna_id'] ?? 0);
$attemptId = (int)($_GET['attempt'] ?? 0);

$stmt = $mysqli->prepare("
  SELECT id, pengguna_id, category, score, label, notes, created_at
  FROM quiz_attempts
  WHERE id = ? AND pengguna_id = ?
  LIMIT 1
");
$stmt->bind_param('ii', $attemptId, $uid);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
  http_response_code(404);
  exit('Hasil tidak ditemukan.');
}

$cat = $attempt['category'];
$title = $CAT_LABEL[$cat] ?? ucfirst(str_replace('_',' ', $cat));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Hasil Kuis • <?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="css/admin.css" />
  <style>
    .panel{max-width:760px;margin:24px auto;padding:18px;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.05)}
    .title{font-weight:800;margin:0 0 6px}
    .muted{color:#6b7280}
    .scorebox{margin-top:12px;border:1px solid #f6c3d3;border-radius:16px;padding:18px;background:linear-gradient(180deg,#fff0f6,#fff)}
    .pct{font-size:42px;font-weight:900}
    .badge{display:inline-block;padding:6px 12px;border:1px solid #f1b9cd;border-radius:999px;font-weight:800;margin-top:6px}
    .btn{margin-top:14px}
    .row{display:flex;gap:8px;flex-wrap:wrap}
  </style>
</head>
<body>
  <div class="panel">
    <h2 class="title">Hasil Kuis • <?= htmlspecialchars($title) ?></h2>
    <div class="muted">Selesai pada: <?= htmlspecialchars($attempt['created_at'] ?? '') ?></div>

    <div class="scorebox">
      <div class="pct"><?= htmlspecialchars((float)$attempt['score']) ?>%</div>
      <div class="badge"><?= htmlspecialchars($attempt['label']) ?></div>
      <p class="muted" style="margin-top:10px"><?= nl2br(htmlspecialchars($attempt['notes'] ?? '')) ?></p>
    </div>

    <div class="row">
      <a class="btn" href="play_quiz.php?cat=<?= urlencode($cat) ?>">Ulangi Kuis</a>
      <a class="btn ghost" href="home.php">Kembali ke Beranda</a>
    </div>
  </div>
</body>
</html>
