<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum QuoteStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Void = 'void';

    /** Legacy: quotes converted directly to an invoice before the task-based workflow. */
    case Converted = 'converted';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Void => 'Void',
            self::Converted => 'Converted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'primary',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::Expired => 'warning',
            self::Void => 'gray',
            self::Converted => 'gray',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Accepted, self::Void],
            self::Sent => [self::Accepted, self::Declined, self::Expired, self::Void],
            self::Accepted, self::Declined, self::Expired, self::Void, self::Converted => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Draft quotes are editable; declined and expired quotes can be revised too.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Declined, self::Expired], true);
    }
}
