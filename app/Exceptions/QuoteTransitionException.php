<?php

namespace App\Exceptions;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use RuntimeException;

class QuoteTransitionException extends RuntimeException
{
    public static function notAllowed(Quote $quote, QuoteStatus $target): self
    {
        return new self(sprintf(
            'Quote %s cannot move from "%s" to "%s".',
            $quote->number,
            $quote->status->value,
            $target->value,
        ));
    }

    public static function notEditable(Quote $quote): self
    {
        return new self(sprintf(
            'Quote %s is "%s" and can no longer be edited. Only draft, declined or expired quotes are editable.',
            $quote->number,
            $quote->status->value,
        ));
    }

    public static function missingClientEmail(Quote $quote): self
    {
        return new self("Quote {$quote->number} cannot be sent because its client has no email address.");
    }
}
