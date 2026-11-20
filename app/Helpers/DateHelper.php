<?php

namespace App\Helpers;

use Carbon\Carbon;
use Exception;

class DateHelper
{
    /**
     * Parse date string into Carbon instance.
     */
    public static function parseDateFromString(?string $dateStr): ?Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        $str = trim($dateStr);
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'];
        foreach ($formats as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $str);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }
}
