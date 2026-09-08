<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

trait ValidatesReviewNotes
{
    protected function optionalReviewNotes(Request $request): ?string
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $notes = trim($validated['notes'] ?? '');

        return $notes !== '' ? $notes : null;
    }
}
