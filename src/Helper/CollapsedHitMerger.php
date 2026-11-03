<?php

namespace App\Helper;

use Elastica\Result;

/**
 * Turns collapsed search hits (ADR-0002) into one result per section.
 *
 * Each result carries the versions and slugs of all documents of its collapse group, so the
 * hit shows every version of the section. The text shown (title, content, highlights) is
 * taken from the document of the representative version, so the snippet matches the page
 * the hit links to. The plain array shape keeps the template's `hit.data` / `hit.highlights`
 * access working unchanged.
 */
class CollapsedHitMerger
{
    public const INNER_HITS_NAME = 'versions';

    private const TEXT_FIELDS = ['snippet_title', 'snippet_content', 'content_hash'];

    /**
     * @param Result[] $results
     * @return array<int, array{data: array<string, mixed>, highlights: array<mixed>}>
     */
    public static function merge(array $results): array
    {
        $merged = [];
        foreach ($results as $result) {
            $data = $result->getData();
            $highlights = $result->getHighlights();
            $versions = (array)($data['manual_version'] ?? []);
            $slugs = (array)($data['manual_slug'] ?? []);

            $innerHits = $result->getParam('inner_hits')[self::INNER_HITS_NAME]['hits']['hits'] ?? [];
            foreach ($innerHits as $innerHit) {
                $source = $innerHit['_source'] ?? [];
                $versions = array_merge($versions, (array)($source['manual_version'] ?? []));
                $slugs = array_merge($slugs, (array)($source['manual_slug'] ?? []));
            }

            $data['manual_version'] = array_values(array_unique($versions));
            $data['manual_slug'] = array_values(array_unique($slugs));

            $representative = RepresentativeVersion::select($data['manual_version'], (bool)($data['is_core'] ?? false));
            if ($representative !== null && !in_array($representative, (array)($result->getData()['manual_version'] ?? []), true)) {
                foreach ($innerHits as $innerHit) {
                    $source = $innerHit['_source'] ?? [];
                    if (in_array($representative, (array)($source['manual_version'] ?? []), true)) {
                        foreach (self::TEXT_FIELDS as $field) {
                            if (array_key_exists($field, $source)) {
                                $data[$field] = $source[$field];
                            }
                        }
                        $highlights = $innerHit['highlight'] ?? [];
                        break;
                    }
                }
            }

            $merged[] = ['data' => $data, 'highlights' => $highlights];
        }

        return $merged;
    }
}
