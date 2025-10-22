<?php
// admin/_init.php
require_once __DIR__ . '/../backend/config.php'; // starts session, exposes $mysqli, require_login()
require_login();

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}


function current_user_id(){ return (int)($_SESSION['user']['pengguna_id'] ?? 0); }

function require_admin(mysqli $db){
  $uid = current_user_id();
  if ($uid <= 0) { http_response_code(403); exit('Forbidden'); }
  $stmt = $db->prepare("SELECT role FROM pengguna WHERE pengguna_id=? LIMIT 1");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $role = $stmt->get_result()->fetch_column();
  $stmt->close();
  if ($role !== 'admin') { http_response_code(403); exit('Forbidden: Admin only'); }
}
require_admin($mysqli);

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf'];
