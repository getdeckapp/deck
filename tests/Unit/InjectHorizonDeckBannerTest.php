<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TorMorten\Deck\Http\Middleware\InjectHorizonDeckBanner;

it('injects the deck banner into horizon dashboard html', function () {
    config([
        'horizon.path' => 'horizon',
        'deck.horizon.banner' => true,
    ]);

    $request = Request::create('/horizon', 'GET');
    $middleware = new InjectHorizonDeckBanner;

    $response = $middleware->handle($request, fn (): Response => new Response(
        '<html><body><div id="horizon"></div></body></html>',
        200,
        ['Content-Type' => 'text/html'],
    ));

    expect($response->getContent())
        ->toContain('deck-horizon-banner')
        ->toContain('Open Deck');
});

it('does not inject the banner into horizon api responses', function () {
    config([
        'horizon.path' => 'horizon',
        'deck.horizon.banner' => true,
    ]);

    $request = Request::create('/horizon/api/stats', 'GET');
    $middleware = new InjectHorizonDeckBanner;

    $response = $middleware->handle($request, fn (): Response => new Response('{"jobs":0}', 200));

    expect($response->getContent())->toBe('{"jobs":0}');
});

it('skips injection when the banner is disabled', function () {
    config([
        'horizon.path' => 'horizon',
        'deck.horizon.banner' => false,
    ]);

    $request = Request::create('/horizon', 'GET');
    $middleware = new InjectHorizonDeckBanner;

    $response = $middleware->handle($request, fn (): Response => new Response(
        '<html><body><div id="horizon"></div></body></html>',
    ));

    expect($response->getContent())->not->toContain('deck-horizon-banner');
});
