# AGENTS.md — TYPO3 Documentation Search Indexer

`TYPO3-Documentation/t3docs-search-indexer` — the Symfony application behind
the search on docs.typo3.org. It does two things:

1.  **Index**: crawl the *rendered HTML* of every manual on the docs server
    and store each `<section>` as a snippet in Elasticsearch.
2.  **Serve**: provide the search frontend (`/`, `/search`) and the JSON
    suggestion endpoint (`/suggest`) used by the search modal on
    docs.typo3.org.

Unlike most repos under `TYPO3-Documentation/`, this is **not a manual** —
it is PHP application code. There is no `Documentation/` folder, no
`Makefile`, and no reST to render. Changes here are code changes and are
validated with unit tests and PHP CS Fixer, not with a docs build.

Stack: PHP 8.2, Symfony 6.4, Twig, Elasticsearch 7.17 via `ruflin/elastica`,
DDEV for local development, Magallanes (`.mage.yml`) for deployment.

## Repo structure

```
src/Command/          # console commands: docsearch:import, docsearch:index:delete
src/Service/          # crawling & parsing: DirectoryFinderService,
                      #   ImportManualHTMLService, ParseDocumentationHTMLService
src/Dto/Manual.php    # one manual (or sub-manual); decides which files get indexed
src/QueryBuilder/     # ElasticQueryBuilder — the search query, boosting, aggregations
src/Repository/       # ElasticRepository — index/search/delete against Elasticsearch
src/Controller/       # SearchController: /, /search, /suggest
templates/            # Twig templates for the search frontend
config/services.yaml  # allowed_paths, excluded_directories, CDN asset versions
config/Elasticorn/    # Elasticsearch index configuration & mapping (used by Elasticorn)
tests/Unit/           # PHPUnit tests + HTML fixtures
.ddev/                # local environment, incl. the Elasticsearch container
.github/workflows/    # ci.yml (lint, CS, tests), docs-search.yml (manual deployment)
```

## Commands

Everything runs inside DDEV (`ddev start` first); see `README.rst` for the
full local setup, including the `docs_server` folder with rendered manuals.

- `ddev exec composer ci:test:unit` — run the unit tests
- `ddev exec composer ci:php:cs-fixer` — check coding standards (dry run, as CI does)
- `ddev exec composer fix:php:cs-fixer` — apply coding standards to `src/`
- `ddev exec composer ci:php:lint` — PHP syntax lint
- `ddev exec ./bin/console docsearch:import [<packagePath>]` — index everything,
  or a single manual relative to `DOCS_ROOT_PATH`
- `ddev exec ./bin/console docsearch:index:delete --manual-…` — remove manuals
  from the index by slug, package, version, type or language

Run the tests and the CS check before proposing a change — CI
(`.github/workflows/ci.yml`) runs exactly these three on every PR.

## Things that are easy to get wrong

1.  **The indexer reads rendered HTML, not reST.** Behaviour depends on the
    markup produced by `TYPO3-Documentation/render-guides`. When a change
    concerns "what gets indexed", the fixtures under
    `tests/Unit/Service/Fixtures/` are the reference for both the current
    and the legacy theme markup — update both variants where they exist.
2.  **A directory only counts as a manual if it contains `objects.inv`**
    (or `objects.inv.json`) — see `DirectoryFinderService::objectsFileExists()`.
    A missing index is more often a missing `objects.inv` than a bug.
3.  **`typo3/cms-core` is special.** Only its `main` version is indexed
    (`DirectoryFinderService::isNotIgnoredPath()`), the `Changelog` folder is
    excluded from the manual itself (`Manual::getFilesWithSections()`) and
    re-indexed as sub-manuals instead (`Manual::getSubManuals()`).
4.  **Passing an explicit `<packagePath>` bypasses `allowed_paths`** and the
    `cms-core` filter, so a local single-manual import can index something the
    full run would skip. That is intentional — don't "fix" it.
5.  **Search relevance is tuned, not accidental.** Boosting, version handling
    and aggregations in `ElasticQueryBuilder` encode documentation-team
    decisions (see `CHANGELOG.md`). Don't retune them as a side effect of an
    unrelated change.
6.  **Mapping changes need a reindex.** `config/Elasticorn/docsearch/` is
    applied by Elasticorn, not by the application; an altered mapping has no
    effect until the index is re-initialised and the manuals re-imported.
7.  **CDN asset versions are pinned** in the `assets` section of
    `config/services.yaml` and must be raised together — theme CSS and JS come
    from the same `typo3-docs-theme` version.

## Commit and pull request conventions

- Prefix the subject with `[TASK]`, `[BUGFIX]`, or `[FEATURE]` followed by a
  short imperative summary (the history also contains `[FIX]` and `[CHORE]`;
  prefer the three above for new commits).
- Explain *why* in the body — the diff already shows what changed.
- If AI assistance went beyond spelling/grammar, add an
  `Assisted-by: <tool/model name> <contact>` trailer.
- This repo has **no LTS branches** — everything targets `main`, so there is
  no `Releases:` trailer and no `backport <version>` label to add, unlike the
  manual repos. (The `backport required` / `backport done` labels exist but
  are unused here.)
- When a PR contains a single commit, the PR title and body must match that
  commit's subject and body.
- **Never commit or push without being asked.**

## Deployment

`main` is deployed to prod.docs.typo3.com with Magallanes, via the
`docs-search` GitHub Actions workflow — `workflow_dispatch` only, so a merge
to `main` does *not* deploy by itself; someone triggers it.

## References

- `README.rst` — local setup, indexing usage, Kibana, exclusion rules
- `CHANGELOG.md` — notable search/indexing behaviour changes
- [render-guides](https://github.com/TYPO3-Documentation/render-guides) — produces the HTML this app indexes
