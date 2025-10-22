<?php
require_once 'config.php';
$user = require_login();
$uid = $user['pengguna_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $limit = intval($_GET['limit'] ?? 10);
  $stmt = $mysqli->prepare("SELECT jurnal_id, tanggal, judul FROM jurnal_harian WHERE pengguna_id=? ORDER BY tanggal DESC, jurnal_id DESC LIMIT ?");
  $stmt->bind_param('ii', $uid, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $items = $res->fetch_all(MYSQLI_ASSOC);
  echo json_encode(['items' => $items]);
  exit;
}

$in = json_input();
$action = $in['action'] ?? 'create';
if ($action === 'create') {
  $judul = trim($in['judul'] ?? '');
  $isi = trim($in['isi'] ?? '');
  if (!$judul || !$isi) {
    echo json_encode(['error' => 'Lengkapi data']);
    exit;
  }
  $stmt = $mysqli->prepare("INSERT INTO jurnal_harian (pengguna_id, tanggal, judul, isi_jurnal) VALUES (?, CURDATE(), ?, ?)");
  $stmt->bind_param('iss', $uid, $judul, $isi);
  $ok = $stmt->execute();
  echo json_encode($ok ? ['success' => true] : ['error' => 'Gagal insert jurnal']);
  exit;
}
echo json_encode(['error' => 'Unsupported action']);
