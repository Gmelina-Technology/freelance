<?php

namespace App\Models;

use App\Enums\EmailTemplateType;
use App\Traits\HasAccount;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasAccount;

    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'type',
        'subject',
        'body',
        'metadata',
    ];

    protected $casts = [
        'type' => EmailTemplateType::class
    ];
}
