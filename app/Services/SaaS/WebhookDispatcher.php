<?php

namespace App\Services\SaaS;

use App\Models\Organization;
use App\Models\OrganizationWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    /** @param  array<string, mixed>  $payload */
    public function dispatch(Organization $organization, string $event, array $payload): void
    {
        $webhooks = $organization->webhooks()
            ->where('is_active', true)
            ->get()
            ->filter(fn (OrganizationWebhook $webhook) => $webhook->listensTo($event));

        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $event, $payload);
        }
    }

    /** @param  array<string, mixed>  $payload */
    protected function deliver(OrganizationWebhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $body = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $request = Http::timeout(15)->acceptJson();

        if (filled($webhook->secret)) {
            $request = $request->withHeaders([
                'X-DonorConnect-Signature' => hash_hmac('sha256', json_encode($body), $webhook->secret),
            ]);
        }

        try {
            $response = $request->post($webhook->url, $body);
            $success = $response->successful();
            $statusCode = $response->status();
            $responseBody = $response->body();
        } catch (\Throwable $e) {
            $success = false;
            $statusCode = null;
            $responseBody = $e->getMessage();
        }

        return WebhookDelivery::create([
            'organization_webhook_id' => $webhook->id,
            'event' => $event,
            'status_code' => $statusCode,
            'success' => $success,
            'payload' => $body,
            'response_body' => $responseBody,
            'delivered_at' => now(),
        ]);
    }
}
