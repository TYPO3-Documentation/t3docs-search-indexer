<?php

namespace App\Helper;

use App\Dto\SearchDemand;
use Elastica\Result;

/**
 * Turns collapsed search hits (ADR-0002) into one result per section.
 *
 * Each result carries the versions and slugs of all documents of its collapse group, so the
 * hit shows every version of the section. The text shown (title, content, highlights) is
 * taken from the document of the version the hit links to (SlugBuilder::targetVersion), so
 * the snippet matches the linked page, also under a version filter. The plain array shape keeps the template's `hit.data` / `hit.highlights`
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
    public static function merge(array $results, SearchDemand $searchDemand): array
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

            // The text comes from the document of the version the hit links to (SlugBuilder).
            $target = SlugBuilder::targetVersion($data, $searchDemand);
            if ($target !== null && !in_array($target, (array)($result->getData()['manual_version'] ?? []), true)) {
                foreach ($innerHits as $innerHit) {
                    $source = $innerHit['_source'] ?? [];
                    if (in_array($target, (array)($source['manual_version'] ?? []), true)) {
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
