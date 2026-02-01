<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class ReportNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|min:10|max:500',
        ];
    }
}
