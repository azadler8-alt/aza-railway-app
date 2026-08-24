<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$posOnly = isset($_GET['pos']); // filter بۆ POS تەنها ئەوانەی show_on_pos=1 و is_stopped=0

$sql = 'SELECT id, item_code, name, barcode, purchase_price, sale_price, cost, quantity, image_path
        FROM materials WHERE is_stopped = 0 AND (name LIKE ? OR barcode LIKE ? OR item_code LIKE ?)';
if ($posOnly) $sql .= ' AND show_on_pos = 1';
$sql .= ' LIMIT 15';

$stmt = db()->prepare($sql);
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll());
