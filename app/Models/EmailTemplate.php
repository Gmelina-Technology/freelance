<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;
    use HasAccount;

    protected $fillable = [
        'account_id',
        'type',
        'subject',
        'body',
        'metadata'
    ];
}
