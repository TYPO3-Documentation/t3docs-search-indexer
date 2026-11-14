<?php

namespace App\Tests\Unit\Helper;

use App\Helper\SnippetId;
use PHPUnit\Framework\TestCase;

class SnippetIdTest extends TestCase
{
    private function section(array $override = []): array
    {
        return array_merge([
            'manual_title' => 'netresearch/nr-llm',
            'manual_type' => 'Community extension',
            'manual_language' => 'en-us',
            'manual_version' => '0.3',
            'relative_url' => 'Developer/Index.html',
            'fragment' => 'response-objects',
            'content_hash' => 'aaa',
        ], $override);
    }

    /**
     * @test
     */
    public function versionsOfOneSectionShareTheId(): void
    {
        self::assertSame(
            SnippetId::fromSection($this->section()),
            SnippetId::fromSection($this->section(['manual_version' => 'main', 'content_hash' => 'bbb']))
        );
    }

    /**
     * relative_url is manual-relative: the same page and anchor in another manual is a
     * different section and must not collapse with it.
     *
     * @test
     * @dataProvider otherManualDataProvider
     */
    public function sameRelativeUrlInAnotherManualGetsAnotherId(array $override): void
    {
        self::assertNotSame(
            SnippetId::fromSection($this->section()),
            SnippetId::fromSection($this->section($override))
        );
    }

    public function otherManualDataProvider(): array
    {
        return [
            'other manual title' => [['manual_title' => 'vendor/other-extension']],
            'other manual type' => [['manual_type' => 'System extension']],
            'other language' => [['manual_language' => 'de-de']],
            'other page' => [['relative_url' => 'Api/Index.html']],
            'other fragment' => [['fragment' => 'introduction']],
        ];
    }
}
