<?php

namespace App\Models;

use App\Traits\HasAccount;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'color',
    ];
}
