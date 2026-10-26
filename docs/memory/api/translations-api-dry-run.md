---
name: translations-api-dry-run
description: The seven translation API writes take X-Dry-Run (PR #1911): where each stops, the batch counts, translate_api_write_check(), pg_tr_result_judge(), pg_tr_reviewed_kept()
---

## Which translation writes can be rehearsed?
- All seven in `includes/translate/api.php` (PR #1911): `translations.write`, `refresh`, `status`, `glossary.save` and Claude's `jobs.claim`, `jobs.results`, `jobs.fail` (these three are no MCP tools, `'mcp' => false`, but REST callers rehearse them too).

## Where does each stop?
- Single writes stop directly above the write: `refresh` after the scope check (detail `scope`, nothing read off the pages); `glossary.save` after every check and the same-wording lookup (`would` created or updated, detail id, language, term, keep); `jobs.claim` after the two 409s (`would` claimed); `jobs.fail` after the closed check (`would` failed).
- Batches ask `api_dry_run_requested()` once, judge every item, skip the writes and stop with the same counts the real call answers: `write` (written, skipped, stale, rejected), `status` with hashes (changed, unchanged, stale), `status` with `all` (changed = a COUNT over `pg_tr_review_all_where()`), `jobs.results` (written, skipped, stale, rejected with retry; the job is not marked running, no item is closed, no error is kept).

## Which checks are shared with the real write?
- `translate_api_write_check()`: empty, `pg_tr_result_check()`, and `pg_tr_reviewed_kept()`; `translate_api_write_one()` calls it before saving.
- `pg_tr_result_judge()` (engines.php): the sanitising and the check of an engine answer; `pg_tr_accept_result()` calls it before saving.
- `pg_tr_reviewed_kept()` (store.php): a machine answer does not replace a reviewed one unless forced; `pg_tr_save_translation()` asks it.

## Where is it tested?
- `tests/translate_api_dry_run_test.php`: the flag, the stop before each write, the batches skipping writes, the writers calling the shared checks.

Related: `api-dry-run.md`, `mcp-dry-run-argument.md`, `erp/erp-api-dry-run.md`
