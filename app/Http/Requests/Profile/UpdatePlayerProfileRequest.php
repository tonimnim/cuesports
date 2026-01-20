<?php

namespace App\Http\Requests\Profile;

use App\Enums\Gender;
use App\Enums\GeographicLevel;
use App\Models\GeographicUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'min:2', 'max:50'],
            'last_name' => ['sometimes', 'string', 'min:2', 'max:50'],
            'nickname' => ['nullable', 'string', 'min:2', 'max:30'],
            'date_of_birth' => ['sometimes', 'date', 'before:today', 'after:1920-01-01'],
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            'geographic_unit_id' => [
                'sometimes',
                'exists:geographic_units,id',
                function ($attribute, $value, $fail) {
                    $unit = GeographicUnit::find($value);
                    if ($unit && $unit->level !== GeographicLevel::ATOMIC->value) {
                        $fail('You must select a community (atomic) level location.');
                    }
                },
            ],
            'national_id_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Date of birth must be in the past',
            'geographic_unit_id.exists' => 'Invalid location selected',
        ];
    }
}
