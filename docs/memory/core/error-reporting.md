---
name: error-reporting
description: How PHP errors are reported (SET_ERROR_REPORTING, php_errors.log on Site Log), how a PHP error log is read and its warnings fixed by cause, never with @; what the 2026 PHP 8.5 log clean-up left
---

## How is error reporting set?
- `init.php` and `router.php` set `E_ALL & ~E_NOTICE & ~E_DEPRECATED` unless `data/config.php` defines `SET_ERROR_REPORTING` false; then php.ini decides.
- `pg_error_install()` (`includes/fn/errors.php`, from `init.php`) writes warnings, fatals and uncaught exceptions as JSON lines to `data/temp/php_errors.log`, read on Site Log (`view_log.php`); deprecations only with `DEBUG` on, a call silenced with `@` never (`fn-module-errors-mail-backup-consent.md`). PHP's own `error_log` gets the line too.

## How is a PHP error log read?
- A developer machine's PHP keeps one log for every project: count only lines whose file is under the checkout's `pinegrap/`, not `.claude/worktrees` or scratch folders. Read by path, never by the log's totals.
- Count lines, not requests, grouped by shape: one message per file, numbers and paths folded.
- A log never enters the repository (`*.log` and `pinegrap/data/` are ignored): it holds addresses and request data.

## How is a logged line checked against development?
- Its line number is the checkout's on that day. The `HEAD` reflog gives the commit it ran; read the line there (`git show <commit>:<path>`) and look for its text on `origin/development`; `git log -S'<text>' origin/development -- <path>` names the commit that removed it.
- Without a reflog: `git log --since=<date> origin/development -- <path>`, then `git show <commit>^:<path>`. A cloud clone is shallow: `git fetch --deepen=3000 origin development` first.
- PHPStan level 1-2 (`checkMaybeUndefinedVariables`) also flags a variable set and read under one condition: a hit is the logged warning only with a path that reaches the read.

## How is a warning fixed?
- By its cause, never with `@`: a row that may be missing read `?: array()` and its fields `?? ''`, a key that may be absent `?? ''`, a variable set before the branch, a constant defined once. PHP 7.1 has `??`, not `??=`.
- The screen answers as before; a fix that would change what a screen shows is asked first. A pure function touched gets a test.
- A bundled library: its newer release that is quiet on PHP 8.5 and still runs on 7.1, or the smallest guarded patch written in its `*-embedding.md`; a release that changes behaviour is asked first. Own code: `deprecated-resource-free-calls.md`.

## What did the 2026 log clean-up fix?
- The owner's PHP 8.5 dev log, 10 October to 17 November 2026: 14,782 Pinegrap lines, 125 files, 339 shapes (14,656 warnings, 93 fatals, 33 deprecations); its other 142,000 deprecations were other projects'.
- Fixes by area: `php85-log-warnings-already-fixed.md`, `php-warning-causes-report-screens.md`, `log-warnings-forms-default-screens.md`, `commerce/order-preview-receipt-warnings.md`, `erp/erp-money-overflow.md`, `platform/download-assistant-session.md`; fatals: `view_folder_and_files_f.php` (#10112), the System Status walk (#10113). Most shapes were gone before a fix reached them.
- The census, file by file with the PR of each shape: `git show 42ff0a990b:docs/memory/plans/php-error-log-clean-census.md`, parts `-census-2` to `-census-4`.

## What was left, and why?
- Not code: the Workspace notes import's own "creating the stage folder failed"; PHPMailer's `openssl_sign()` with a DKIM key that is not private (a setting); `session_start()` permission denied (the server's folder); dompdf's float to int cast (unpatched, `dompdf-embedding.md`).
- One 120 s query from `translations_action.php` (12 October) ended inside `db()` and never came back; `init.php` 300 s at `session_start()` waited on a session such a request held.
- Not faults: "Cannot redeclare" from a scratch script compiling every file in one process, a design template included alone from the command line, "Call to undefined function" while a pull had updated a file before its module.
