<?php

namespace App\Helper;

/**
 * Identity of a section across versions, used as the collapse key (ADR-0002).
 *
 * relative_url is relative to the manual, so the manual's identity (title, type,
 * language) is part of the key; version and content hash are not, so the versions
 * of one section collapse into a single hit.
 */
class SnippetId
{
    /**
     * @param array<string, mixed> $section
     */
    public static function fromSection(array $section): string
    {
        return implode('|', [
            $section['manual_title'],
            $section['manual_type'],
            $section['manual_language'],
            $section['relative_url'],
        ]) . '#' . $section['fragment'];
    }
}
