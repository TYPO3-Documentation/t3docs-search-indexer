<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Config\ManualType;
use PHPUnit\Framework\TestCase;

final class ManualTypeTest extends TestCase
{
    /**
     * @test
     */
    public function getKeyForSystemExtension(): void
    {
        $this->assertEquals('c', ManualType::SystemExtension->getKey());
    }

    /**
     * @test
     */
    public function getKeyForCommunityExtension(): void
    {
        $this->assertEquals('p', ManualType::CommunityExtension->getKey());
    }

    /**
     * @test
     */
    public function getKeyForTypo3Manual(): void
    {
        $this->assertEquals('m', ManualType::Typo3Manual->getKey());
    }

    /**
     * @test
     */
    public function getKeyForCoreChangelog(): void
    {
        $this->assertEquals('changelog', ManualType::CoreChangelog->getKey());
    }

    /**
     * @test
     */
    public function getKeyForDocsHomePage(): void
    {
        $this->assertEquals('h', ManualType::DocsHomePage->getKey());
    }

    public function testGetMap(): void
    {
        $expectedMap = [
            'c' => ManualType::SystemExtension->value,
            'p' => ManualType::CommunityExtension->value,
            'm' => ManualType::Typo3Manual->value,
            'changelog' => ManualType::CoreChangelog->value,
            'h' => ManualType::DocsHomePage->value,
            'other' => ManualType::Typo3Manual->value,
            'typo3cms' => ManualType::ExceptionReference->value,
        ];

        $this->assertEquals($expectedMap, ManualType::getMap());
    }
}