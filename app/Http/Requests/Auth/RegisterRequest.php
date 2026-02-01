<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Enums\GeographicLevel;
use App\Models\GeographicUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Account credentials
            'phone_number' => ['required', 'string', 'unique:users,phone_number', 'regex:/^(\+[1-9]\d{6,14}|0[1-9]\d{8,9})$/'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'country_id' => ['required', 'exists:geographic_units,id'],

            // Player profile (required for players)
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'nickname' => ['nullable', 'string', 'min:2', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1920-01-01'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'geographic_unit_id' => [
                'required',
                'exists:geographic_units,id',
                function ($attribute, $value, $fail) {
                    $unit = GeographicUnit::find($value);
                    if ($unit && $unit->level !== GeographicLevel::ATOMIC->value) {
                        $fail('You must register at a community (atomic) level location.');
                    }
                },
            ],
            'national_id_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Enter a valid phone number (e.g., 0705708643 or +254705708643)',
            'phone_number.unique' => 'This phone number is already registered',
            'email.unique' => 'This email is already registered',
            'password' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers',
            'country_id.exists' => 'Invalid country selected',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'gender.required' => 'Gender is required',
            'geographic_unit_id.required' => 'Please select your community location',
            'geographic_unit_id.exists' => 'Invalid location selected',
        ];
    }
}
