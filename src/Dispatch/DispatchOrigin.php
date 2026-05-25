<?php

namespace Deck\Deck\Dispatch;

use Illuminate\Http\Request;

class DispatchOrigin
{
    /**
     * @return array<string, scalar|null>|null
     */
    public static function resolve(): ?array
    {
        if (! (bool) config('deck.lifecycle.enabled', true) || ! (bool) config('deck.lifecycle.origin_http', true)) {
            return self::resolveNonHttp();
        }

        if (app()->bound('request')) {
            $request = request();

            if ($request instanceof Request && ! app()->runningInConsole()) {
                return self::fromRequest($request);
            }
        }

        return self::resolveNonHttp();
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private static function resolveNonHttp(): ?array
    {
        if (DispatchLineage::activeJobUuid() !== null && DispatchLineage::activeJobUuid() !== '') {
            return [
                'type' => 'job',
                'parent_uuid' => DispatchLineage::activeJobUuid(),
                'parent_class' => DispatchLineage::activeJobClass(),
            ];
        }

        if ((bool) config('deck.lifecycle.origin_artisan', true) && app()->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? null;

            if (is_string($command) && $command !== '') {
                return [
                    'type' => 'artisan',
                    'command' => mb_substr($command, 0, 255),
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function fromRequest(Request $request): array
    {
        DispatchGroup::ensureRequestGroup($request);

        $header = (string) config('deck.dispatch_groups.request_id_header', 'X-Request-Id');
        $attribute = (string) config('deck.dispatch_groups.request_id_attribute', 'request_id');

        $requestId = $request->headers->get($header)
            ?? $request->attributes->get($attribute);

        $route = $request->route();

        return array_filter([
            'type' => 'http',
            'method' => mb_substr($request->method(), 0, 16),
            'route' => $route?->getName() !== null ? mb_substr((string) $route->getName(), 0, 255) : null,
            'uri' => mb_substr($request->path(), 0, 255),
            'request_id' => is_string($requestId) ? mb_substr($requestId, 0, 128) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
