<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class DisputeMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for the dispute is required.',
            'reason.min' => 'Please provide a more detailed reason (at least 10 characters).',
            'reason.max' => 'Dispute reason is too long (maximum 1000 characters).',
        ];
    }
}
