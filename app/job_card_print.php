<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requirePermission('job_cards', 'view');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM job_cards WHERE id = ?');
$stmt->execute([$id]);
$jobCard = $stmt->fetch();

if (!$jobCard) {
    http_response_code(404);
    die('Job card not found.');
}

function jcCheck($checked) {
    return $checked ? '<span class="tick">&#10003;</span>' : '';
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
    @font-face {
        font-family: 'Cervino Semi Bold Neue';
        src: url('fonts/Cervino-SemiBoldNeue.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    @page { size: 172mm 185mm; margin: 5mm; }
    body { font-family: 'Cervino Semi Bold Neue', Arial, sans-serif; font-size: 12pt; margin: 0; padding: 20px; background: #f0f0f0; color: #1a1a1a; }
    .sheet { max-width: 172mm; min-height: 160mm; margin: 0 auto; background: #fff; border: 2px solid #1a1a1a; border-radius: 10px; padding: 10px; overflow: hidden; display: flex; flex-direction: column; }
    .header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
    .company-logo { max-width: 330px; height: auto; }
    .meta { text-align: right; }
    .meta div { margin-bottom: 3px; }
    .meta .dotted { display: inline-block; min-width: 100px; border-bottom: 1px dotted #999; padding-bottom: 2px; }
    .title-pill { background: #1a1a1a; color: #fff; text-align: center; padding: 5px; letter-spacing: 1px; margin: 6px -10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .field-row { display: flex; margin-bottom: 5px; }
    .field-label { width: 150px; flex-shrink: 0; color: #333; }
    .field-value { flex: 1; border-bottom: 1px dotted #999; padding-bottom: 1px; white-space: pre-wrap; line-height: 1.25; }
    .checkbox-section { margin-top: 6px; padding-top: 2px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 3px; }
    .checkbox-row { display: grid; grid-template-columns: 150px 125px 26px 110px 26px 72px 26px; align-items: center; column-gap: 6px; font-size: 12pt; }
    .pill-label { background: #1a1a1a; color: #fff; padding: 6px 0 6px 14px; margin-left: -10px; border-radius: 0 999px 999px 0; font-size: 13pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .checkbox-box { width: 22px; height: 20px; border: 2px solid #1a1a1a; border-radius: 5px; position: relative; }
    .checkbox-box .tick { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 30px; font-weight: normal; line-height: 1; white-space: nowrap; }
    .print-bar { max-width: 172mm; margin: 0 auto 15px; text-align: right; }
    .print-btn { background: #9acd32; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
    .print-btn:hover { background: #7fae22; }
    @media print {
        body { background: #fff; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .no-print { display: none; }
        .sheet { border: 2px solid #000; box-shadow: none; margin: 0; max-width: 100%; min-height: 0; height: auto; }
        .checkbox-section { flex: none; }
    }
</style>
</head>
<body>
    <div class="print-bar no-print">
        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        <p style="max-width: 360px; margin-left: auto; margin-top: 6px; font-size: 12px; color: #666; text-align: right;">In the print dialog, enable <strong>"Background graphics"</strong> (in More Settings) so the black bars print. Choose <strong>"Save as PDF"</strong> as destination if you want a PDF.</p>
    </div>
    <div class="sheet">
        <div class="header">
            <img src="../image/job_card_header.png" alt="Creative Printing Solution" class="company-logo">
            <div class="meta">
                <div>Sl.No. : <span class="dotted"><?= str_pad((string)$jobCard['id'], 2, '0', STR_PAD_LEFT) ?></span></div>
                <div>Date : <span class="dotted"><?= htmlspecialchars(date('d.m.Y', strtotime($jobCard['job_date']))) ?></span></div>
            </div>
        </div>
        <div class="title-pill">JOB CARD</div>
        <div class="fields">
            <div class="field-row"><div class="field-label">Name</div><div class="field-value">: <?= htmlspecialchars($jobCard['product_name']) ?></div></div>
            <div class="field-row"><div class="field-label">Design Name</div><div class="field-value">: <?= htmlspecialchars($jobCard['design_name'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Board Name / Gsm</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_name_gsm'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Board Size</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_size'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Cutting Size</div><div class="field-value">: <?= htmlspecialchars($jobCard['cutting_size'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Board Quantity</div><div class="field-value">: <?= htmlspecialchars($jobCard['board_quantity'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Copies</div><div class="field-value">: <?= htmlspecialchars($jobCard['copies'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Colour</div><div class="field-value">: <?= htmlspecialchars($jobCard['colour'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Lamination / Varnish</div><div class="field-value">: <?= htmlspecialchars($jobCard['lamination_varnish'] ?? '') ?></div></div>
            <div class="field-row"><div class="field-label">Details</div><div class="field-value"><?= $jobCard['details'] !== null && $jobCard['details'] !== '' ? htmlspecialchars($jobCard['details']) : '' ?></div></div>
        </div>
        <div class="checkbox-section">
            <div class="checkbox-row">
                <span class="pill-label">Order</span>
                <span>Bulk Production</span><span class="checkbox-box"><?= jcCheck($jobCard['order_type'] === 'Bulk Production') ?></span>
                <span>Repeat order</span><span class="checkbox-box"><?= jcCheck($jobCard['order_type'] === 'Repeat Order') ?></span>
                <span>Sample</span><span class="checkbox-box"><?= jcCheck($jobCard['order_type'] === 'Sample') ?></span>
            </div>
            <div class="checkbox-row">
                <span class="pill-label">Plate</span>
                <span>New</span><span class="checkbox-box"><?= jcCheck($jobCard['plate_type'] === 'New') ?></span>
                <span>Old</span><span class="checkbox-box"><?= jcCheck($jobCard['plate_type'] === 'Old') ?></span>
                <span></span><span></span>
            </div>
            <div class="checkbox-row">
                <span class="pill-label">Die Punching</span>
                <span>New</span><span class="checkbox-box"><?= jcCheck($jobCard['die_punching'] === 'New') ?></span>
                <span>Old</span><span class="checkbox-box"><?= jcCheck($jobCard['die_punching'] === 'Old') ?></span>
                <span></span><span></span>
            </div>
            <div class="checkbox-row">
                <span class="pill-label">Pasting</span>
                <span>Perforation</span><span class="checkbox-box"><?= jcCheck((bool)$jobCard['pasting_perforation']) ?></span>
                <span>Double Board</span><span class="checkbox-box"><?= jcCheck((bool)$jobCard['pasting_double_board']) ?></span>
                <span></span><span></span>
            </div>
        </div>
    </div>
</body>
</html>
