<?php

use App\Enums\EmailTemplateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existingQuoteTemplateAccountIds = DB::table('email_templates')
            ->where('type', EmailTemplateType::QUOTE_REQUEST->name)
            ->pluck('account_id')
            ->all();

        $accounts = DB::table('accounts')
            ->whereNotIn('id', $existingQuoteTemplateAccountIds)
            ->pluck('id');

        $now = now();

        foreach ($accounts as $accountId) {
            DB::table('email_templates')->insert([
                'account_id' => $accountId,
                'type' => EmailTemplateType::QUOTE_REQUEST->name,
                'subject' => 'Task Service Quote',
                'body' => config('email-templates.defaults.quote.body'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_templates')
            ->where('type', EmailTemplateType::QUOTE_REQUEST->name)
            ->delete();
    }
};
