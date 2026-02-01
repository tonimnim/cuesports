<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'my_score' => ['required', 'integer', 'min:0', 'max:3'],
            'opponent_score' => ['required', 'integer', 'min:0', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'my_score.required' => 'Your score is required.',
            'my_score.integer' => 'Score must be a whole number.',
            'my_score.min' => 'Score cannot be negative.',
            'my_score.max' => 'Score cannot exceed 3 (best of 3).',
            'opponent_score.required' => 'Opponent score is required.',
            'opponent_score.integer' => 'Score must be a whole number.',
            'opponent_score.min' => 'Score cannot be negative.',
            'opponent_score.max' => 'Score cannot exceed 3 (best of 3).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $myScore = $this->input('my_score');
            $opponentScore = $this->input('opponent_score');

            // Validate that one player won (reached 2 in best of 3)
            if ($myScore !== 2 && $opponentScore !== 2) {
                $validator->errors()->add('my_score', 'One player must have a score of 2 to win (best of 3).');
            }

            // Validate that both can't have winning score
            if ($myScore === 2 && $opponentScore === 2) {
                $validator->errors()->add('my_score', 'Both players cannot have the winning score.');
            }
        });
    }
}
