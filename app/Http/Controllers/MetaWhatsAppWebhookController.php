<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\OutboundMessage;
use App\Models\PlatformMessagingSetting;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MetaWhatsAppCredentialResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $expected = config('services.meta.webhook_verify_token')
            ?: PlatformMessagingSetting::current()->meta_app_id;

        if ($mode === 'subscribe' && filled($expected) && hash_equals((string) $expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(
        Request $request,
        MetaWhatsAppCredentialResolver $resolver,
        MessageService $messages,
    ): Response {
        $platformCreds = $resolver->platformWebhookCredentials();
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($platformCreds?->appSecret && filled($signature)) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $platformCreds->appSecret);
            if (! hash_equals($expected, $signature)) {
                Log::warning('Meta WhatsApp webhook signature mismatch');

                return response('Invalid signature', 403);
            }
        }

        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                if ($field === 'message_template_status_update' && is_array($value)) {
                    $messages->applyMetaTemplateStatusWebhook($value);

                    continue;
                }

                $statuses = data_get($value, 'statuses', []);
                foreach ($statuses as $statusRow) {
                    $this->applyStatusUpdate($statusRow);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * @param  array<string, mixed>  $statusRow
     */
    protected function applyStatusUpdate(array $statusRow): void
    {
        $providerId = $statusRow['id'] ?? null;
        if (! filled($providerId)) {
            return;
        }

        $message = OutboundMessage::query()
            ->where('provider_message_id', $providerId)
            ->first();

        if (! $message) {
            return;
        }

        $status = strtolower((string) ($statusRow['status'] ?? ''));
        $mapped = match ($status) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'read' => MessageStatus::Read,
            'failed' => MessageStatus::Failed,
            default => null,
        };

        if (! $mapped) {
            return;
        }

        $error = data_get($statusRow, 'errors.0.title')
            ?? data_get($statusRow, 'errors.0.message');

        $message->update([
            'status' => $mapped,
            'error_message' => $mapped === MessageStatus::Failed ? ($error ?: $message->error_message) : $message->error_message,
            'provider_payload' => array_merge($message->provider_payload ?? [], [
                'webhook_status' => $statusRow,
            ]),
        ]);
    }
}
