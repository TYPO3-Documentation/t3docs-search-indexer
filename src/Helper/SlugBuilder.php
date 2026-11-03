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

        // Without a specific version, link to the representative version (ADR-0002):
        // current stable for core documentation, newest non-main release for extensions.
        $representativeVersion = RepresentativeVersion::select($allVersion, (bool)($data['is_core'] ?? false));

        if ($requestedVersion && in_array($requestedVersion, $allVersion, true)) {
            // An exact version of this hit (e.g. a version badge): link to exactly that one,
            // not to another minor of the same major.
            $targetVersion = $requestedVersion;
        } elseif ($requestedVersion && $requestedVersion !== 'latest') {
            // If a major version is seeked, replace the version in the slug to match.
            $requestedVersion = explode('.', $requestedVersion)[0];
            $targetVersion = null;
            foreach ($allVersion as $version) {
                if (str_starts_with($version, $requestedVersion . '.') or $version === $requestedVersion) {
                    $targetVersion = $version;
                    break;
                }
            }
            // No exact match (e.g. the "all" pseudo-filter, or a version not present in this
            // section): link to the representative version instead of an arbitrary one.
            $targetVersion ??= $representativeVersion;
        } else {
            $targetVersion = $representativeVersion;
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
