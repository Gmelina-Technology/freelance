<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The exchange rate is gross / amount. With gross capped at 9,999,999,999,999.99 and the smallest
     * positive amount being 0.01, a rate can reach 15 integer digits, hence decimal(25, 10).
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->string('currency_code', 3)->nullable()->after('amount')->index();
            $table->string('converted_currency_code', 3)->nullable()->after('currency_code');
            $table->decimal('converted_amount', 15, 2)->nullable()->after('converted_currency_code');
            $table->decimal('fee_amount', 15, 2)->nullable()->after('converted_amount');
            $table->decimal('tax_amount', 15, 2)->nullable()->after('fee_amount');
            $table->decimal('gross_amount', 15, 2)->nullable()->after('tax_amount');
            $table->decimal('exchange_rate', 25, 10)->nullable()->after('gross_amount');
            $table->text('conversion_note')->nullable()->after('exchange_rate');
            $table->timestamp('converted_at')->nullable()->after('conversion_note');
            $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();

            $table->index(['account_id', 'converted_currency_code', 'transaction_date'], 'sales_report_index');
            $table->index(['account_id', 'converted_at']);
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
            $table->dropIndex('sales_report_index');
            $table->dropIndex(['account_id', 'converted_at']);
            $table->dropIndex(['currency_code']);
            $table->dropConstrainedForeignId('converted_by');
            $table->dropColumn([
                'currency_code',
                'converted_currency_code',
                'converted_amount',
                'fee_amount',
                'tax_amount',
                'gross_amount',
                'exchange_rate',
                'conversion_note',
                'converted_at',
            ]);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->change();
        });
    }
};
