<?php

namespace Deck\Deck\Http\Middleware;

use Closure;
use Deck\Deck\Horizon\HorizonDashboardRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectHorizonDeckBanner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return $response;
        }

        $banner = view('deck::horizon-banner', [
            'deckUrl' => route('deck.index'),
        ])->render();

        $injected = str_replace('<body>', '<body>'.$banner, $content);

        if ($injected === $content) {
            return $response;
        }

        return $response->setContent($injected);
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (! config('deck.horizon.banner', true)) {
            return false;
        }

        if (! HorizonDashboardRequest::isDashboardRequest($request)) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        $content = $response->getContent();

        return is_string($content)
            && str_contains($content, 'id="horizon"')
            && ! str_contains($content, 'deck-horizon-banner');
    }
}
