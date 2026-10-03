<?php

namespace App\Http\Requests\Api;

use App\Models\Account;
use App\Services\TaskService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->attributes->get('account');

        return app(TaskService::class)->rules($account, updating: false);
    }
}
