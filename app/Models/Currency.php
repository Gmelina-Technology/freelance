<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /** @use HasFactory<\Database\Factories\CurrencyFactory> */
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
