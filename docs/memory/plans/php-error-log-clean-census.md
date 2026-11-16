---
name: php-error-log-clean-census
description: Census of the PHP error log (plan php-error-log-clean, step EL1): what was read, how lines were grouped and checked, the totals; the groups by file are in the numbered parts
---

## What was read?
- The owner's dev machine, read on 2026-11-17, both logs streamed read-only: PHP's own `error.log` at the main checkout's root (15 October to 17 November 2026) and the site's `data/temp/php_errors.log` (JSON lines, 10 October to 14 November).
- Only lines whose file is under the main checkout's `pinegrap/` count; worktrees under `.claude/`, scratch folders and other projects are left out.
- The census is being written; the groups follow in this file's numbered parts.
