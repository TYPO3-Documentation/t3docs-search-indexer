<?php


namespace App\Helper;


class VersionSorter
{
    public static function sortVersions(array $versions, string $direction='asc'):array
    {
        usort($versions, function ($a, $b) {
            if ($a === 'main') {
                return 1;
            }
            if ($b === 'main') {
                return -1;
            }
            return version_compare($a, $b);
        });

        if ($direction === 'desc') {
            $versions = \array_reverse($versions);
        }
        return $versions;
    }
}
