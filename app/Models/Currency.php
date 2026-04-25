<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    public $incrementing = false;   // not auto-increment

    protected $keyType = 'string';  // PK is string, not int

    protected $fillable = [
        'code',
        'name',
        'symbol',
    ];
}
