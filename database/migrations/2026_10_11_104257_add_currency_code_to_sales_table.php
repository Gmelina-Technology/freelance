<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * currency_code is the currency the sale was paid in. Conversions into the account currency
     * live in sale_conversions.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->string('currency_code', 3)->nullable()->after('amount');

            $table->index(['account_id', 'transaction_date'], 'sales_account_date_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Narrowing amount back to decimal(8, 2) fails on MySQL strict mode (and truncates elsewhere)
     * when any sale is above 999,999.99.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_account_date_index');
            $table->dropColumn('currency_code');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->change();
        });
    }
};
