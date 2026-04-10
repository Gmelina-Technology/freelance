<?php

namespace App\Models;

use App\Traits\HasAccount;
use Database\Factories\BankDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDetail extends Model
{
    /** @use HasFactory<BankDetailFactory> */
    use HasFactory, HasAccount;

    protected $fillable = [
        'account_id',
        'bank_name',
        'bank_address',
        'bsb',
        'swift_bic_code',
        'account_name',
        'account_number',
    ];
}
