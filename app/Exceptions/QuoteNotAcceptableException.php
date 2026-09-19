<?php

namespace App\Exceptions;

use App\Models\Quote;
use RuntimeException;

class QuoteNotAcceptableException extends RuntimeException
{
    public function __construct(public Quote $quote)
    {
        parent::__construct(sprintf(
            'Quote %s cannot be accepted from status "%s".',
            $quote->number,
            $quote->status->value,
        ));
    }
}
