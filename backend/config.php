<?php
// backend/config_web.php
// WAJIB: hidupkan session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Konfigurasi DB
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';           // sesuaikan
$DB_NAME = 'emocare';    // pastikan sama dengan DB kamu

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('DB connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function require_login()
{
  if (empty($_SESSION['user']['pengguna_id'])) {
    header('Location: ../login.html');
    exit;
  }
}
