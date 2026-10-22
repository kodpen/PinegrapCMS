---
name: server-config-web-root-rules
description: includes/server_config.php: why web-root web.config/.htaccess rules live in one list, how to add a block, default-must-pass-detect invariant, repair rules.
---

## Why is there a single rule list?
- The web-root `web.config` / `.htaccess` is outside the software folder; no update package carries it. A rule written only in `install/index.php` reaches new installs only.
- So the list lives in `includes/server_config.php` (not under `install/`: the install folder can be deleted from a live server, same reason as `includes/migrations/runner.php`). Two consumers read it:
  - `install/index.php`: file missing -> writes everything with `pg_server_config_default()`; file present -> `pg_server_config_repair(false)` adds only the missing.
  - System Status card: scans block by block with `pg_server_config_scan()`; the admin tile calls `pg_server_config_repair(true)` (`api.php` -> `server_config_repair`).

## How is a new rule added?
- Add a block to `pg_server_config_blocks($server)`: `key` (stable id), `label`, `why` (one sentence: what is exposed without it), `level` (`core` / `required` / `recommended`), `detect` (regex searched in the file), `snippet`, `anchor`.
- Server-specific branch only; IIS and Apache variants written separately. Then also put it into the file skeleton in `pg_server_config_default()`.
- **Invariant: the default must pass its own `detect`.** Otherwise a fresh install declares itself "missing" on day one. When adding a block, generate the defaults of all three servers and run every `detect` over them.
- If the rule carries the folder name, add it to `pg_server_config_retarget()` too.
- A header PHP sends and some pages replace (`Referrer-Policy`) never goes into a server rule, and `X-Content-Type-Options` from IIS makes PHP skip its copy: docs/memory/waf/waf-security-headers-hsts.md.

## What are the repair rules?
- Only adds. Nothing already in the file is rewritten (single exception: the redirect path via `$fix_path`).
- String surgery, not DOM: `DOMDocument::saveXML()` re-indents the whole file and moves comments; the operator's file must stay inspectable.
- A block with no anchor is skipped, never written to an invented place (a `<hiddenSegments>` outside `<requestFiltering>` makes every request 500). Skipped ones are counted and said in the message: saying "nothing was missing" when all were skipped, while System Status says "3 missing", is a lie.
- An IIS result is not written before it parses with `simplexml_load_string`: an unreadable web.config makes the whole site 500.19, after the operator pressed a button saying "makes it safer".
- Backup goes to `data/temp/server_config/`: a `.bak` next to `.htaccess` would be served as plain text; `data/` is what the written rule protects.
- When writing at the closing tag's offset use `pg_server_config_split_indent()`: text up to that point ends with the tag's indent, appending the snippet directly indents the first line twice.

Related: docs/memory/platform/server-config-paths-and-detection.md
