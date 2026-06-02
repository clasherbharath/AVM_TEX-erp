<?php
/**
 * Helpers: date formatting.
 *
 * Purpose:
 * - Consistent date/time formats across modules.
 */

declare(strict_types=1);

function format_date(string $datetime, string $format = 'd M Y'): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date($format, $ts);
}

