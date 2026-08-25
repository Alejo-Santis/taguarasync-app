<?php

namespace App\Support;

class PresentationQuantity
{
    /**
     * Infers the piece/container count a presentation name implies (e.g. "Caja x 20" -> 20),
     * so it can be cross-checked against the stored minimum_unit_quantity and catch typos like
     * naming a box "x 20" while saving quantity 1. A number attached directly to a volume/weight
     * suffix ("120ml", "60g") describes container content, not a piece count, so those names imply
     * a single, unsplit container (1) rather than the number itself.
     *
     * Returns null when the name doesn't encode a countable quantity (e.g. "Ampolla"), meaning no
     * cross-check should be applied.
     */
    public static function expectedFromName(string $name): ?int
    {
        $normalized = trim($name);

        if (preg_match('/^unidad(es)?$/iu', $normalized)) {
            return 1;
        }

        if (! preg_match('/x\s*(\d+)([a-zA-Z]*)/i', $normalized, $matches)) {
            return null;
        }

        if ($matches[2] !== '') {
            return 1;
        }

        return (int) $matches[1];
    }
}
