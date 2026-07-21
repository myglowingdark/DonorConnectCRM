<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBillingSetting extends Model
{
    protected $fillable = [
        'razorpay_key_id',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'razorpay_key_secret' => 'encrypted',
            'razorpay_webhook_secret' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => false,
        ]);
    }
}
