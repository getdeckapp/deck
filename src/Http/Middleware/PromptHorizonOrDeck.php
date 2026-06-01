<?php

namespace Deck\Deck\Http\Middleware;

use Closure;
use Deck\Deck\Horizon\HorizonDashboardRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PromptHorizonOrDeck
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('deck.horizon.prompt_on_visit', true)) {
            return $next($request);
        }

        if (! $this->isHorizonDashboardRequest($request)) {
            return $next($request);
        }

        $preference = $request->session()->get('deck_horizon_preference');

        if ($preference === 'deck') {
            return redirect()->route('deck.index');
        }

        if ($preference === 'horizon') {
            return $next($request);
        }

        return response()->view('deck::horizon-prompt', [
            'rememberChoice' => config('deck.horizon.remember_choice', true),
            'horizonUrl' => url(config('horizon.path', 'horizon')),
            'deckUrl' => route('deck.index'),
        ]);
    }

    private function isHorizonDashboardRequest(Request $request): bool
    {
        return HorizonDashboardRequest::isDashboardRequest($request);
    }
}
