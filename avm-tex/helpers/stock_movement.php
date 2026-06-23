<?php
declare(strict_types=1);

/**
 * Stock movement audit helpers.
 */

/**
 * Record one stock movement row in the audit trail.
 */
function recordStockMovement(
    PDO $pdo,
    string $movementType,
    int $productId,
    float $quantityBefore,
    float $quantityAfter,
    float $quantityChanged,
    string $referenceType,
    ?int $referenceId = null,
    ?string $notes = null
): void {
    $validTypes = ['initial', 'purchase', 'sale', 'adjustment', 'return', 'delete'];
    if (!in_array($movementType, $validTypes, true)) {
        throw new InvalidArgumentException('Invalid stock movement type.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stock_movements (
            movement_type, product_id, quantity_before, quantity_after,
            quantity_changed, reference_type, reference_id, notes
         ) VALUES (
            :movement_type, :product_id, :quantity_before, :quantity_after,
            :quantity_changed, :reference_type, :reference_id, :notes
         )'
    );
    $stmt->execute([
        ':movement_type' => $movementType,
        ':product_id' => $productId,
        ':quantity_before' => $quantityBefore,
        ':quantity_after' => $quantityAfter,
        ':quantity_changed' => $quantityChanged,
        ':reference_type' => $referenceType,
        ':reference_id' => $referenceId,
        ':notes' => $notes,
    ]);
}
