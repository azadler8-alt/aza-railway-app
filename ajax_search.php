<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$stmt = db()->prepare('SELECT id, name FROM companies WHERE is_blocked = 0 AND name LIKE ? ORDER BY name LIMIT 10');
$stmt->execute(["%$q%"]);
echo json_encode($stmt->fetchAll());
