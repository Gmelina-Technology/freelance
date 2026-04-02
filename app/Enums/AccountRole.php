<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AccountRole: string implements HasLabel
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_combine(
            self::values(),
            array_map(fn (self $role): string => $role->getLabel(), self::cases()),
        );
    }
}
