<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOrganizationAdmin($this->route('organization')) ?? false;
    }

    protected function failedAuthorization(): never
    {
        abort(404);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'in:'.implode(',', config('tenants.member_roles', ['owner', 'admin', 'member']))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
