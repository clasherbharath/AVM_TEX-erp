<?php
/**
 * Helpers: currency formatting.
 *
 * Purpose:
 * - Centralize formatting for ERP amounts (₹).
 */

declare(strict_types=1);

function format_inr(float|int $amount): string
{
    return '₹ ' . number_format((float)$amount, 2);
}

