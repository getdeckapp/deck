<?php

namespace Deck\Deck\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorizonPreferenceController
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'in:deck,horizon'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if ($validated['remember'] ?? config('deck.horizon.remember_choice', true)) {
            $request->session()->put('deck_horizon_preference', $validated['choice']);
        }

        return redirect()->to(
            $validated['choice'] === 'deck'
                ? route('deck.index')
                : url(config('horizon.path', 'horizon'))
        );
    }
}
