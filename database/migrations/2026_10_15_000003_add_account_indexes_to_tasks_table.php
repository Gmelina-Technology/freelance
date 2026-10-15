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
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'due_date']);
            $table->index(['account_id', 'assigned_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * MySQL drops the implicit index behind the account_id foreign key once a composite index
     * starting with account_id exists. Dropping the last such index would then fail (errno 1553),
     * so the foreign key's own index is restored before the last composite index goes.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'due_date']);
            $table->dropIndex(['account_id', 'assigned_user_id']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! Schema::hasIndex('tasks', ['account_id'])) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index('account_id', 'tasks_account_id_foreign');
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'status']);
        });
    }
};
