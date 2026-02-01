<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class SubmitResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'my_score' => 'required|integer|min:0|max:20',
            'opponent_score' => 'required|integer|min:0|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'my_score.required' => 'Your score is required',
            'opponent_score.required' => 'Opponent score is required',
        ];
    }
}
