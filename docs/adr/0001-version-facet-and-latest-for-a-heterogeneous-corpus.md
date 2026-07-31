# 1. Version facet and `latest` semantics for a heterogeneous corpus

- Status: **Proposed**
- Date: 2026-07-31
- Deciders: TYPO3 Documentation Team
- Related: #143 (symptoms), #144 (fixes the stale-`latest` maintenance bug)

## Context and problem statement

The index holds two very different kinds of manuals:

- **Core / system manuals**, versioned by the TYPO3 major (e.g. `12`, `13`).
- **~1500 community extensions**, versioned independently — some by real SemVer majors
  (e.g. `georgringer/news`: `9`, `11`, `12`), many still pre-1.0 where *every* release is major `0`
  (e.g. `netresearch/nr-llm`: `0.1` … `0.25`).

The version dimension is stored on every snippet as a single field `major_versions`, derived at index
time from `explode('.', $version)[0]`, plus two synthetic tokens: `all` and `latest`.

`latest` is **major-based**, not "newest version": a manual's *last-versions* set is
"the highest version per major, restricted to the last two majors, plus `main`"
(`Manual::getLastVersions()` → `VersionFilter::filterVersions()`). A snippet is tagged `latest`
iff its version is in that set at index time (`ElasticRepository::addOrUpdateDocument`).

Contrary to how it may look, this is **not** a core rule blindly applied to extensions. The "only
show the last two versions" restriction was introduced *specifically for third-party documentation*
(commit `3d25e20`, gated on `is_core = false`); core manuals are treated differently — a ranking
boost for `is_core` and for the current TYPO3 stable/dev version via `Typo3VersionMapping`. And
version aggregation has been major-granular since #14 (2023): `latest` has always meant "the highest
version per major", never "the single newest version".

The real mismatch is therefore **granularity, not core-vs-extension**: "last two *majors*" is right
for extensions with genuine SemVer majors (e.g. `georgringer/news`: 9 / 11 / 12), but collapses for
pre-1.0 extensions where every release is major `0`. From that, three problems follow:

1. **The version facet is meaningless in cross-manual scope.** In "search all" the `major_versions`
   buckets mix core majors (`12`, `13`) with extension majors (`0`, `1`, `2`, `3`) in one list —
   "version 0" of extension A has nothing to do with "version 0" of extension B, and neither relates
   to TYPO3 `13`.
2. **The major axis conveys nothing for 0.x extensions.** Every release collapses to major `0`.
3. **The axis users actually want for an extension — which TYPO3 version it supports — is not indexed
   at all.** No field carries it, so it cannot be offered as a facet.

(The separate stale-`latest` *maintenance* bug — both the `latest` facet token and the
`is_last_versions` sort field were only ever added, never recomputed — is fixed in #144 and is **not**
re-litigated here; this ADR records the semantics as the baseline.)

## Decision drivers

- The version facet must be meaningful in cross-manual ("search all") scope, not just within one manual.
- One model must serve core (major = TYPO3 version) and community (major = the extension's own line).
- Prefer the axis users can act on; avoid exposing internal artifacts (bare major integers).
- Keep index size and indexing complexity reasonable.
- Data availability: TYPO3-compatibility is *not* on the rendered page, but the docs deployment
  metadata (Intercept) already tracks a "supported TYPO3 version" per render.

## Considered options

1. **Status quo** — one shared `major_versions` facet for everything.
2. **Scope-aware facet** — render the numeric major buckets only where they are meaningful
   (within a single manual, or for the core corpus); in cross-manual scope expose only `latest` / `all`.
3. **TYPO3-compatibility facet for community extensions** — index a new `typo3_versions` field from the
   deployment metadata; keep the core-version facet for core manuals.
4. **Replace the extension version facet with a per-manual `latest`/`all` toggle** — drop major buckets
   for extensions entirely.

## Decision outcome (recommended)

Adopt a staged combination, keeping `latest` semantics unchanged:

- **Baseline (already true after #144):** `latest` = "the manual's current last-versions set", correctly
  maintained on every import. No change of meaning.
- **Step 1 — Option 2 (scope-aware facet):** stop showing the numeric-major mix across manuals. In
  cross-manual scope the version facet is reduced to `latest` vs `all`; numeric majors remain only
  within a single manual / the core corpus. Low cost, removes the most confusing behavior.
- **Step 2 — Option 3 (TYPO3-compatibility facet):** index a `typo3_versions` field for community
  extensions from the deployment metadata and expose it as the primary version-ish facet for them.
  This is the axis users actually filter on. Larger change (new field + reindex + UI), tracked as
  follow-up work.

Option 4 is rejected: it discards useful within-manual version filtering for extensions that *do* have
meaningful majors (e.g. news). Option 1 is rejected: it is the current, demonstrably confusing state.

## Consequences

**Positive**
- Cross-manual search stops presenting a meaningless integer soup.
- Community extensions gain the facet users actually want (TYPO3 compatibility).
- Core behavior is unchanged.

**Negative / cost**
- Step 2 needs a new indexed field, a full reindex, and UI work.
- Compatibility data quality depends on deployment metadata completeness; a fallback (composer
  constraint parsing) may be needed.
- Two facet behaviors (core vs community) add conditional logic.

## Out of scope

Cross-version **result de-duplication** and which version a hit links to (a page that changed across
versions appears once per version; results link to the newest version *within* the de-duplicated
document, not the newest overall). That is a separate design change and will get its own ADR.

## Evidence

Reproduced locally with real rendered docs, two fixtures, imported in release order to mirror
production accumulation (harness: `netresearch/nr-llm` 0.x, `georgringer/news` majors 9/11/12+main):

- In "search all", the version facet exposes buckets `0, 1, 2, 3, 6, 12, 13` — extension majors and
  TYPO3 core majors mixed in one control.
- For `nr-llm` every indexed version is major `0`; the numeric facet distinguishes nothing.
- No `typo3_versions`-style field exists on any snippet, so TYPO3-compatibility cannot be faceted today,
  although Intercept records a "supported TYPO3 version" per deployment.
