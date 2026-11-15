# Integration tests

These tests run against a **real Elasticsearch** instance and cover behaviour the
unit tests mock away — the Painless `latest` recompute and the content-hash
de-duplication.

CI runs them against an Elasticsearch service container. To run them locally:

```bash
composer ci:test:integration
```

- Inside DDEV the ES host is preset (`ELASTICA_HOST=elasticsearch`); elsewhere export
  `ELASTICA_HOST` (and `ELASTICA_PORT` if needed) before running.
- Each test uses an isolated throwaway index (`docsearch_test`), created from the real
  `config/Elasticorn/docsearch` mapping/settings and dropped again on teardown, so it
  never touches the `docsearch` index.

The unit suite (`composer ci:test:unit`) covers `tests/Unit` only and needs no ES.
