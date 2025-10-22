<?php
$__quizzes=[];
$q=$mysqli->query("SELECT slug,name,icon FROM quiz_list WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if($q) $__quizzes=$q->fetch_all(MYSQLI_ASSOC);

?>
<aside class="sidebar">
  <div class="brand">
    <div class="logo-heart" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M12 21s-5.052-3.33-8.097-6.375C1.52 12.242 1.29 8.93 3.514 6.706a5.25 5.25 0 0 1 7.424 0L12 7.768l1.062-1.062a5.25 5.25 0 0 1 7.424 0c2.224 2.224 1.994 5.536-.389 7.919C17.052 17.67 12 21 12 21z"/></svg>
    </div>
    <div class="brand-text">
      <div class="brand-name">EmoCare • Admin</div>
      <div class="admin-name"><?= h($_SESSION['user']['nama'] ?? 'Administrator') ?></div>
    </div>
  </div>

  <nav>
    <a href="dashboard.php" class="<?= ($active==='dashboard'?'active':'') ?>">
      <span class="ico">📊</span><span class="_text">Dashboard</span>
    </a>

    <div class="menu-head">KUIS</div>
    <?php foreach($__quizzes as $k): ?>
      <a href="quiz_manage.php?cat=<?= urlencode($k['slug']) ?>" class="<?= ($active===$k['slug']?'active':'') ?>">
        <span class="ico"><?= h($k['icon']?:'✨') ?></span><span class="_text"><?= h($k['name']) ?></span>
      </a>
    <?php endforeach; ?>

    <a href="quizzes.php" class="<?= ($active==='quizzes'?'active':'') ?>">
      <span class="ico">➕</span><span class="_text">Kelola Kuis</span>
    </a>

    <div class="menu-head">AKUN</div>
    <a href="admins.php" class="<?= ($active==='admins'?'active':'') ?>"><span class="ico">👑</span><span class="_text">Daftar Admin</span></a>
    <a href="users.php"  class="<?= ($active==='users'?'active':'') ?>"><span class="ico">👥</span><span class="_text">Daftar User</span></a>
    <a href="../home.php"><span class="ico">↪</span><span class="_text">Kembali ke Home</span></a>
  </nav>
</aside>
