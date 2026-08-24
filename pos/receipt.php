<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT s.*, u.full_name AS cashier_name, p.name AS pos_name
                        FROM pos_sales s JOIN users u ON u.id = s.cashier_id JOIN pos_terminals p ON p.id = s.pos_id
                        WHERE s.id = ?');
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) die('پسوولە نەدۆزرایەوە');

$itemsStmt = db()->prepare('SELECT * FROM pos_sale_items WHERE sale_id = ?');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پسوولە <?= htmlspecialchars($sale['invoice_number']) ?></title>
<style>
body{font-family:monospace;width:300px;margin:10px auto;font-size:13px}
h2{text-align:center;margin:4px 0}
table{width:100%;border-collapse:collapse;margin-top:8px}
td,th{padding:3px 0;font-size:12px}
.line{border-top:1px dashed #000;margin:6px 0}
.total{font-weight:bold;font-size:15px;text-align:center;margin-top:8px}
</style>
</head>
<body onload="window.print()">
  <h2>aza</h2>
  <div style="text-align:center">POS: <?= htmlspecialchars($sale['pos_name']) ?></div>
  <div style="text-align:center">کاشێر: <?= htmlspecialchars($sale['cashier_name']) ?></div>
  <div style="text-align:center"><?= date('Y-m-d H:i', strtotime($sale['created_at'])) ?></div>
  <div style="text-align:center">#<?= htmlspecialchars($sale['invoice_number']) ?></div>
  <div class="line"></div>
  <table>
    <?php foreach ($items as $it): ?>
      <tr><td colspan="3"><?= htmlspecialchars($it['name']) ?></td></tr>
      <tr>
        <td><?= $it['qty'] ?> × <?= number_format($it['sale_price']) ?></td>
        <td></td>
        <td style="text-align:left"><?= number_format($it['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="line"></div>
  <div class="total">کۆی گشتی: <?= number_format($sale['total_amount']) ?> د.ع</div>
  <div class="line"></div>
  <div style="text-align:center">سوپاس بۆ سەردانتان 🙏</div>
</body>
</html>
