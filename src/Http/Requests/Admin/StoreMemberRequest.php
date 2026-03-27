<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:'.implode(',', config('tenants.member_roles', ['owner', 'admin', 'member']))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'A user is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'role.required' => 'A role is required.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
