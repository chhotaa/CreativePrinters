<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM job_cards WHERE id = ?');
$stmt->execute([$id]);
$jobCard = $stmt->fetch();

if (!$jobCard) {
    http_response_code(404);
    die('Job card not found.');
}

function jcCheck($checked) {
    return $checked ? '&#10003;' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Card #<?= str_pad((string)$jobCard['id'], 2, '0', STR_PAD_LEFT) ?> - Creative Printers</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #f0f0f0; color: #1a1a1a; }
    .sheet { max-width: 900px; margin: 0 auto; background: #fff; border: 2px solid #1a1a1a; border-radius: 10px; padding: 30px; }
    .header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
    .company { display: flex; align-items: center; gap: 16px; }
    .company img { max-width: 90px; height: auto; flex-shrink: 0; }
    .company-address { font-size: 13px; color: #333; line-height: 1.5; }
    .meta { text-align: right; font-size: 15px; }
    .meta div { margin-bottom: 10px; }
    .meta .dotted { display: inline-block; min-width: 100px; border-bottom: 1px dotted #999; padding-bottom: 2px; font-weight: bold; }
    .divider { border: none; border-top: 2px solid #1a1a1a; margin: 20px 0; }
    .title-pill { background: #1a1a1a; color: #fff; text-align: center; padding: 8px; font-weight: bold; letter-spacing: 1px; border-radius: 4px; margin-bottom: 25px; }
    .body-grid { display: flex; gap: 30px; flex-wrap: wrap; }
    .fields { flex: 1.6; min-width: 280px; }
    .field-row { display: flex; margin-bottom: 28px; }
    .field-label { width: 150px; flex-shrink: 0; color: #333; }
    .field-value { flex: 1; border-bottom: 1px dotted #999; font-weight: bold; padding-bottom: 3px; }
    .options { flex: 1; min-width: 220px; }
    .option-group-label { background: #1a1a1a; color: #fff; display: inline-block; padding: 4px 10px; font-weight: bold; font-size: 13px; border-radius: 3px; margin-bottom: 8px; margin-top: 12px; }
    .option-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; }
    .option-box { width: 60px; height: 24px; border: 1px solid #333; border-radius: 3px; text-align: center; font-weight: bold; }
    .footer-title { background: #1a1a1a; color: #fff; text-align: center; padding: 8px; font-weight: bold; letter-spacing: 1px; border-radius: 4px; margin-top: 30px; }
    .details-box { min-height: 100px; padding: 12px 4px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
    .print-bar { max-width: 900px; margin: 0 auto 15px; text-align: right; }
    .print-btn { background: #9acd32; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
    .print-btn:hover { background: #7fae22; }
    @media print {
        body { background: #fff; padding: 0; }
        .no-print { display: none; }
        .sheet { border: 2px solid #000; box-shadow: none; margin: 0; max-width: 100%; }
    }
</style>
</head>
<body>
    <div class="print-bar no-print">
        <button class="print-btn" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <div class="header">
            <div class="company">
                <img src="../image/Creative Card.cdr New_Page_1.jpg" alt="Creative Printing Solution">
                <div class="company-address">
                    30/1, Rajalingapuram, Sengunthapuram 6th Cross,<br>
                    Karur- 639 002.<br>
                    M: +91 90470 07788
                </div>
            </div>
            <div class="meta">
                <div>Sl.No. : <span class="dotted"><?= str_pad((string)$jobCard['id'], 2, '0', STR_PAD_LEFT) ?></span></div>
                <div>Date : <span class="dotted"><?= htmlspecialchars(date('d.m.Y', strtotime($jobCard['job_date']))) ?></span></div>
            </div>
        </div>
        <hr class="divider">
        <div class="title-pill">JOB CARD</div>
        <div class="body-grid">
            <div class="fields">
                <div class="field-row"><div class="field-label">Name</div><div class="field-value">: <?= htmlspecialchars($jobCard['product_name']) ?></div></div>
                <div class="field-row"><div class="field-label">Design Name</div><div class="field-value">: <?= htmlspecialchars($jobCard['design_name'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Board Name/ GSM</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_name_gsm'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Board Size</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_size'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Cutting Size</div><div class="field-value">: <?= htmlspecialchars($jobCard['cutting_size'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Board Quantity</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_quantity'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Copies</div><div class="field-value">: <?= htmlspecialchars($jobCard['copies'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Colour</div><div class="field-value">: <?= htmlspecialchars($jobCard['colour'] ?? '') ?></div></div>
                <div class="field-row"><div class="field-label">Lamination / Varnish</div><div class="field-value">: <?= htmlspecialchars($jobCard['lamination_varnish'] ?? '') ?></div></div>
            </div>
            <div class="options">
                <div class="option-group-label" style="margin-top:0;">Order</div>
                <div class="option-row"><span>Sample</span><span class="option-box"><?= jcCheck($jobCard['order_type'] === 'Sample') ?></span></div>
                <div class="option-row"><span>Bulk Production</span><span class="option-box"><?= jcCheck($jobCard['order_type'] === 'Bulk Production') ?></span></div>
                <div class="option-row"><span>Repeat order</span><span class="option-box"><?= jcCheck($jobCard['order_type'] === 'Repeat Order') ?></span></div>

                <div class="option-group-label">Plate</div>
                <div class="option-row"><span>New</span><span class="option-box"><?= jcCheck($jobCard['plate_type'] === 'New') ?></span></div>
                <div class="option-row"><span>Old</span><span class="option-box"><?= jcCheck($jobCard['plate_type'] === 'Old') ?></span></div>

                <div class="option-group-label">Die Punching</div>
                <div class="option-row"><span>New</span><span class="option-box"><?= jcCheck($jobCard['die_punching'] === 'New') ?></span></div>
                <div class="option-row"><span>Old</span><span class="option-box"><?= jcCheck($jobCard['die_punching'] === 'Old') ?></span></div>

                <div class="option-group-label">Pasting</div>
                <div class="option-row"><span>Perforation</span><span class="option-box"><?= jcCheck((bool)$jobCard['pasting_perforation']) ?></span></div>
                <div class="option-row"><span>Double Board</span><span class="option-box"><?= jcCheck((bool)$jobCard['pasting_double_board']) ?></span></div>
            </div>
        </div>
        <div class="footer-title">DETAILS</div>
        <div class="details-box"><?= $jobCard['details'] !== null && $jobCard['details'] !== '' ? nl2br(htmlspecialchars($jobCard['details'])) : '&nbsp;' ?></div>
    </div>
</body>
</html>
