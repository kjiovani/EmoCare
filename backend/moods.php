<?php
require_once __DIR__ . '/config.php'; // pastikan file ini memanggil session_start()

// --- Helper optional jika belum ada ---
if (!function_exists('json_input')) {
  function json_input()
  {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
  }
}
if (!function_exists('respond')) {
  function respond($data, $code = 200)
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
  }
}

// Wajib login
$user = require_login();
$uid = (int) $user['pengguna_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 1000;
  $sql = "SELECT mood_id, pengguna_id, tanggal, mood_level, catatan, created_at
          FROM moodtracker
          WHERE pengguna_id=?
          ORDER BY tanggal DESC, mood_id DESC
          LIMIT ?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ii', $uid, $limit);
  $stmt->execute();
  $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  // Untuk dipakai fetch via AJAX, tetap JSON:
  header('location: ../home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Terima baik JSON maupun FORM-URLENCODED
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ctype, 'application/json') !== false) {
    $in = json_input();
    $action = $in['action'] ?? '';
    $mood = (int) ($in['mood_level'] ?? 0);
    $note = trim($in['catatan'] ?? '');
  } else {
    $action = $_POST['action'] ?? '';
    $mood = (int) ($_POST['mood_level'] ?? 0);
    $note = trim($_POST['catatan'] ?? '');
  }

  if ($action !== 'create') {
    // Kalau kamu submit form langsung tanpa JS, kita anggap create
    if (isset($_POST['mood_level'])) {
      $action = 'create';
    } else {
      respond(['error' => 'Unknown action'], 400);
    }
  }

  if ($mood < 1 || $mood > 5) {
    // Jika submit via form biasa, balik ke home dengan alert
    if (!empty($_POST)) {
      echo '<script>alert("Skala mood harus 1..5"); window.history.back();</script>';
      exit;
    }
    respond(['error' => 'mood_level harus 1..5'], 400);
  }

  $sql = "INSERT INTO moodtracker (pengguna_id, tanggal, mood_level, catatan)
          VALUES (?, CURDATE(), ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('iis', $uid, $mood, $note);
  if (!$stmt->execute()) {
    if (!empty($_POST)) {
      echo '<script>alert("Insert gagal: ' . htmlspecialchars($stmt->error) . '"); window.history.back();</script>';
      exit;
    }
    respond(['error' => 'Insert gagal: ' . $stmt->error], 500);
  }

  // Sukses: kalau form biasa → balik ke home; kalau JSON → balas JSON
  if (!empty($_POST)) {
    echo '<script>alert("Mood tersimpan!"); window.location.href = "../home.html";</script>';
    exit;
  } else {
    respond(['success' => true, 'mood_id' => $mysqli->insert_id]);
  }
}

respond(['error' => 'Method not allowed'], 405);
