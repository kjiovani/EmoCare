<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die('Method Not Allowed');
}

// Lebih simpel: 1 field bisa email ATAU username
$identity = trim($_POST['identity'] ?? ($_POST['email'] ?? $_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';

if ($identity === '' || $password === '') {
  $_SESSION['flash'] = 'Isi email/username dan password.';
  header('Location: ../login.html');
  exit;
}

// Tentukan login pakai email atau username
if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
  $sql = "SELECT pengguna_id, nama, email, password_hash FROM pengguna WHERE email = ? LIMIT 1";
} else {
  $sql = "SELECT pengguna_id, nama, email, password_hash FROM pengguna WHERE nama = ? LIMIT 1";
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $identity);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
  $_SESSION['flash'] = 'Akun tidak ditemukan.';
  header('Location: ../login.html');
  exit;
}

if (!password_verify($password, $user['password_hash'])) {
  $_SESSION['flash'] = 'Password salah.';
  echo "<script>alert('Pass wrong')</script>";
  header('Location: ../login.html');
  exit;
}

// Sukses
$_SESSION['user'] = [
  'pengguna_id' => (int) $user['pengguna_id'],
  'nama' => $user['nama'],
  'email' => $user['email'],
];

header('Location: ../home.php');
exit;
