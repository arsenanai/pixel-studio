<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Generations\HandlePixelforgeEvent;
use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeEvent;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\InvalidWebhookSignature;
use App\Services\Pixelforge\VerifyPixelforgeSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class PixelforgeWebhookController
{
    public function __invoke(Request $request, VerifyPixelforgeSignature $verify, HandlePixelforgeEvent $handle): Response
    {
        $body = $request->getContent();
        $headerEventId = $request->header('Pixelforge-Event-Id');
        $attempt = $request->header('Pixelforge-Delivery-Attempt');
        $context = ['header_event_id' => $headerEventId, 'delivery_attempt' => $attempt];

        try {
            $verify((string) $request->header('Pixelforge-Signature'), $body);
            $event = PixelforgeEvent::fromPayload(Payload::fromJson($body));
        } catch (InvalidWebhookSignature|InvalidPixelforgePayload $rejected) {
            Log::warning('pixelforge.webhook.rejected', [...$context, 'reason' => $rejected->getMessage()]);

            return response($rejected->getMessage(), 400);
        }

        if ($headerEventId !== null && $headerEventId !== $event->id) {
            Log::warning('pixelforge.webhook.event_id_mismatch', [...$context, 'body_event_id' => $event->id]);
        }

        $handle($event, $body, is_string($attempt) && ctype_digit($attempt) ? (int) $attempt : null);

        return response()->noContent();
    }
}
