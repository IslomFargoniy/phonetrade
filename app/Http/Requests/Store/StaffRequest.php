<?php

namespace App\Http\Requests\Store;

use App\Concerns\ProfileValidationRules;
use App\Models\Branch;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff instanceof User ? $staff->id : null;

        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($staffId),
            // Yangi xodimda parol majburiy; tahrirda bo'sh qoldirilsa o'zgarmaydi.
            'password' => [$staffId === null ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'can_refund' => ['boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', Rule::exists(Branch::class, 'id')->where('tenant_id', $this->user()->tenant_id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['branch_ids' => 'Filiallar'];
    }
}
