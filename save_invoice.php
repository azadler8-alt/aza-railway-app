<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if (!has_permission('purchase', 'edit')) {
    http_response_code(403);
    echo json_encode(['message' => 'مۆڵەتت نییە']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw || empty($raw['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $raw['csrf'])) {
    http_response_code(400);
    echo json_encode(['message' => 'CSRF token هەڵەیە']);
    exit;
}

$companyId = (int)($raw['company_id'] ?? 0);
$lines     = $raw['lines'] ?? [];

if (!$companyId || !$lines) {
    http_response_code(400);
    echo json_encode(['message' => 'زانیاری تەواو نییە']);
    exit;
}

$pdo = db();
try {
    $pdo->beginTransaction();

    // 1) پشکنین کۆمپانیا بوونی هەیە و ڕەش نەکراوە
    $c = $pdo->prepare('SELECT id FROM companies WHERE id = ? AND is_blocked = 0');
    $c->execute([$companyId]);
    if (!$c->fetch()) throw new Exception('کۆمپانیا نەدۆزرایەوە');

    // 2) دروستکردنی وەسڵ بە p_number کاتی
    $pdo->prepare('INSERT INTO purchase_invoices (p_number, company_id, total_amount, created_by) VALUES (?,?,?,?)')
        ->execute(['TEMP', $companyId, 0, $_SESSION['user_id']]);
    $invoiceId = (int)$pdo->lastInsertId();
    $pNumber = 'P-' . str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE purchase_invoices SET p_number = ? WHERE id = ?')->execute([$pNumber, $invoiceId]);

    $grandTotal = 0;
    $matStmt = $pdo->prepare('SELECT * FROM materials WHERE id = ? FOR UPDATE');
    $insItem  = $pdo->prepare('INSERT INTO purchase_invoice_items
        (invoice_id, material_id, barcode, name, qty, prev_purchase_price, current_purchase_price, line_total)
        VALUES (?,?,?,?,?,?,?,?)');
    $updMat   = $pdo->prepare('UPDATE materials SET purchase_price=?, cost=?, quantity=?, is_stopped=0, stopped_reason=NULL, stopped_at=NULL WHERE id=?');
    $insMove  = $pdo->prepare('INSERT INTO stock_movements (material_id, change_qty, movement_type, ref_table, ref_id, created_by) VALUES (?,?,?,?,?,?)');

    foreach ($lines as $line) {
        $materialId = (int)($line['material_id'] ?? 0);
        $qty        = (float)($line['qty'] ?? 0);
        $price      = (float)($line['price'] ?? 0);
        if ($materialId <= 0 || $qty <= 0) continue;

        $matStmt->execute([$materialId]);
        $mat = $matStmt->fetch();
        if (!$mat) continue;

        $prevPrice = (float)$mat['purchase_price'];
        $lineTotal = $qty * $price;
        $grandTotal += $lineTotal;

        $insItem->execute([$invoiceId, $materialId, $mat['barcode'], $mat['name'], $qty, $prevPrice, $price, $lineTotal]);

        // ئەڤەرێج کۆست: (کۆی نرخی کۆنی هەبوون + کۆی نرخی کرینی نوێ) / کۆی عدد
        $oldQty = (float)$mat['quantity'];
        $oldCost = (float)$mat['cost'];
        $newQty = $oldQty + $qty;
        $newCost = $newQty > 0 ? ((($oldQty * $oldCost) + ($qty * $price)) / $newQty) : $price;

        $updMat->execute([$price, $newCost, $newQty, $materialId]);
        $insMove->execute([$materialId, $qty, 'purchase', 'purchase_invoices', $invoiceId, $_SESSION['user_id']]);
    }

    $pdo->prepare('UPDATE purchase_invoices SET total_amount = ? WHERE id = ?')->execute([$grandTotal, $invoiceId]);

    $pdo->commit();
    echo json_encode(['invoice_id' => $invoiceId, 'p_number' => $pNumber]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
