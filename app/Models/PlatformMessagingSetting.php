<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMessagingSetting extends Model
{
    protected $fillable = [
        'email_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'from_email',
        'from_name',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'email_enabled' => true,
        ]);
    }

    public function usesCustomSmtp(): bool
    {
        return filled($this->smtp_host) && filled($this->from_email);
    }
}
