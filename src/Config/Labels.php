<?php

declare(strict_types=1);

namespace App\Config;

class Labels
{
    public const MAP = [
        'manual_vendor' => 'Vendor',
        'manual_package' => 'Package',
        'manual_version' => 'Version',
        'is_core' => 'Core?',
        'manual_type' => 'Document Type',
        'major_versions' => 'Major Version',
        'manual_language' => 'Language',
        'option' => 'Option',
        'optionaggs' => 'Option',
    ];

    public static function getLabelForEsColumn(string $filter, string $default = ''): string
    {
        if ($default !== '') {
            return self::MAP[$filter] ?? $default;
        }

        return self::MAP[$filter] ?? $filter;
    }
}
