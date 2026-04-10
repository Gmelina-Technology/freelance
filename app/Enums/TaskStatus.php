<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskStatus: string implements HasLabel
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case COMPLETED = 'completed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::REVIEW => 'Review',
            self::COMPLETED => 'Completed',
        };
    }
}
