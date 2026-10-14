---
name: upgrade-runner-behavior
description: How the upgrade runner reacts to failing statements, locks and fatals, and how the install screen runs one version per request, with heavy-table preflight.
---

## What happens when a schema statement fails while the runner runs?
- `db()` / `db_value()` / `db_item()`... ask `install_query_failed()` on a failed schema statement: 1050 (table exists), 1060 (column exists), 1061 (index exists), 1091 (DROP target missing), and 1146 on `DROP TABLE` are noted and skipped.
- Every other error (1062 duplicate entry, 1054, 1064...) becomes `InstallQueryException`: the loop marks the version "failed", `config.version` stays at the last completed version, the screen shows the statement + MySQL text. No `exit()`.

## How does the run loop behave?
- `install_run_upgrades($versions, $from_key, array('stream' => true))`: `GET_LOCK` (else `flock`), `ignore_user_abort(true)`, shutdown guard (a fatal error is also written to the progress file).
- The version marker is written the moment the function returns, before anything is printed to the screen.
- An interrupted upgrade resumes where it stopped via "Tekrar dene". A syntax error in a file is caught as `ParseError` and stops only that version.

## How does the screen run (since 2026-08-29)?
- One version per request: JS calls `install_action=upgrade_step` with `array('one' => true)` and continues using `next` in the response. `locked` -> wait 5 s; error -> "Tekrar dene"; no response -> "Devam Et".
- A failed step is written to `data/temp/upgrade_last.json`; the "Son koşum" line and the "Yükseltmeye devam et" button on screen open come from it; deleted when the version passes.
- The endpoint does `session_write_close()` after the auth check (a long step must not lock the panel).
- Without JS, form submit falls back to the old single-request flow.
- A new version that rewrites a big table: add `'<version>' => array('<table>')` to the `install_heavy_tables()` map; the preflight card builds its row-count / size warning from it. Preflight warns, never blocks.

Related: docs/memory/release/upgrade-automation-and-testing.md
