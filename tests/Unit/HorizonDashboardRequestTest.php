<?php

use Deck\Deck\Support\HorizonDashboardRequest;
use Illuminate\Http\Request;

it('matches horizon dashboard html routes', function () {
    config(['horizon.path' => 'horizon']);

    expect(HorizonDashboardRequest::isDashboardRequest(Request::create('/horizon', 'GET')))->toBeTrue()
        ->and(HorizonDashboardRequest::isDashboardRequest(Request::create('/horizon/jobs/pending', 'GET')))->toBeTrue();
});

it('does not match horizon api routes', function () {
    config(['horizon.path' => 'horizon']);

    expect(HorizonDashboardRequest::isDashboardRequest(Request::create('/horizon/api/stats', 'GET')))->toBeFalse();
});
