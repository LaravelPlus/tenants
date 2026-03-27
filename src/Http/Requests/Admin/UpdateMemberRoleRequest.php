<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMemberRoleRequest extends FormRequest
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
            'role' => ['required', 'string', 'in:'.implode(',', config('tenants.member_roles', ['owner', 'admin', 'member']))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'A role is required.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
