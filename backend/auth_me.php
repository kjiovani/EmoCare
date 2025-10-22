<?php
require_once 'config.php';
echo json_encode(['user' => $_SESSION['user'] ?? null]);
