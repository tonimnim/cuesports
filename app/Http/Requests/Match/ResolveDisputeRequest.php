<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->is_support || $user->is_super_admin);
    }

    public function rules(): array
    {
        return [
            'player1_score' => ['required', 'integer', 'min:0', 'max:3'],
            'player2_score' => ['required', 'integer', 'min:0', 'max:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'player1_score.required' => 'Player 1 score is required.',
            'player2_score.required' => 'Player 2 score is required.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $score1 = $this->input('player1_score');
            $score2 = $this->input('player2_score');

            if ($score1 !== 2 && $score2 !== 2) {
                $validator->errors()->add('player1_score', 'One player must have a score of 2 to win (best of 3).');
            }

            if ($score1 === 2 && $score2 === 2) {
                $validator->errors()->add('player1_score', 'Both players cannot have the winning score.');
            }
        });
    }
}
