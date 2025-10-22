<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// Ambil kuis aktif; fallback: kuis terbaru
$sql = "SELECT id, question_text, is_active, created_at FROM quiz_questions
        ORDER BY is_active DESC, id DESC LIMIT 1";
$q = $mysqli->query($sql);
$quiz = $q ? $q->fetch_assoc() : null;

$out = ['quiz' => null, 'options' => []];
if ($quiz) {
  $out['quiz'] = [
    'id' => (int) $quiz['id'],
    'question_text' => $quiz['question_text'],
    'is_active' => (int) $quiz['is_active'],
    'created_at' => $quiz['created_at'],
  ];
  $stmt = $mysqli->prepare("SELECT id, option_text, is_correct FROM quiz_options WHERE question_id=? ORDER BY id");
  $stmt->bind_param('i', $out['quiz']['id']);
  $stmt->execute();
  $out['options'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}
echo json_encode($out);
