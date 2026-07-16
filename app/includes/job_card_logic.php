<?php
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/attachments.php';
$message = $message ?? '';
$error = $error ?? '';
$canEdit = hasPermission('job_cards', 'edit');

$allowedOrderTypes = ['Sample', 'Bulk Production', 'Repeat Order'];
$allowedPlateTypes = ['New', 'Old'];
$allowedDiePunching = ['New', 'Old'];

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_job_card'])) {
        $jobDate = $_POST['job_date'] ?: date('Y-m-d');
        $productName = trim($_POST['product_name'] ?? '');
        $designName = trim($_POST['design_name'] ?? '');
        $boardNameGsm = trim($_POST['board_name_gsm'] ?? '');
        $boardSize = trim($_POST['board_size'] ?? '');
        $cuttingSize = trim($_POST['cutting_size'] ?? '');
        $boardQuantity = trim($_POST['board_quantity'] ?? '');
        $copies = trim($_POST['copies'] ?? '');
        $colour = trim($_POST['colour'] ?? '');
        $laminationVarnish = trim($_POST['lamination_varnish'] ?? '');
        $orderType = $_POST['order_type'] ?? '';
        $plateType = $_POST['plate_type'] ?? '';
        $diePunching = $_POST['die_punching'] ?? '';
        $pastingPerforation = isset($_POST['pasting_perforation']) ? 1 : 0;
        $pastingDoubleBoard = isset($_POST['pasting_double_board']) ? 1 : 0;
        $details = trim($_POST['details'] ?? '');

        if ($productName === '') {
            $error = 'Name is required.';
        } elseif (!in_array($orderType, $allowedOrderTypes, true)) {
            $error = 'Please select a valid order type.';
        } elseif (!in_array($plateType, $allowedPlateTypes, true)) {
            $error = 'Please select a valid plate type.';
        } elseif ($diePunching !== '' && !in_array($diePunching, $allowedDiePunching, true)) {
            $error = 'Please select a valid die punching option.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO job_cards
                    (job_date, product_name, design_name, board_name_gsm, board_size, cutting_size,
                     board_quantity, copies, colour, lamination_varnish, order_type, plate_type,
                     die_punching, pasting_perforation, pasting_double_board, details, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $jobDate, $productName, $designName ?: null, $boardNameGsm ?: null, $boardSize ?: null,
                $cuttingSize ?: null, $boardQuantity ?: null, $copies ?: null, $colour ?: null,
                $laminationVarnish ?: null, $orderType, $plateType, $diePunching ?: null,
                $pastingPerforation, $pastingDoubleBoard, $details ?: null, $_SESSION['user_id'],
            ]);
            $newId = (string)$pdo->lastInsertId();
            setFlashMessage('Job card #' . str_pad($newId, 2, '0', STR_PAD_LEFT) . ' created.');
            logActivity('create_job_card', 'Created Job Card #' . str_pad($newId, 2, '0', STR_PAD_LEFT) . " (\"$productName\").");
            header('Location: job_cards.php');
            exit;
        }
    } elseif (isset($_POST['update_job_card'])) {
        if (!$canEdit) {
            $error = 'You do not have permission to edit job cards.';
        } else {
            $id = (int)$_POST['job_card_id'];
            $jobDate = $_POST['job_date'] ?: date('Y-m-d');
            $productName = trim($_POST['product_name'] ?? '');
            $designName = trim($_POST['design_name'] ?? '');
            $boardNameGsm = trim($_POST['board_name_gsm'] ?? '');
            $boardSize = trim($_POST['board_size'] ?? '');
            $cuttingSize = trim($_POST['cutting_size'] ?? '');
            $boardQuantity = trim($_POST['board_quantity'] ?? '');
            $copies = trim($_POST['copies'] ?? '');
            $colour = trim($_POST['colour'] ?? '');
            $laminationVarnish = trim($_POST['lamination_varnish'] ?? '');
            $orderType = $_POST['order_type'] ?? '';
            $plateType = $_POST['plate_type'] ?? '';
            $diePunching = $_POST['die_punching'] ?? '';
            $pastingPerforation = isset($_POST['pasting_perforation']) ? 1 : 0;
            $pastingDoubleBoard = isset($_POST['pasting_double_board']) ? 1 : 0;
            $details = trim($_POST['details'] ?? '');

            if ($productName === '') {
                $error = 'Name is required.';
            } elseif (!in_array($orderType, $allowedOrderTypes, true)) {
                $error = 'Please select a valid order type.';
            } elseif (!in_array($plateType, $allowedPlateTypes, true)) {
                $error = 'Please select a valid plate type.';
            } elseif ($diePunching !== '' && !in_array($diePunching, $allowedDiePunching, true)) {
                $error = 'Please select a valid die punching option.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE job_cards SET
                        job_date = ?, product_name = ?, design_name = ?, board_name_gsm = ?, board_size = ?,
                        cutting_size = ?, board_quantity = ?, copies = ?, colour = ?, lamination_varnish = ?,
                        order_type = ?, plate_type = ?, die_punching = ?, pasting_perforation = ?, pasting_double_board = ?,
                        details = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $jobDate, $productName, $designName ?: null, $boardNameGsm ?: null, $boardSize ?: null,
                    $cuttingSize ?: null, $boardQuantity ?: null, $copies ?: null, $colour ?: null,
                    $laminationVarnish ?: null, $orderType, $plateType, $diePunching ?: null,
                    $pastingPerforation, $pastingDoubleBoard, $details ?: null, $id,
                ]);
                setFlashMessage('Job card #' . str_pad((string)$id, 2, '0', STR_PAD_LEFT) . ' updated.');
                logActivity('update_job_card', 'Updated Job Card #' . str_pad((string)$id, 2, '0', STR_PAD_LEFT) . " (\"$productName\").");
                header('Location: job_cards.php');
                exit;
            }
        }
    } elseif (isset($_POST['delete_job_card'])) {
        if (!$canEdit) {
            $error = 'You do not have permission to delete job cards.';
        } else {
            $id = (int)$_POST['job_card_id'];
            $nameStmt = $pdo->prepare('SELECT product_name FROM job_cards WHERE id = ?');
            $nameStmt->execute([$id]);
            $deletedProductName = $nameStmt->fetchColumn();
            $stmt = $pdo->prepare('DELETE FROM job_cards WHERE id = ?');
            $stmt->execute([$id]);
            setFlashMessage('Job card #' . str_pad((string)$id, 2, '0', STR_PAD_LEFT) . ' deleted.');
            logActivity('delete_job_card', 'Deleted Job Card #' . str_pad((string)$id, 2, '0', STR_PAD_LEFT) . " (\"$deletedProductName\").");
            header('Location: job_cards.php');
            exit;
        }
    } elseif (isset($_POST['upload_attachment'])) {
        $id = (int)$_POST['job_card_id'];
        $uploadError = saveAttachment('job_card', $id, $_FILES['attachment'] ?? []);
        if ($uploadError) {
            $error = $uploadError;
        } else {
            setFlashMessage('Attachment uploaded.');
            logActivity('upload_attachment', "Uploaded attachment \"{$_FILES['attachment']['name']}\" to Job Card #$id.");
            header('Location: job_cards.php');
            exit;
        }
    } elseif (isset($_POST['delete_attachment'])) {
        $attachmentId = (int)$_POST['attachment_id'];
        if (deleteAttachment($attachmentId)) {
            setFlashMessage('Attachment deleted.');
            logActivity('delete_attachment', "Deleted attachment #$attachmentId from a Job Card.");
        }
        header('Location: job_cards.php');
        exit;
    }
}

$editJobCard = null;
if ($canEdit && isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM job_cards WHERE id = ?');
    $editStmt->execute([(int)$_GET['edit']]);
    $editJobCard = $editStmt->fetch();
}

$jobCards = $pdo->query('SELECT * FROM job_cards ORDER BY id DESC')->fetchAll();

$jobCardAttachments = [];
$allJobCardAttachments = $pdo->query("SELECT * FROM attachments WHERE record_type = 'job_card' ORDER BY uploaded_at DESC")->fetchAll();
foreach ($allJobCardAttachments as $a) {
    $jobCardAttachments[$a['record_id']][] = $a;
}
