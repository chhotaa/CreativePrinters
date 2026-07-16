<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/attachments.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = ?');
$stmt->execute([$id]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    die('Attachment not found.');
}

$moduleByRecordType = ['delivery' => 'deliveries', 'job_card' => 'job_cards'];
$module = $moduleByRecordType[$attachment['record_type']] ?? null;

if (!$module || !hasPermission($module, 'view')) {
    http_response_code(403);
    die('Access denied.');
}

$path = ATTACHMENTS_DIR . '/' . $attachment['stored_filename'];
if (!is_file($path)) {
    http_response_code(404);
    die('File not found on server.');
}

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($attachment['original_filename']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
