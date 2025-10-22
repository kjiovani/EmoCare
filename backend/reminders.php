<?php
require_once 'config.php';
$user = require_login();
$uid = $user['pengguna_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $mysqli->prepare("SELECT pengingat_id, jenis_pengingat, waktu_pengingat, status FROM pengingat_selfcare WHERE pengguna_id=? ORDER BY pengingat_id DESC");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  echo json_encode(['items' => $res->fetch_all(MYSQLI_ASSOC)]);
  exit;
}

$in = json_input();
$action = $in['action'] ?? 'create';
if ($action === 'create') {
  $jenis = trim($in['jenis_pengingat'] ?? '');
  $waktu = trim($in['waktu_pengingat'] ?? '');
  if (!$jenis || !$waktu) {
    echo json_encode(['error' => 'Lengkapi data']);
    exit;
  }
  $stmt = $mysqli->prepare("INSERT INTO pengingat_selfcare (pengguna_id, jenis_pengingat, waktu_pengingat, status) VALUES (?, ?, ?, 1)");
  $stmt->bind_param('iss', $uid, $jenis, $waktu);
  $ok = $stmt->execute();
  echo json_encode($ok ? ['success' => true] : ['error' => 'Gagal insert pengingat']);
  exit;
}
echo json_encode(['error' => 'Unsupported action']);
