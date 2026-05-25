<?php

namespace Deck\Deck\Http\Middleware;

use Closure;
use Deck\Deck\Dispatch\DispatchGroup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignDispatchGroup
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        DispatchGroup::ensureRequestGroup($request);

        return $next($request);
    }
}
