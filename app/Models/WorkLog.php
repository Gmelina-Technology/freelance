<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    use HasAccount;

    protected $fillable = [
        'account_id',
        'user_id',
        'task_id',
        'worked_date',
        'hours',
        'description',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
