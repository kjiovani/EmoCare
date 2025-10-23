<?php
// admin/dashboard.php
require_once __DIR__ . '/_init.php';

$PAGE_TITLE = 'Dashboard • EmoCare';
$PAGE_HEADING = 'Dashboard';
$active = 'dashboard'; // highlight di sidebar

include __DIR__ . '/_head.php';

// ambil semua kuis aktif seperti yang dipakai sidebar
$cards = [];
$sql = "
  SELECT l.id, l.name, l.slug, l.icon,
    (SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug) AS total_q,
    (SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug AND q.is_active=1) AS active_q
  FROM quiz_list l
  WHERE l.is_active = 1
  ORDER BY l.sort_order ASC, l.id ASC
";
if ($r = $mysqli->query($sql)) {
  $cards = $r->fetch_all(MYSQLI_ASSOC);
}
?>

<!-- Kartu-kartu kuis (selaras dengan sidebar) -->
<div class="cards-grid">
  <?php foreach ($cards as $c): ?>
    <div class="card soft">
      <div class="card-title"><?= h($c['name']) ?></div>
      <div class="muted">Total pertanyaan: <b><?= (int) $c['total_q'] ?></b></div>
      <div class="muted">Aktif: <b><?= (int) $c['active_q'] ?></b></div>
      <div style="margin-top:12px">
        <a class="btn" href="quiz_manage.php?cat=<?= urlencode($c['slug']) ?>">Kelola</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Aksi cepat yang juga ada di sidebar -->
<div class="cards-grid" style="margin-top:18px">


  <div class="card">
    <div class="card-title">Daftar Admin</div>
    <p class="muted">Kelola akun admin sistem.</p>
    <div style="margin-top:12px"><a class="btn ghost" href="admins.php">Buka</a></div>
  </div>

  <div class="card">
    <div class="card-title">Daftar User</div>
    <p class="muted">Kelola akun pengguna.</p>
    <div style="margin-top:12px"><a class="btn ghost" href="users.php">Buka</a></div>
  </div>
</div>

<!-- Ajakan tambah kuis baru -->
<div class="card" style="margin-top:18px">
  <div class="muted">Ingin menambah kuis baru?</div>
  <a class="btn" href="quizzes.php" style="margin-top:8px">Tambah Kuis</a>
</div>

<?php include __DIR__ . '/_foot.php'; ?>