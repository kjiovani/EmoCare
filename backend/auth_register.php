<?php
require_once __DIR__ . '/config.php';

// Ambil input POST
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi dasar
if ($username === '' || $email === '' || $password === '') {
  $_SESSION['flash'] = 'Semua field wajib diisi.';
  header('Location: ../register.html');
  exit;
}
if (strlen($username) < 8) {
  $_SESSION['flash'] = 'Username minimal 8 karakter.';
  header('Location: ../register.html');
  exit;
}
if (!preg_match('/@gmail\.com$/', $email)) {
  $_SESSION['flash'] = 'Gunakan email @gmail.com.';
  header('Location: ../register.html');
  exit;
}
if (strlen($password) < 8 || !preg_match('/[^a-zA-Z0-9]/', $password)) {
  $_SESSION['flash'] = 'Password minimal 8 karakter & mengandung simbol.';
  header('Location: ../register.html');
  exit;
}

// Cek duplikasi
$cek = $mysqli->prepare("SELECT 1 FROM pengguna WHERE nama=? OR email=? LIMIT 1");
$cek->bind_param('ss', $username, $email);
$cek->execute();
$cek->store_result();

if ($cek->num_rows > 0) {
  $cek->close();
  $_SESSION['flash'] = 'Username atau email sudah digunakan.';
  header('Location: ../register.html');
  exit;
}
$cek->close();

// Insert
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $mysqli->prepare("INSERT INTO pengguna (nama, password_hash, email) VALUES (?, ?, ?)");
$ins->bind_param('sss', $username, $hash, $email);

if (!$ins->execute()) {
  $_SESSION['flash'] = 'Gagal mendaftar: ' . $mysqli->error;
  $ins->close();
  header('Location: ../register.html');
  exit;
}
$ins->close();

$_SESSION['user'] = [
  'pengguna_id' => $mysqli->insert_id,
  'nama' => $username,
  'email' => $email,
];

header('Location: ../login.html');
exit;
