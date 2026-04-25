<?php

namespace App\Models;

use App\Traits\HasAccount;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasAccount;

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
    ];
}
