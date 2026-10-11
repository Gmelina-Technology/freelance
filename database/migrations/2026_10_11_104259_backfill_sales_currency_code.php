<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records the client's currency on sales made from an invoice. No sale is converted: every
     * existing sale starts unconverted. Only rows that are still null are touched, so running
     * this again changes nothing, and updated_at is left alone.
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
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally empty: rolling back the schema migration drops the column.
     */
    public function down(): void
    {
        //
    }
};
