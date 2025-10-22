<?php
require_once __DIR__ . '/config.php';

$sql = "SELECT * FROM pengguna";
$prepare = $mysqli->query($sql);
