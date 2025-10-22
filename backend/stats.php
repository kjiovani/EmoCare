<?php
require_once 'config.php';
// Anonymous stats ok; or require login:
$user = require_login();

$counts = [
  'totalUsers' => 0,
  'totalMoods' => 0,
  'totalJournals' => 0,
  'totalQuizResults' => 0,
];

$res = $mysqli->query("SELECT COUNT(*) AS c FROM pengguna WHERE peran='user'");
$counts['totalUsers'] = intval(($res && ($row = $res->fetch_assoc())) ? $row['c'] : 0);

$res = $mysqli->query("SELECT COUNT(*) AS c FROM moodtracker");
$counts['totalMoods'] = intval(($res && ($row = $res->fetch_assoc())) ? $row['c'] : 0);

$res = $mysqli->query("SELECT COUNT(*) AS c FROM jurnal_harian");
$counts['totalJournals'] = intval(($res && ($row = $res->fetch_assoc())) ? $row['c'] : 0);

$res = $mysqli->query("SELECT COUNT(*) AS c FROM hasil_kuis");
$counts['totalQuizResults'] = intval(($res && ($row = $res->fetch_assoc())) ? $row['c'] : 0);

echo json_encode($counts);
