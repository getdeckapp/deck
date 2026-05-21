<?php

namespace Deck\Deck\Http\Middleware;

use Closure;
use Deck\Deck\Support\DeckHorizon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Horizon;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDeck
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authorized($request)) {
            return $next($request);
        }

        abort(403);
    }

    private function authorized(Request $request): bool
    {
        $callback = config('deck.auth');

        if ($callback !== null) {
            return (bool) $callback($request);
        }

        if (DeckHorizon::isInstalled()) {
            return Horizon::check($request);
        }

        if (Gate::has('viewHorizon')) {
            return Gate::check('viewHorizon', [$request->user()]);
        }

        if (app()->environment('local')) {
            return true;
        }

        // No explicit auth is configured and the app is not running locally.
        // Deny access and warn so the misconfiguration surfaces in logs rather
        // than silently granting every authenticated user operational control
        // over the job queue (block, cancel, retry).
        Log::warning('Deck: dashboard access denied — no authorization configured. Set `deck.auth` in config/deck.php or register a `viewHorizon` gate.');

        return false;
    }
}
