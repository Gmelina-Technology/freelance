<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both statements only touch rows that are still null, so running this again changes nothing.
     * updated_at is left alone so backfilled conversions don't look like user edits.
     */
    public function up(): void
    {
        DB::table('sales')
            ->whereNull('currency_code')
            ->whereNotNull('reference_key')
            ->update([
                'currency_code' => DB::raw(
                    '(select clients.currency_code from invoices
                      inner join clients on clients.id = invoices.client_id and clients.account_id = invoices.account_id
                      where invoices.number = sales.reference_key and invoices.account_id = sales.account_id)'
                ),
            ]);

        DB::table('sales')
            ->whereNull('converted_at')
            ->whereNotNull('currency_code')
            ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('accounts')
                ->whereColumn('accounts.id', 'sales.account_id')
                ->whereColumn('accounts.currency_code', 'sales.currency_code'))
            ->update([
                'converted_currency_code' => DB::raw('currency_code'),
                'converted_amount' => DB::raw('amount'),
                'gross_amount' => DB::raw('amount'),
                'exchange_rate' => 1,
                'converted_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally empty: backfilled conversions can't be told apart from ones made after deploy.
     * Rolling back the schema migration drops the columns.
     */
    public function down(): void
    {
        //
    }
};
