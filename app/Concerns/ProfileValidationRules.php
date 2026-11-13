<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Telefon raqami qoidalari. Qiymat tekshiruvdan oldin Phone::normalize() dan o'tgan bo'lishi kerak.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'regex:'.Phone::PATTERN,
            $userId === null
                ? Rule::unique(User::class, 'phone')
                : Rule::unique(User::class, 'phone')->ignore($userId),
        ];
    }
}
