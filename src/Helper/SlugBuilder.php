<?php

namespace App\Helper;

use App\Dto\SearchDemand;

class SlugBuilder
{
    /**
     * For a search result, build the best default URL.
     * Manual slug is an array of the backend, we extract the best slug.
     *
     * @param array<string, mixed> $data
     */
    public static function build(array $data, SearchDemand $searchDemand, ?string $forceVersion = null): string
    {
        $allVersion = VersionSorter::sortVersions($data['manual_version'], 'desc');
        $allSlugs = is_array($data['manual_slug']) ? $data['manual_slug'] : [$data['manual_slug']];
        $requestedVersion = $forceVersion ?: ($searchDemand->getFilters()['major_versions'][0] ?? null);

        if ($requestedVersion === 'latest') {
            $requestedVersion = $allVersion[0];
        }

        $requestedVersion = explode('.', $requestedVersion)[0];

        // If a major version is seeked, replace the version in the slug to match.
        if ($requestedVersion) {
            $targetVersion = null;
            foreach ($allVersion as $version) {
                if (str_starts_with($version, $requestedVersion . '.') or $version === $requestedVersion) {
                    $targetVersion = $version;
                    break;
                }
            }
        } else {
            $targetVersion = $allVersion[0];
        }

        if ($targetVersion) {
            $targetSlug = array_filter($allSlugs, function ($slug) use ($targetVersion) {
                return str_contains($slug, $targetVersion);
            });

            return reset($targetSlug);
        }

        // Fallback should not happen
        return reset($allSlugs);
    }
}
