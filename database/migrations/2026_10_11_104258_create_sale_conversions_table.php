<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The exchange rate is gross / sales.amount. With gross capped at 9,999,999,999,999.99 and the
     * smallest positive amount being 0.01, a rate can reach 15 integer digits, hence decimal(25, 10).
     */
    public function up(): void
    {
        Schema::create('sale_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('converted_currency_code', 3);
            $table->decimal('converted_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('exchange_rate', 25, 10)->nullable();
            $table->text('conversion_note')->nullable();
            $table->timestamp('converted_at');
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'converted_currency_code', 'converted_amount'], 'sale_conversions_report_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_conversions');
    }
};
