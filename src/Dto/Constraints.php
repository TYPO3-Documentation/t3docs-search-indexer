<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * @deprecated Can be replaced by SearchDemand.
 */
readonly class Constraints
{
    public function __construct(
        private string $slug = '',
        private string $version = '',
        private string $type = '',
        private string $language = '',
        private string $package = ''
    ) {
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getPackage(): string
    {
        return $this->package;
    }
}
