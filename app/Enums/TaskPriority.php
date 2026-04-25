<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskPriority: string implements HasLabel, HasColor
{
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';

    public function getLabel(): string|Htmlable|null
    {
        return $this->name;
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            $this::High => 'danger',
            $this::Medium => 'warning',
            $this::Low => 'gray'
        };
    }
}
