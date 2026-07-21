<?php
// Helper for the stock_movements audit trail.
//
// Callers do their own stock UPDATE/INSERT (there are already three
// different patterns — restock confirm, manual save, delete) and
// then call recordStockMovement() with the computed delta and the
// resulting on-hand quantity. Same-transaction: pass the same $pdo
// that's already inside a beginTransaction()/commit() window when
// the mutation is transactional.
//
// reason_code is one of the constants below; anything else is
// technically allowed but won't be recognised by the history UI.

const STOCK_MOVEMENT_RESTOCK_CONFIRM = 'restock_confirm';
const STOCK_MOVEMENT_MANUAL_SAVE     = 'manual_save';
const STOCK_MOVEMENT_STOCK_DELETED   = 'stock_deleted';

function recordStockMovement(
    PDO $pdo,
    ?int $stockId,
    string $productName,
    int $delta,
    int $quantityAfter,
    string $reasonCode,
    ?string $reasonText = null,
    ?string $sourceType = null,
    ?int $sourceId = null
): void {
    $userId = $_SESSION['user_id'] ?? null;
    $username = null;
    if ($userId && function_exists('currentUser')) {
        $u = currentUser();
        $username = $u['username'] ?? null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stock_movements
         (stock_id, product_name, delta, quantity_after, reason_code, reason_text, source_type, source_id, user_id, username)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $stockId,
        $productName,
        $delta,
        $quantityAfter,
        $reasonCode,
        $reasonText !== null && $reasonText !== '' ? $reasonText : null,
        $sourceType,
        $sourceId,
        $userId,
        $username,
    ]);
}
