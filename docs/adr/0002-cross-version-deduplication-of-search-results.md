# 2. Cross-version de-duplication of search results

- Status: **Proposed**
- Date: 2026-07-31
- Deciders: TYPO3 Documentation Team
- Related: #143 (symptoms), ADR-0001 (deferred this here), #144 (fixed the stale-`latest` bug)

## Context and problem statement

Every snippet (a section/fragment of a page) is stored as one Elasticsearch document keyed by
**content**: `_id = manual_title + relative_url + content_hash + fragment` — deliberately *without*
the version, so identical content across versions collapses into a single document whose
`manual_version` array lists all versions it appears in.

The flip side: when a section's **content changes** between versions, its versions no longer share a
content hash and become **separate documents**. The main search (`findByQuery`) returns *every*
matching document as its own hit — there is **no field-collapsing and no PHP-side de-duplication** —
and `SlugBuilder` links each hit to the newest version *within that fragmented document*, not the
newest overall.

So the same section shows up once per version it diverged in. Measured on a real manual
(`netresearch/nr-llm`, 5 rendered versions): **123 of 1227 sections (~10%) are fragmented across
versions**, e.g. `Adr023…#… → {0.19, 0.25}, {main}` — two separate hits. This even survives the
`latest` filter: `0.25` and `main` are both last-versions, so both fragments are tagged `latest` and
both appear. (The user-visible symptom in #143: "Azure OpenAI" listed twice with `main` and `1.0`
badges.) This is orthogonal to #144 — #144 removes *obsolete* versions from `latest`; this is
duplication *within* the current versions.

The mapping already carries an **unused `snippet_id` keyword field** (never populated, never queried)
— a natural collapse key that looks intended for exactly this and was never wired up.

## Decision drivers

- A section should appear **once**, linking to its representative version (current stable, see
  below), with the other versions available (badges) — not once per diverged version.
- Distinct sections of different manuals must never be merged.
- Must keep pagination, `total`, and the facet aggregations meaningful.
- Should fight relevance ranking as little as possible.
- Prefer native Elasticsearch over fragile PHP-side post-processing.

## Considered options

1. **Status quo** — every document is its own hit.
2. **ES `collapse` on a per-section key** — populate `snippet_id` with the section's identity across
   versions (see below) and add `collapse: { field: snippet_id, inner_hits: … }` to the search query.
   One hit per section; `inner_hits` expose the per-version documents for the version badges.
3. **ES `collapse` on `relative_url`** — one hit per *page*; coarser, merges distinct sections of a
   page into a single hit (likely too aggressive).
4. **PHP-side de-duplication after fetching** — breaks pagination and `total`; rejected.

## Decision outcome (recommended)

**Option 2** — collapse on a new `snippet_id`:

- Populate `snippet_id` in `ImportManualHTMLService` (a full reindex backfills it).
- Add `collapse` + `inner_hits` to `getDefaultSearchQuery`; the version badges come from `inner_hits`.
- Link the hit (via `SlugBuilder`) to the group's **representative version** (rule below).

**Collapse key = the manual's identity plus the section, without version and content hash:**
`snippet_id = manual_title + manual_type + manual_language + relative_url + '#' + fragment`.
`relative_url` alone is not enough: it is *manual-relative* (`$file->getRelativePathname()` of a
`Finder` rooted at the manual's own directory), and the result template prefixes it with the manual
slug. `Index.html#introduction` is therefore byte-identical across manuals, and a key of
`relative_url#fragment` would merge unrelated manuals into one hit and drop real results. The key is
the document `_id` (`manual_title + relative_url + content_hash + fragment`) minus the content hash,
plus type and language.

**Representative version = the current stable TYPO3 version, falling back to the newest.**
The newest version of a group is usually `main`, i.e. the development docs; for a group like
`{14.3, main}` users want `14.3`. The rule:

- Core documentation (`is_core`): `Typo3VersionMapping::Stable->getVersion()` (currently `14.3`) if the
  group contains it, otherwise the newest version (`main` counts as newest).
- Community extensions (no TYPO3 version mapping applies): their newest non-`main` release, or `main`
  if the section exists only there.

The representative decides the link target **and** the text shown (snippet title, content and
highlight are taken from the representative version's document), so the user reads what they land
on. The order of the collapsed groups stays relevance-driven (ES keeps the best-scoring document per
group for sorting); the other versions remain available as badges.

## Consequences

**Positive**
- Each section appears once, linked to its representative (stable) version; badges show where else
  it exists.
- The residual duplication that `latest` cannot remove disappears.

**Negative / cost**
- A new indexed field + a full reindex. Collapse must not go live before every document carries
  `snippet_id`: documents without the field fall into one shared collapse group, and the
  distinct-section count reads 0 — measured on a local index without the field, `q=openai` returned
  "No search results" instead of 134 documents. Since the importer that writes `snippet_id` ships
  with the collapse query, roll out in order: populate `snippet_id` (search unchanged), full
  re-import, then enable the collapse — two deploys, or a switch for the collapse.
- Query complexity (`collapse` + `inner_hits`) and template changes to render the grouped hit.
- `total`/counts under collapse: ES `collapse` does **not** change `hits.total`; an accurate
  distinct-section count needs a `cardinality` aggregation on `snippet_id`.
- Default/`latest` links of core hits move from `main` to the current stable version.

## Out of scope

The version *facet* redesign and the TYPO3-compatibility facet (ADR-0001); the `latest` maintenance
fix (#144).

## Evidence

- No `collapse`/`inner_hits` anywhere today; `findByQuery` returns raw hits and `SlugBuilder` links to
  the newest version within a single fragmented document.
- `snippet_id` is present in `config/Elasticorn/docsearch/Mapping.yaml` (type `keyword`, so it can
  be a collapse field) but is never set or queried.
- Manual-relative `relative_url` (at `main` 01a9512): `src/Service/ImportManualHTMLService.php:77`
  (`$file->getRelativePathname()`), the files come from `Manual::getFilesWithSections()`
  (`src/Dto/Manual.php:79`, `->in($this->getAbsolutePath())`), and
  `templates/search/search.html.twig:77` builds the link as `{{ bestSlug }}/{{ relative_url }}#…`.
  The `_id` includes `manual_title` for this reason: `src/Repository/ElasticRepository.php:81-82`.
- Stable version: `t3docs/typo3-version-handling` 0.38.0 (the locked version) maps
  `Typo3VersionMapping::Stable` to `14.3` (`src/Typo3VersionMapping.php:28`); it already gets a
  scoring boost next to `Dev` in `getDefaultSearchQuery()` (`src/Repository/ElasticRepository.php:615-631`).
- `netresearch/nr-llm`: 123/1227 sections (~10%) fragment across versions; the duplication persists
  under `latest` because several last-versions carry the same section divergently.
