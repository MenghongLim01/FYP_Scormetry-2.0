<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStudent();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'exists:subjects,id'],
            'defense_attempt_id' => ['nullable', 'exists:defense_attempts,id'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'slides' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
        ];
    }
}
