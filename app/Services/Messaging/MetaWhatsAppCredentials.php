<?php

namespace App\Services\Messaging;

readonly class MetaWhatsAppCredentials
{
    public function __construct(
        public string $accessToken,
        public string $phoneNumberId,
        public string $wabaId,
        public string $apiVersion = 'v21.0',
        public ?string $appId = null,
        public ?string $appSecret = null,
        public string $source = 'organization',
    ) {}

    public function isComplete(): bool
    {
        return filled($this->accessToken)
            && filled($this->phoneNumberId)
            && filled($this->wabaId);
    }

    public function graphBaseUrl(): string
    {
        $version = ltrim($this->apiVersion ?: 'v21.0', '/');

        return "https://graph.facebook.com/{$version}";
    }
}
