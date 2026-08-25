<?php

namespace App\Support;

class Sanitize
{
    /**
     * Strips spaces, dots, and dashes commonly pasted into Colombian
     * NIT/cédula fields (e.g. "900.123.456-7"), while preserving letters
     * so alphanumeric IDs (passport, foreign ID) aren't corrupted.
     */
    public static function identification(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s.\-]+/', '', $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
