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

- A section should appear **once**, linking to its newest version, with the other versions available
  (badges) — not once per diverged version.
- Must keep pagination, `total`, and the facet aggregations meaningful.
- Should fight relevance ranking as little as possible.
- Prefer native Elasticsearch over fragile PHP-side post-processing.

## Considered options

1. **Status quo** — every document is its own hit.
2. **ES `collapse` on a per-section key** — populate `snippet_id = relative_url + '#' + fragment` and
   add `collapse: { field: snippet_id, inner_hits: … }` to the search query. One hit per section;
   `inner_hits` expose the per-version documents for the version badges.
3. **ES `collapse` on `relative_url`** — one hit per *page*; coarser, merges distinct sections of a
   page into a single hit (likely too aggressive).
4. **PHP-side de-duplication after fetching** — breaks pagination and `total`; rejected.

## Decision outcome (recommended)

**Option 2** — collapse on a new `snippet_id` (`relative_url#fragment`):

- Populate `snippet_id` in `ImportManualHTMLService` (a full reindex backfills it).
- Add `collapse` + `inner_hits` to `getDefaultSearchQuery`; the version badges come from `inner_hits`.
- Link the hit (via `SlugBuilder`) to the **newest** version in the group.

**Open sub-decision for the team — representative = newest version vs. highest-scoring document.**
ES `collapse` keeps the top-*sorted* document per group. If the outer sort is relevance (score), the
representative is the best-matching version, which may be an old one; if we sort by version we lose
per-group relevance. Recommended: order the collapsed groups by score, but pick the **newest**
version inside each group for the link/snippet (via `inner_hits` sorted by a version rank). This keeps
result ordering relevance-driven while pointing users at current docs.

## Consequences

**Positive**
- Each section appears once, linked to its newest version; badges show where else it exists.
- The residual duplication that `latest` cannot remove disappears.

**Negative / cost**
- A new indexed field + a full reindex.
- Query complexity (`collapse` + `inner_hits`) and template changes to render the grouped hit.
- `total`/counts under collapse: ES `collapse` does **not** change `hits.total`; an accurate
  distinct-section count needs a `cardinality` aggregation on `snippet_id`.
- The relevance-vs-newest tension above.

## Out of scope

The version *facet* redesign and the TYPO3-compatibility facet (ADR-0001); the `latest` maintenance
fix (#144).

## Evidence

- No `collapse`/`inner_hits` anywhere today; `findByQuery` returns raw hits and `SlugBuilder` links to
  the newest version within a single fragmented document.
- `snippet_id` is present in `config/Elasticorn/docsearch/Mapping.yaml` but is never set or queried.
- `netresearch/nr-llm`: 123/1227 sections (~10%) fragment across versions; the duplication persists
  under `latest` because several last-versions carry the same section divergently.
