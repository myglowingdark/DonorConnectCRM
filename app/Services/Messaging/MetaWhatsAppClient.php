<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaWhatsAppClient
{
    /**
     * @param  list<array<string, mixed>>  $components
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
     * Upload a sample media file via Meta Resumable Upload API and return the asset handle.
     */
    public function uploadResumableMedia(
        MetaWhatsAppCredentials $credentials,
        string $absolutePath,
        string $mime,
        string $filename,
    ): string {
        $appId = $credentials->appId ?: config('services.meta.app_id');

        if (blank($appId)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta App ID is required to upload template document samples. Configure it in platform messaging settings.',
            ]);
        }

        if (! is_readable($absolutePath)) {
            throw ValidationException::withMessages([
                'attachment' => 'Template document file could not be read for Meta upload.',
            ]);
        }

        $fileLength = filesize($absolutePath);
        if ($fileLength === false || $fileLength < 1) {
            throw ValidationException::withMessages([
                'attachment' => 'Template document file is empty or unreadable.',
            ]);
        }

        $sessionResponse = Http::withToken($credentials->accessToken)
            ->acceptJson()
            ->post("{$credentials->graphBaseUrl()}/{$appId}/uploads", [
                'file_length' => $fileLength,
                'file_type' => $mime,
                'file_name' => $filename,
            ]);

        if (! $sessionResponse->successful()) {
            throw $this->toValidationException(
                $sessionResponse->json(),
                $sessionResponse->status(),
                'Failed to start Meta media upload for template document.',
            );
        }

        $uploadSessionId = $sessionResponse->json('id');
        if (blank($uploadSessionId)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta did not return an upload session id for the template document.',
            ]);
        }

        $binary = file_get_contents($absolutePath);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'attachment' => 'Template document file could not be read for Meta upload.',
            ]);
        }

        $uploadResponse = Http::withToken($credentials->accessToken)
            ->withHeaders([
                'file_offset' => '0',
                'Content-Type' => $mime,
            ])
            ->withBody($binary, $mime)
            ->post("{$credentials->graphBaseUrl()}/{$uploadSessionId}");

        if (! $uploadResponse->successful()) {
            throw $this->toValidationException(
                $uploadResponse->json(),
                $uploadResponse->status(),
                'Failed to upload template document sample to Meta.',
            );
        }

        $handle = $uploadResponse->json('h');
        if (blank($handle)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta did not return a media handle for the template document.',
            ]);
        }

        return (string) $handle;
    }

    /**
     * @param  array{
     *     name: string,
     *     language: string,
     *     category: string,
     *     body: string,
     *     example_body?: list<string>|null,
     *     header_format?: string|null,
     *     header_handle?: string|null
     * }  $template
     * @return array{id: string|null, status: string|null, raw: array<string, mixed>}
     */
    public function createMessageTemplate(MetaWhatsAppCredentials $credentials, array $template): array
    {
        $components = [];

        if (($template['header_format'] ?? null) === 'document' && filled($template['header_handle'] ?? null)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'example' => [
                    'header_handle' => [$template['header_handle']],
                ],
            ];
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $template['body'],
        ];

        if (! empty($template['example_body'])) {
            $bodyComponent['example'] = [
                'body_text' => [array_values($template['example_body'])],
            ];
        }

        $components[] = $bodyComponent;

        $payload = [
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => strtoupper($template['category']),
            'components' => $components,
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
        $userMessage = data_get($json, 'error.error_user_msg');
        $apiMessage = data_get($json, 'error.message');
        $title = data_get($json, 'error.error_user_title');

        $message = $userMessage ?: $apiMessage ?: $fallback;
        if (filled($title) && filled($userMessage) && ! str_contains((string) $message, (string) $title)) {
            $message = "{$title}: {$message}";
        }

        return ValidationException::withMessages([
            'whatsapp' => "[{$status}] {$message}",
        ]);
    }
}
