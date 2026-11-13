<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('owner_phone'))) {
            $this->merge(['owner_phone' => Phone::normalize($this->input('owner_phone')) ?? $this->input('owner_phone')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_name' => ['required', 'string', 'max:255'],
            'owner_name' => $this->nameRules(),
            'owner_phone' => $this->phoneRules(),
            'owner_password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => "Do'kon nomi",
            'branch_name' => 'Filial nomi',
            'owner_name' => 'Egasining ismi',
            'owner_phone' => 'Egasining telefoni',
            'owner_password' => 'Parol',
        ];
    }
}
