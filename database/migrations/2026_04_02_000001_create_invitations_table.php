<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token')->unique();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
