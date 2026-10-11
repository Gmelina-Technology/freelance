<?php

namespace App\Filament\App\Common\GlobalSearch;

use Carbon\CarbonInterface;
use Illuminate\Support\Number;

final class GlobalSearchDetails
{
    /**
     * Drop details that have no value, keeping the order of the rest.
     *
     * @param  array<string, string|null>  $details
     * @return array<string, string>
     */
    public static function filled(array $details): array
    {
        return array_filter($details, fn (?string $value): bool => filled($value));
    }

    public static function date(?CarbonInterface $date): ?string
    {
        return $date?->format('M d, Y');
    }

    public static function money(string|float|int|null $amount, ?string $currencyCode): ?string
    {
        if ($amount === null) {
            return null;
        }

        $formatted = Number::format((float) $amount, 2);

        return filled($currencyCode) ? "{$currencyCode} {$formatted}" : $formatted;
    }
}
