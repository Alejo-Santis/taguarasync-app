<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(['owner', 'admin', 'cashier', 'warehouse', 'accountant'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo electronico', 'role' => 'rol'];
    }
}
