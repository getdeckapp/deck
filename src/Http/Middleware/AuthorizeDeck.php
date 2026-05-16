<?php

namespace TorMorten\Deck\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        if (class_exists(Horizon::class) && method_exists(Horizon::class, 'check')) {
            return Horizon::check($request);
        }

        if (Gate::has('viewHorizon')) {
            return Gate::check('viewHorizon', [$request->user()]);
        }

        return $request->user() !== null || app()->environment('local');
    }
}
