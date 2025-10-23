<?php
// backend/admin_auth_login.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/* —— CSRF —— */
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  header('Location: /admin_login.php?err=csrf');
  exit;
}

/* —— Throttle sederhana (per sesi) —— */
$_SESSION['admin_login_try'] = $_SESSION['admin_login_try'] ?? 0;
$_SESSION['admin_login_at'] = $_SESSION['admin_login_at'] ?? 0;

$now = time();
if ($_SESSION['admin_login_try'] >= 5 && ($now - $_SESSION['admin_login_at']) < 60) {
  header('Location: /admin_login.php?err=throttle');
  exit;
}

$user = trim($_POST['user'] ?? '');
$pass = (string) ($_POST['pass'] ?? '');

if ($user === '' || $pass === '') {
  header('Location: /admin_login.php?err=invalid');
  exit;
}

/* 
   Skema asumsi:
   Tabel: pengguna
   Kolom minimal: pengguna_id (int), nama (varchar), email (varchar), username (varchar), password_hash (varchar), role (enum: admin|user), is_active (tinyint)
*/
$sql = "
  SELECT pengguna_id, nama, email, username, password_hash, role, COALESCE(is_active,1) AS is_active
  FROM pengguna
  WHERE (email = ? OR username = ?)
  LIMIT 1
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $user, $user);
$stmt->execute();
$res = $stmt->get_result();
$acc = $res->fetch_assoc();
$stmt->close();

$ok = false;
if ($acc && (int) $acc['is_active'] === 1 && $acc['role'] === 'admin') {
  // Password check
  $ok = password_verify($pass, (string) $acc['password_hash']);
}

if (!$ok) {
  // catat percobaan
  $_SESSION['admin_login_try'] = ($_SESSION['admin_login_try'] ?? 0) + 1;
  $_SESSION['admin_login_at'] = time();
  // pesan berbeda untuk nonaktif
  if ($acc && (int) $acc['is_active'] !== 1) {
    header('Location: /admin_login.php?err=inactive');
    exit;
  }
  header('Location: /admin_login.php?err=invalid');
  exit;
}

// Sukses: reset throttle
$_SESSION['admin_login_try'] = 0;
$_SESSION['admin_login_at'] = 0;

// Set session aplikasi (sesuai yang dipakai di project-mu)
$_SESSION['user'] = [
  'pengguna_id' => (int) $acc['pengguna_id'],
  'nama' => (string) $acc['nama'],
  'role' => (string) $acc['role'],
  'email' => (string) $acc['email'],
  'username' => (string) $acc['username'],
];

// Optional: regenerate session id
session_regenerate_id(true);

// Redirect ke dashboard admin
header('Location: /admin/dashboard.php');
exit;
