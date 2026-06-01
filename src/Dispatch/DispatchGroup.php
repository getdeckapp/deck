<?php

namespace Deck\Deck\Dispatch;

use Deck\Deck\Enums\DispatchGroupSource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DispatchGroup
{
    private const string REQUEST_ATTRIBUTE = 'deck.dispatch_group_id';

    private const string REQUEST_SOURCE_ATTRIBUTE = 'deck.dispatch_group_source';

    /** @var list<array{id: string, source: DispatchGroupSource}> */
    private static array $manualStack = [];

    private static bool $withoutGroup = false;

    public static function enabled(): bool
    {
        return (bool) config('deck.dispatch_groups.enabled', true);
    }

    public static function currentId(): ?string
    {
        $resolved = self::resolve();

        return $resolved['id'] ?? null;
    }

    public static function currentSource(): ?DispatchGroupSource
    {
        $resolved = self::resolve();

        return $resolved['source'] ?? null;
    }

    /**
     * @return array{id: string, source: DispatchGroupSource}|null
     */
    public static function resolve(): ?array
    {
        if (! self::enabled() || self::$withoutGroup) {
            return null;
        }

        if (self::$manualStack !== []) {
            return self::$manualStack[array_key_last(self::$manualStack)];
        }

        $lineageGroup = DispatchLineage::inheritedDispatchGroup();

        if ($lineageGroup !== null) {
            return $lineageGroup;
        }

        $requestGroup = self::requestGroup();

        if ($requestGroup !== null) {
            return $requestGroup;
        }

        $artisanGroup = self::artisanGroup();

        if ($artisanGroup !== null) {
            return $artisanGroup;
        }

        return null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function using(string $id, callable $callback, DispatchGroupSource $source = DispatchGroupSource::Manual): mixed
    {
        self::$manualStack[] = [
            'id' => self::normalizeId($id),
            'source' => $source,
        ];

        try {
            return $callback();
        } finally {
            array_pop(self::$manualStack);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutGroup(callable $callback): mixed
    {
        $previous = self::$withoutGroup;
        self::$withoutGroup = true;

        try {
            return $callback();
        } finally {
            self::$withoutGroup = $previous;
        }
    }

    public static function ensureRequestGroup(Request $request): void
    {
        if (! self::enabled() || ! (bool) config('deck.dispatch_groups.request_middleware', true)) {
            return;
        }

        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return;
        }

        $header = (string) config('deck.dispatch_groups.request_id_header', 'X-Request-Id');
        $attribute = (string) config('deck.dispatch_groups.request_id_attribute', 'request_id');

        $id = $request->headers->get($header)
            ?? $request->attributes->get($attribute)
            ?? (string) Str::uuid();

        $request->attributes->set(self::REQUEST_ATTRIBUTE, self::normalizeId((string) $id));
        $request->attributes->set(self::REQUEST_SOURCE_ATTRIBUTE, DispatchGroupSource::Request->value);
    }

    /**
     * @return array{id: string, source: DispatchGroupSource}|null
     */
    private static function requestGroup(): ?array
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request instanceof Request) {
            return null;
        }

        self::ensureRequestGroup($request);

        $id = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (! is_string($id) || $id === '') {
            return null;
        }

        $sourceValue = $request->attributes->get(self::REQUEST_SOURCE_ATTRIBUTE);
        $source = is_string($sourceValue)
            ? DispatchGroupSource::tryFrom($sourceValue) ?? DispatchGroupSource::Request
            : DispatchGroupSource::Request;

        return [
            'id' => $id,
            'source' => $source,
        ];
    }

    /**
     * @return array{id: string, source: DispatchGroupSource}|null
     */
    private static function artisanGroup(): ?array
    {
        if (! (bool) config('deck.dispatch_groups.artisan', false) || ! app()->runningInConsole()) {
            return null;
        }

        $command = $_SERVER['argv'][1] ?? null;

        if (! is_string($command) || $command === '') {
            return null;
        }

        return [
            'id' => self::normalizeId('artisan:'.sha1(implode(' ', $_SERVER['argv'] ?? []))),
            'source' => DispatchGroupSource::Manual,
        ];
    }

    private static function normalizeId(string $id): string
    {
        return mb_substr(trim($id), 0, 128);
    }
}
