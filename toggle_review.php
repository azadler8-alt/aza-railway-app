<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('purchase_review', 'edit');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id && in_array($action, ['review', 'unreview'], true)) {
    if ($action === 'review') {
        $stmt = db()->prepare('UPDATE purchase_invoices SET is_reviewed = 1, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$_SESSION['user_id'], $id]);
    } else {
        $stmt = db()->prepare('UPDATE purchase_invoices SET is_reviewed = 0, reviewed_by = NULL, reviewed_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }
}

header('Location: invoice_form.php?id=' . $id);
exit;
