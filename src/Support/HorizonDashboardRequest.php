<?php

namespace TorMorten\Deck\Support;

use Illuminate\Http\Request;

class HorizonDashboardRequest
{
    public static function isDashboardRequest(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $path = trim((string) config('horizon.path', 'horizon'), '/');

        if ($path === '') {
            return false;
        }

        if (! $request->is($path) && ! $request->is($path.'/*')) {
            return false;
        }

        return ! $request->is($path.'/api/*');
    }
}
