<?php
// Files are stored OUTSIDE public_html entirely -- same location as
// db_credentials.php (one level above the Hostinger account's
// public_html folder) -- so Apache can never serve one directly no
// matter how .htaccess is configured. All access goes through
// download_attachment.php, which enforces the RBAC permission check.
define('ATTACHMENTS_DIR', __DIR__ . '/../../../uploads');

const ATTACHMENTS_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
const ATTACHMENTS_MAX_SIZE = 5 * 1024 * 1024;

function getAttachments($recordType, $recordId) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM attachments WHERE record_type = ? AND record_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$recordType, $recordId]);
    return $stmt->fetchAll();
}

function saveAttachment($recordType, $recordId, array $file) {
    global $pdo;

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed. Please try again.';
    }
    if ($file['size'] > ATTACHMENTS_MAX_SIZE) {
        return 'File is too large (max 5MB).';
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Invalid upload.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(ATTACHMENTS_ALLOWED_TYPES[$actualMimeType])) {
        return 'Unsupported file type. Only JPG, PNG, GIF, WEBP, and PDF are allowed.';
    }

    if (!is_dir(ATTACHMENTS_DIR)) {
        mkdir(ATTACHMENTS_DIR, 0700, true);
    }

    $extension = ATTACHMENTS_ALLOWED_TYPES[$actualMimeType];
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = ATTACHMENTS_DIR . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return 'Could not save the uploaded file.';
    }

    $originalFilename = $file['name'];
    $stmt = $pdo->prepare(
        'INSERT INTO attachments (record_type, record_id, original_filename, stored_filename, mime_type, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$recordType, $recordId, $originalFilename, $storedFilename, $actualMimeType, $file['size'], $_SESSION['user_id'] ?? null]);

    return null;
}

function deleteAttachment($id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT stored_filename FROM attachments WHERE id = ?');
    $stmt->execute([$id]);
    $attachment = $stmt->fetch();

    if (!$attachment) {
        return false;
    }

    $path = ATTACHMENTS_DIR . '/' . $attachment['stored_filename'];
    if (is_file($path)) {
        unlink($path);
    }

    $del = $pdo->prepare('DELETE FROM attachments WHERE id = ?');
    $del->execute([$id]);
    return true;
}
