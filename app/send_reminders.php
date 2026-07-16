<?php
// ============================================================
// This script is meant to be run automatically by a Hostinger
// Cron Job (hPanel > Advanced > Cron Jobs), NOT visited in a browser.
// See SETUP_GUIDE_PHP.md for how to schedule it.
// ============================================================
require_once __DIR__ . '/includes/db.php';

$REMINDER_DAYS_BEFORE = 3; // change this to send reminders earlier/later

$stmt = $pdo->query(
    "SELECT d.id, d.due_date, d.quantity, d.status,
            po.po_number, po.customer_name, po.item_code, po.description
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     WHERE d.status != 'Delivered' AND d.reminder_sent = 'No'"
);
$rows = $stmt->fetchAll();

$adminEmails = array_column(
    $pdo->query(
        "SELECT u.email FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'Super Admin' AND u.email IS NOT NULL AND u.email <> ''"
    )->fetchAll(),
    'email'
);

$today = new DateTime('today');
$sentCount = 0;

foreach ($rows as $row) {
    $due = new DateTime($row['due_date']);
    $diffDays = (int)$today->diff($due)->format('%r%a'); // signed day difference

    if ($diffDays === $REMINDER_DAYS_BEFORE || $diffDays === 0) {
        $subject = $diffDays === 0
            ? "Delivery due TODAY: PO {$row['po_number']}"
            : "Delivery due in {$REMINDER_DAYS_BEFORE} days: PO {$row['po_number']}";

        $body = "PO Number: {$row['po_number']}\n" .
                "Customer: {$row['customer_name']}\n" .
                "Item: {$row['item_code']} - {$row['description']}\n" .
                "Quantity due: {$row['quantity']}\n" .
                "Due Date: {$row['due_date']}\n" .
                "Status: {$row['status']}";

        $headers = "From: no-reply@creativeprintingsolution.in\r\n";

        foreach ($adminEmails as $email) {
            mail($email, $subject, $body, $headers);
            $sentCount++;
        }

        if ($diffDays === $REMINDER_DAYS_BEFORE) {
            $upd = $pdo->prepare("UPDATE deliveries SET reminder_sent = 'Yes' WHERE id = ?");
            $upd->execute([$row['id']]);
        }
    }
}

echo "Reminder check completed at " . date('Y-m-d H:i:s') . ". Emails sent: $sentCount\n";
