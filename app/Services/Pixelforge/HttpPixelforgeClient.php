<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\Pixelforge\GenerationRequest;
use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Data\Pixelforge\PixelforgeUsage;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class HttpPixelforgeClient implements PixelforgeClient
{
    private const PRICING_CACHE_KEY = 'pixelforge.pricing';

    public function __construct(
        #[Config('services.pixelforge.base_url')] private string $baseUrl,
        #[Config('services.pixelforge.api_key')] private string $apiKey,
    ) {}

    public function createGeneration(GenerationRequest $request): PixelforgeGeneration
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeader('Idempotency-Key', $request->idempotencyKey)
            ->post('generations', $request->body()));

        if ($response->header('Idempotent-Replayed') === 'true') {
            Log::info('pixelforge.create.replayed', ['idempotency_key' => $request->idempotencyKey]);
        }

        return PixelforgeGeneration::fromPayload(Payload::of($response->json()));
    }

    public function generation(string $id): PixelforgeGeneration
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($id)));

        return PixelforgeGeneration::fromPayload(Payload::of($response->json()));
    }

    public function usage(CarbonImmutable $from, CarbonImmutable $to): PixelforgeUsage
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $from->utc()->toDateString(),
            'end_date' => $to->utc()->toDateString(),
        ]));

        return PixelforgeUsage::fromPayload(Payload::of($response->json()));
    }

    public function pricing(): PixelforgePricing
    {
        $json = Cache::remember(
            self::PRICING_CACHE_KEY,
            now()->addMinutes(10),
            fn (): string => $this->send(fn (PendingRequest $http) => $http->get('pricing'))->body(),
        );

        return PixelforgePricing::fromPayload(Payload::fromJson($json));
    }

    /** @param Closure(PendingRequest): Response $call */
    private function send(Closure $call): Response
    {
        try {
            $response = $call(Http::baseUrl($this->baseUrl)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(15));
        } catch (ConnectionException $exception) {
            throw new PixelforgeUnreachable($exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw PixelforgeRequestFailed::fromResponse($response);
        }

        return $response;
    }
}
