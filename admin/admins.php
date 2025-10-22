<?php
require_once __DIR__.'/_init.php';
$PAGE_TITLE   = 'Daftar Admin • EmoCare';
$PAGE_HEADING = 'Daftar Admin';
$active       = 'admins';
include __DIR__.'/_head.php';

function initials($s){
  $s = trim($s);
  if ($s === '') return 'A';
  $parts = preg_split('/\s+/', $s);
  $a = mb_substr($parts[0],0,1);
  $b = isset($parts[1]) ? mb_substr($parts[1],0,1) : '';
  return mb_strtoupper($a.$b);
}

$rows = [];
$q = $mysqli->query("SELECT pengguna_id, nama, email, created_at
                     FROM pengguna
                     WHERE role='admin'
                     ORDER BY pengguna_id DESC");
if ($q) $rows = $q->fetch_all(MYSQLI_ASSOC);
?>

<div class="card soft">
  <h2 class="card-title">Daftar Akun Admin</h2>

  <?php if (!$rows): ?>
    <div class="empty">Belum ada admin.</div>
  <?php else: ?>
    <table class="table pretty zebra">
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th>Nama</th>
          <th>Email</th>
          <th style="width:180px">Dibuat</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><span class="badge-id"><?= (int)$r['pengguna_id'] ?></span></td>
            <td class="name-cell">
              <span class="avatar"><?= h(initials($r['nama'])) ?></span>
              <span><?= h($r['nama']) ?></span>
            </td>
            <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
            <td><?= h(substr($r['created_at'],0,16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__.'/_foot.php'; ?>
