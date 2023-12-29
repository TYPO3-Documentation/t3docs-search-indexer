<?php

namespace App\Helper;

class VersionFilter
{
    /**
     * Filters an array of version strings to return only the highest version for each major version.
     *
     * This function groups the provided versions by their major version number, then sorts and selects
     * the highest version within each major version group. It is assumed that the version strings are in
     * the format 'major.minor' or similar. The function returns an array of the highest versions for
     * each major version.
     *
     * @param array<string> $versions
     */
    public static function filterVersions(array $versions): array
    {
        $groupedVersions = [];
        $nonNumericVersions = [];

        foreach ($versions as $version) {
            if (!is_numeric(str_replace('.', '', $version))) {
                $nonNumericVersions[] = $version;
                continue;
            }

            $majorVersion = explode('.', $version)[0];
            $groupedVersions[$majorVersion][] = $version;
        }

        $highestVersions = [];

        foreach ($groupedVersions as $minorVersions) {
            usort($minorVersions, static function($a, $b) {
                return version_compare($b, $a);
            });
            $highestVersions[] = $minorVersions[0];
        }

        return array_merge($highestVersions, $nonNumericVersions);
    }
}
