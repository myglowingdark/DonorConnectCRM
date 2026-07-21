<?php

namespace App\Enums;

enum ApiAuthType: string
{
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKey = 'api_key';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Bearer => 'Bearer Token',
            self::Basic => 'Basic Auth',
            self::ApiKey => 'API Key',
            self::None => 'None',
        };
    }
}
