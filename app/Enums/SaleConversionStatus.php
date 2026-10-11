<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Display-only status derived from whether a Sale has a SaleConversion. Never stored.
 */
enum SaleConversionStatus: string implements HasColor, HasLabel
{
    case Unconverted = 'unconverted';
    case Converted = 'converted';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Unconverted => 'Unconverted',
            self::Converted => 'Converted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unconverted => 'warning',
            self::Converted => 'success',
        };
    }
}
