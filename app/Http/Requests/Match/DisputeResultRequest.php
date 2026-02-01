<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class DisputeResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500',
            'my_score' => 'nullable|integer|min:0|max:20',
            'opponent_score' => 'nullable|integer|min:0|max:20',
        ];
    }
}
