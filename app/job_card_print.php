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
    @page { size: 170mm 185mm; margin: 0.3in; }
    body { font-family: 'Flama Condensed Medium', 'Flama Condensed', 'Arial Narrow', Arial, sans-serif; font-size: 12pt; margin: 0; padding: 20px; background: #f0f0f0; color: #1a1a1a; }
    .sheet { max-width: 170mm; margin: 0 auto; background: #fff; border: 2px solid #1a1a1a; border-radius: 10px; padding: 16px; }
    .header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
    .company-logo { max-width: 220px; height: auto; }
    .meta { text-align: right; }
    .meta div { margin-bottom: 6px; }
    .meta .dotted { display: inline-block; min-width: 100px; border-bottom: 1px dotted #999; padding-bottom: 2px; }
    .divider { border: none; border-top: 2px solid #1a1a1a; margin: 10px 0; }
    .title-pill { background: #1a1a1a; color: #fff; text-align: center; padding: 6px; letter-spacing: 1px; border-radius: 4px; margin-bottom: 10px; }
    .body-grid { display: flex; gap: 24px; flex-wrap: wrap; }
    .fields { flex: 1.6; min-width: 280px; }
    .field-row { display: flex; margin-bottom: 10px; }
    .field-label { width: 150px; flex-shrink: 0; color: #333; }
    .field-value { flex: 1; border-bottom: 1px dotted #999; padding-bottom: 3px; }
    .options { flex: 1; min-width: 220px; }
    .option-group-label { background: #1a1a1a; color: #fff; display: inline-block; padding: 3px 10px; border-radius: 3px; margin-bottom: 4px; margin-top: 6px; }
    .option-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
    .option-box { width: 60px; height: 22px; border: 1px solid #333; border-radius: 3px; text-align: center; }
    .footer-title { background: #1a1a1a; color: #fff; text-align: center; padding: 6px; letter-spacing: 1px; border-radius: 4px; margin-top: 10px; }
    .details-box { min-height: 50px; padding: 6px 4px; line-height: 1.4; white-space: pre-wrap; }
    .print-bar { max-width: 170mm; margin: 0 auto 15px; text-align: right; }
    .print-btn { background: #9acd32; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; margin-left: 8px; }
    .print-btn:hover { background: #7fae22; }
    .print-btn:disabled { opacity: 0.6; cursor: default; }
    .print-btn.pdf-btn { background: #2f4f4f; }
    .print-btn.pdf-btn:hover { background: #26403f; }
    @media print {
        body { background: #fff; padding: 0; }
        .no-print { display: none; }
        .sheet { border: 2px solid #000; box-shadow: none; margin: 0; max-width: 100%; }
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <div class="print-bar no-print">
        <button class="print-btn" onclick="window.print()">Print</button>
        <button class="print-btn pdf-btn" id="downloadPdfBtn" onclick="downloadPdf()">Download PDF</button>
    </div>
    <div class="sheet">
        <div class="header">
            <img src="../image/job_card_header.png" alt="Creative Printing Solution" class="company-logo">
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
        <div class="details-box"><?= $jobCard['details'] !== null && $jobCard['details'] !== '' ? htmlspecialchars($jobCard['details']) : '&nbsp;' ?></div>
    </div>
    <script>
        function downloadPdf() {
            var btn = document.getElementById('downloadPdfBtn');
            var originalLabel = btn.textContent;
            btn.textContent = 'Generating...';
            btn.disabled = true;
            html2pdf().set({
                margin: 0,
                filename: 'Job_Card_<?= str_pad((string)$jobCard['id'], 2, '0', STR_PAD_LEFT) ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    ignoreElements: function (el) { return el.classList.contains('no-print'); }
                },
                jsPDF: { unit: 'mm', format: [170, 185], orientation: 'portrait' }
            }).from(document.querySelector('.sheet')).save().then(function () {
                btn.textContent = originalLabel;
                btn.disabled = false;
            }).catch(function () {
                btn.textContent = originalLabel;
                btn.disabled = false;
                alert('Could not generate the PDF. Please try again.');
            });
        }
    </script>
</body>
</html>
