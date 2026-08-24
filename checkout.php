<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw || empty($raw['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $raw['csrf'])) {
    http_response_code(400);
    echo json_encode(['message' => 'CSRF token هەڵەیە']);
    exit;
}

$posId = (int)($raw['pos_id'] ?? 0);
$lines = $raw['lines'] ?? [];
if (!$posId || !$lines) {
    http_response_code(400);
    echo json_encode(['message' => 'زانیاری تەواو نییە']);
    exit;
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO pos_sales (invoice_number, pos_id, cashier_id, total_amount) VALUES (?,?,?,?)')
        ->execute(['TEMP', $posId, $_SESSION['user_id'], 0]);
    $saleId = (int)$pdo->lastInsertId();
    $invNumber = 'S-' . str_pad($saleId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE pos_sales SET invoice_number = ? WHERE id = ?')->execute([$invNumber, $saleId]);

    $matStmt = $pdo->prepare('SELECT * FROM materials WHERE id = ? FOR UPDATE');
    $insItem = $pdo->prepare('INSERT INTO pos_sale_items (sale_id, material_id, barcode, name, qty, sale_price, cost_at_sale, line_total) VALUES (?,?,?,?,?,?,?,?)');
    $updMat  = $pdo->prepare('UPDATE materials SET quantity=?, is_stopped=?, stopped_reason=?, stopped_at=? WHERE id=?');
    $insMove = $pdo->prepare('INSERT INTO stock_movements (material_id, change_qty, movement_type, ref_table, ref_id, created_by) VALUES (?,?,?,?,?,?)');

    $grandTotal = 0;
    foreach ($lines as $line) {
        $materialId = (int)($line['material_id'] ?? 0);
        $qty   = (float)($line['qty'] ?? 0);
        $price = (float)($line['price'] ?? 0);
        if ($materialId <= 0 || $qty <= 0) continue;

        $matStmt->execute([$materialId]);
        $mat = $matStmt->fetch();
        if (!$mat) continue;
        if ($qty > (float)$mat['quantity']) {
            throw new Exception('بڕی مادە بەردەست نییە: ' . $mat['name']);
        }

        $lineTotal = $qty * $price;
        $grandTotal += $lineTotal;

        $insItem->execute([$saleId, $materialId, $mat['barcode'], $mat['name'], $qty, $price, $mat['cost'], $lineTotal]);

        $newQty = (float)$mat['quantity'] - $qty;
        if ($newQty <= 0) {
            $updMat->execute([$newQty, 1, 'sale', date('Y-m-d H:i:s'), $materialId]);
        } else {
            $updMat->execute([$newQty, 0, null, null, $materialId]);
        }
        $insMove->execute([$materialId, -$qty, 'sale', 'pos_sales', $saleId, $_SESSION['user_id']]);
    }

    $pdo->prepare('UPDATE pos_sales SET total_amount = ? WHERE id = ?')->execute([$grandTotal, $saleId]);
    $pdo->commit();

    echo json_encode(['sale_id' => $saleId, 'invoice_number' => $invNumber]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
