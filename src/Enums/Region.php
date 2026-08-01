<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Enums;

enum Region: string
{
    case AU = 'au';
    case EU = 'eu';

    /**
     * Get the base URL for this region.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::AU => 'https://au-api.halaxy.com/main/',
            self::EU => 'https://eu-api.halaxy.com/main/',
        };
    }

    /**
     * Create from a string value, defaulting to AU if invalid.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::AU;
    }
}
