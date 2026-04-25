<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectStatus: string implements HasLabel, HasColor
{
    case BACKLOG = 'backlog';
    case IN_PROGRESS = 'in_progress';
    case BLOCKED = 'blocked';
    case COMPLETED = 'completed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::BACKLOG => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::BLOCKED => 'Blocked',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BACKLOG => 'gray',
            self::IN_PROGRESS => 'primary',
            self::BLOCKED => 'warning',
            self::COMPLETED => 'success',
        };
    }
}
