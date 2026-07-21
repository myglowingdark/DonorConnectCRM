<?php

namespace App\Support;

class Languages
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'en' => 'English',
            'hi' => 'Hindi',
            'bn' => 'Bengali',
            'te' => 'Telugu',
            'mr' => 'Marathi',
            'ta' => 'Tamil',
            'gu' => 'Gujarati',
            'kn' => 'Kannada',
            'ml' => 'Malayalam',
            'pa' => 'Punjabi',
            'or' => 'Odia',
            'ur' => 'Urdu',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return self::options()[$code] ?? $code;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function forSelect(): array
    {
        return collect(self::options())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
