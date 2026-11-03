<?php

namespace App\Helper;

use T3Docs\VersionHandling\Typo3VersionMapping;

/**
 * The version a search hit links to when no specific version is requested (ADR-0002).
 *
 * Core documentation: the current stable TYPO3 version if the hit has it, otherwise the
 * newest version ("main" counts as newest). Community extensions: their newest non-main
 * release, or "main" if that is the only version.
 */
class RepresentativeVersion
{
    /**
     * @param array<string> $versions
     */
    public static function select(array $versions, bool $isCore): ?string
    {
        if ($versions === []) {
            return null;
        }

        $sorted = VersionSorter::sortVersions(array_values($versions), 'desc');

        if ($isCore) {
            $stable = Typo3VersionMapping::Stable->getVersion();
            return in_array($stable, $sorted, true) ? $stable : $sorted[0];
        }

        foreach ($sorted as $version) {
            if ($version !== 'main') {
                return $version;
            }
        }

        return $sorted[0];
    }
}
