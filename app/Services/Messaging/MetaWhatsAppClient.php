<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaWhatsAppClient
{
    /**
     * @param  list<array{type: string, parameters: list<array{type: string, text: string}>}>  $components
     * @return array{message_id: string|null, raw: array<string, mixed>}
     */
    public function sendTemplateMessage(
        MetaWhatsAppCredentials $credentials,
        string $toE164,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): array {
        $to = preg_replace('/\D+/', '', $toE164) ?: '';

        if ($to === '') {
            throw ValidationException::withMessages([
                'recipient' => 'Recipient phone number is invalid.',
            ]);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($credentials->accessToken)
            ->acceptJson()
            ->post("{$credentials->graphBaseUrl()}/{$credentials->phoneNumberId}/messages", $payload);

        if (! $response->successful()) {
            throw $this->toValidationException($response->json(), $response->status(), 'Failed to send WhatsApp template message.');
        }

        $json = $response->json() ?? [];

        return [
            'message_id' => data_get($json, 'messages.0.id'),
            'raw' => $json,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     language: string,
     *     category: string,
     *     body: string,
     *     example_body?: list<string>|null
     * }  $template
     * @return array{id: string|null, status: string|null, raw: array<string, mixed>}
     */
    public function createMessageTemplate(MetaWhatsAppCredentials $credentials, array $template): array
    {
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $template['body'],
        ];

        if (! empty($template['example_body'])) {
            $bodyComponent['example'] = [
                'body_text' => [array_values($template['example_body'])],
            ];
        }

        $payload = [
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => strtoupper($template['category']),
            'components' => [$bodyComponent],
        ];

        $response = Http::withToken($credentials->accessToken)
            ->acceptJson()
            ->post("{$credentials->graphBaseUrl()}/{$credentials->wabaId}/message_templates", $payload);

        if (! $response->successful()) {
            throw $this->toValidationException($response->json(), $response->status(), 'Failed to submit WhatsApp template to Meta.');
        }

        $json = $response->json() ?? [];

        return [
            'id' => isset($json['id']) ? (string) $json['id'] : null,
            'status' => isset($json['status']) ? (string) $json['status'] : null,
            'raw' => $json,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMessageTemplates(MetaWhatsAppCredentials $credentials, ?string $name = null): array
    {
        $query = ['limit' => 100];
        if (filled($name)) {
            $query['name'] = $name;
        }

        $response = Http::withToken($credentials->accessToken)
            ->acceptJson()
            ->get("{$credentials->graphBaseUrl()}/{$credentials->wabaId}/message_templates", $query);

        if (! $response->successful()) {
            throw $this->toValidationException($response->json(), $response->status(), 'Failed to fetch WhatsApp templates from Meta.');
        }

        return $response->json('data') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPhoneNumber(MetaWhatsAppCredentials $credentials): array
    {
        $response = Http::withToken($credentials->accessToken)
            ->acceptJson()
            ->get("{$credentials->graphBaseUrl()}/{$credentials->phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);

        if (! $response->successful()) {
            throw $this->toValidationException($response->json(), $response->status(), 'Meta WhatsApp connection test failed.');
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function toValidationException(?array $json, int $status, string $fallback): ValidationException
    {
        $message = data_get($json, 'error.message')
            ?? data_get($json, 'error.error_user_msg')
            ?? $fallback;

        return ValidationException::withMessages([
            'whatsapp' => "[{$status}] {$message}",
        ]);
    }
}
