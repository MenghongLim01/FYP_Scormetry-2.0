<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin()
            || $this->user()->id === $this->route('rubric')->subject->teacher_id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'structure_json' => ['required', 'array'],
            'structure_json.*.criteria' => ['required', 'string'],
            'structure_json.*.max_score' => ['required', 'integer', 'min:1'],
            'structure_json.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'confirm_scoring_started_change' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $structure = $this->input('structure_json', []);

                if (! is_array($structure) || $structure === []) {
                    return;
                }

                $total = collect($structure)
                    ->sum(fn ($item) => (float) ($item['weight'] ?? 0));

                if (abs($total - 100) > 0.01) {
                    $validator->errors()->add('structure_json', 'Weights must sum to 100%.');
                }
            },
        ];
    }
}
