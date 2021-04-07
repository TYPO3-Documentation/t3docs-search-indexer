<?php


namespace App\Helper;


class VersionSorter
{
    public static function sortVersions(array $versions, string $direction='asc'):array
    {
        usort($versions, function ($a, $b) {
            if ($a === 'master') {
                return 1;
            }
            if ($b === 'master') {
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
