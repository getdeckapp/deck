<?php

namespace Deck\Deck\Cloud\Connection;

use Deck\Deck\Cloud\DeckCloud;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpClient
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        $response = $this->request('get', $path, query: $query);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload): bool
    {
        $response = $this->request('post', $path, payload: $payload);

        return $response !== null && $response->successful();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $path, array $payload = [], array $query = []): ?Response
    {
        if (! DeckCloud::isEnabled()) {
            return null;
        }

        $url = DeckCloud::baseUrl().'/'.ltrim($path, '/');
        $timeout = max(1, (int) config('deck.cloud.timeout_seconds', 5));
        $maxAttempts = max(1, (int) config('deck.cloud.retry_attempts', 3));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $pendingRequest = Http::timeout($timeout)
                    ->withToken((string) config('deck.cloud.api_key'))
                    ->acceptJson();

                $response = $method === 'get'
                    ? $pendingRequest->get($url, $query)
                    : $pendingRequest->asJson()->post($url, $payload);

                if ($response->successful()) {
                    return $response;
                }

                if (in_array($response->status(), [401, 403], true)) {
                    $this->logFailure($path, $response, 'Deck Cloud API key is invalid or expired.');

                    return null;
                }

                if ($response->status() === 422) {
                    $this->logFailure($path, $response, 'Deck Cloud rejected the payload (validation failed).');

                    return null;
                }

                if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                    usleep($this->backoffMicroseconds($attempt));

                    continue;
                }

                $this->logFailure($path, $response);

                return null;
            } catch (Throwable $exception) {
                if ($attempt < $maxAttempts) {
                    usleep($this->backoffMicroseconds($attempt));

                    continue;
                }

                if (config('deck.cloud.log_failures', true)) {
                    Log::warning('Deck Cloud request failed.', [
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ]);
                }

                return null;
            }
        }

        return null;
    }

    private function logFailure(string $path, Response $response, ?string $message = null): void
    {
        if (! config('deck.cloud.log_failures', true)) {
            return;
        }

        Log::warning($message ?? 'Deck Cloud request was not successful.', [
            'path' => $path,
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);
    }

    private function backoffMicroseconds(int $attempt): int
    {
        return (int) (250_000 * (2 ** max(0, $attempt - 1)));
    }
}
