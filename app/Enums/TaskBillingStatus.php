<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskBillingStatus: string implements HasColor, HasLabel
{
    case Billable = 'billable';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Billable => 'Billable',
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Billable => 'gray',
            self::PendingPayment => 'warning',
            self::Paid => 'success',
        };
    }

    public function isBillable(): bool
    {
        return $this === self::Billable;
    }
}
