<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaperScoreController extends Controller
{
    public function update(Request $request, Paper $paper): RedirectResponse
    {
        $validated = $request->validate([
            'override_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'override_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $paper->update([
            'final_score_override' => $validated['override_score'],
            'final_score_override_reason' => $validated['override_note'] ?? null,
            'final_score_override_by' => $request->user()->id,
        ]);

        $paper->defenseAttempt?->update([
            'final_score_override' => $validated['override_score'],
            'final_score_override_reason' => $validated['override_note'] ?? null,
            'final_score_override_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Score override saved.');
    }
}
