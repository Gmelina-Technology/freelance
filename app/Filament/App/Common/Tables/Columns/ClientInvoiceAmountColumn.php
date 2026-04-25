<?php

namespace App\Filament\App\Common\Tables\Columns;

use Filament\Tables\Columns\TextColumn;


class ClientInvoiceAmountColumn extends TextColumn
{
    public static function make(?string $name = 'amount'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->money(fn($record) => $record->client->currency_code);
    }
}
