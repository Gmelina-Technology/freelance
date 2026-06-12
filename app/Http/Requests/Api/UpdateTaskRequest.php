<?php

namespace App\Http\Requests\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->attributes->get('account');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('account_id', $account->id)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('account_id', $account->id)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('account_user', 'user_id')->where('account_id', $account->id)],
        ];
    }
}
