<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('default_bank_detail_id')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')
                ->references('code')
                ->on('currencies')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
