<?php

namespace App\Http\Requests\Match;

use App\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UploadEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,mov|max:51200', // 50MB max
            'type' => ['required', 'string', new Enum(EvidenceType::class)],
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a file',
            'file.mimes' => 'File must be an image (jpg, png, gif) or video (mp4, mov)',
            'file.max' => 'File size must be less than 50MB',
        ];
    }
}
