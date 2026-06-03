<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isTeacher()) {
            return false;
        }

        // Teachers may only upload a rubric to a subject they own
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return $subject instanceof Subject
            && $subject->teacher_id === $user->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'defense_period_id' => ['nullable', 'exists:defense_periods,id'],
            'use_custom_period' => ['nullable', 'boolean'],
            'custom_period_name' => ['nullable', 'required_if:use_custom_period,1', 'string', 'max:100'],
            'replace_locked' => ['nullable', 'boolean'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
