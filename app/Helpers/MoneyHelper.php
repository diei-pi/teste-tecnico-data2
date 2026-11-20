<?php

namespace App\Helpers;

class MoneyHelper
{
    /**
     * Parse messy currency strings into float numbers.
     * Examples: "720 mil" -> 720000, "R$3.500/mês" -> 3500, "1.150.000" -> 1150000, "4.500,00" -> 4500
     */
    public static function parseMoneyFromString(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $str = trim($value);

        // Check for "mil" notation (e.g. "720 mil", "410 mil")
        if (preg_match('/([\d\.,]+)\s*mil/i', $str, $matches)) {
            $num = str_replace(',', '.', str_replace('.', '', $matches[1]));
            return (float) $num * 1000;
        }

        // Clean out noise: "R$", "/mês", "/mes", spaces
        $str = preg_replace('/[R\$\s\/mês\/mes]/iu', '', $str);

        // Check pattern: Brazilian format with comma decimal (e.g. "4.500,00", "980.000,00", "1.900,00")
        if (preg_match('/^(\d{1,3}(\.\d{3})*),(\d{2})$/', $str, $matches)) {
            $cleaned = str_replace('.', '', $matches[1]) . '.' . $matches[3];
            return (float) $cleaned;
        }

        // Comma as decimal without thousand dot (e.g. "4500,00")
        if (preg_match('/^(\d+),(\d{2})$/', $str, $matches)) {
            return (float) ($matches[1] . '.' . $matches[2]);
        }

        // Multiple dots as thousand separators without comma (e.g. "1.150.000")
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $str)) {
            return (float) str_replace('.', '', $str);
        }

        // Single dot with 3 digits (e.g. "2.800" -> 2800)
        if (preg_match('/^(\d+)\.(\d{3})$/', $str, $matches)) {
            return (float) ($matches[1] . $matches[2]);
        }

        // Standard dot decimal or plain integer (e.g. "1450000", "1600")
        $cleaned = preg_replace('/[^\d\.]/', '', $str);
        return $cleaned !== '' ? (float) $cleaned : null;
    }
}
