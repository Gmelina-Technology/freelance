<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    /** @use HasFactory<\Database\Factories\UnitFactory> */
    use HasFactory;
    use HasAccount;

    protected $fillable = [
        'account_id',
        'name',
    ];
}
