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
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('title')->default('')->after('quote_id');
            $table->text('description')->nullable()->after('title');
            $table->foreignId('category_id')->nullable()->after('description')->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1)->change();
        });

        // Historical quote lines must survive deleting a unit.
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['title', 'description', 'category_id', 'sort_order']);
        });
    }
};
