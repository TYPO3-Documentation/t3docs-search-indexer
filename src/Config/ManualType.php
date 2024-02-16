<?php

declare(strict_types=1);

namespace App\Config;

enum ManualType: string
{
    case SystemExtension = 'System extension';
    case CommunityExtension = 'Community extension';
    case Typo3Manual = 'TYPO3 manual';
    case CoreChangelog = 'Core changelog';
    case DocsHomePage = 'Docs Home Page';
    case ExceptionReference = 'Exception Reference';

    public function getKey(): string
    {
        return match ($this) {
            self::SystemExtension => 'c',
            self::CommunityExtension => 'p',
            self::Typo3Manual => 'm',
            self::CoreChangelog => 'changelog',
            self::DocsHomePage => 'h',
            self::ExceptionReference => 'typo3cms',
        };
    }

    public static function getMap(): array
    {
        return [
            'c' => self::SystemExtension->value,
            'p' => self::CommunityExtension->value,
            'm' => self::Typo3Manual->value,
            'changelog' => self::CoreChangelog->value,
            'h' => self::DocsHomePage->value,
            'other' => self::Typo3Manual->value,
            'typo3cms' => self::ExceptionReference->value,
        ];
    }
}