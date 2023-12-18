<?php

namespace App\Tests\Unit\Dto;

use App\Dto\Constraints;
use PHPUnit\Framework\TestCase;

class ConstraintsTest extends TestCase
{
    /**
     * @test
     */
    public function canBeInstantiatedWithDefaultValues(): void
    {
        $constraints = new Constraints();

        $this->assertInstanceOf(Constraints::class, $constraints);
        $this->assertSame('', $constraints->getSlug());
        $this->assertSame('', $constraints->getVersion());
        $this->assertSame('', $constraints->getType());
        $this->assertSame('', $constraints->getLanguage());
    }

    /**
     * @test
     */
    public function canBeInstantiatedWithCustomValues(): void
    {
        $constraints = new Constraints('m/typo3/reference-coreapi/12.4/en-us', '12.4', 'TYPO3 Manual', 'en-us');

        $this->assertInstanceOf(Constraints::class, $constraints);
        $this->assertSame('m/typo3/reference-coreapi/12.4/en-us', $constraints->getSlug());
        $this->assertSame('12.4', $constraints->getVersion());
        $this->assertSame('TYPO3 Manual', $constraints->getType());
        $this->assertSame('en-us', $constraints->getLanguage());
    }

    /**
     * @test
     */
    public function getSlugReturnsCorrectValue(): void
    {
        $constraints = new Constraints('m/typo3/reference-coreapi/12.4/en-us');
        $this->assertSame('m/typo3/reference-coreapi/12.4/en-us', $constraints->getSlug());
    }

    /**
     * @test
     */
    public function getVersionReturnsCorrectValue(): void
    {
        $constraints = new Constraints('', '12.4');
        $this->assertSame('12.4', $constraints->getVersion());
    }

    /**
     * @test
     */
    public function getTypeReturnsCorrectValue(): void
    {
        $constraints = new Constraints('', '', 'TYPO3 Manual');
        $this->assertSame('TYPO3 Manual', $constraints->getType());
    }

    /**
     * @test
     */
    public function getLanguageReturnsCorrectValue(): void
    {
        $constraints = new Constraints('', '', '', 'en-us');
        $this->assertSame('en-us', $constraints->getLanguage());
    }
}
