---
name: static-analysis
description: CI's PHPStan level 0 and PHPCompatibility (PHP 7.1+) checks: what they catch, baselines and how they shrink, guarded newer APIs, pinned tools, running them in a session
---

## What runs, and why?
- `bash tools/static_analysis.sh` (two steps of `php-checks.yml`): PHPStan 2.3.1 level 0 over the product tree (`tools/phpstan.neon`, `phpVersion` 7.1-8.5) and PHP_CodeSniffer 4.0.4 with PHPCompatibility 10.0.0-alpha2, `testVersion 7.1-` (`tools/phpcs.xml`). Before them CI ran `php -l` on PHP 8.3 only, which passes a function PHP 7.1 lacks, a variable never set or a duplicate array key.
- Vendored libraries are scanned for their classes, not analysed; `data/` (the starter sites' layouts) is skipped; the root `download_assistant.php` and `index.php` are checked.

## What are the baselines?
- What the tree held when the checks went in: 48 PHPStan errors, 200 PHPCompatibility findings (guarded APIs, the dead `mysql_*`/`pfpro`/`mcrypt` paths, nested named functions, PHP 8.1-8.5 deprecations; `plans/php-compatibility-and-bugs.md`). `tools/phpstan-baseline.neon` counts per file and message, `tools/phpcs-baseline.json` per file and sniff (`tools/check_php_compat.php` compares). More than the count fails; fewer passes.
- After a fix, `bash tools/static_analysis.sh --generate-baseline` in the same PR shrinks them. Never regenerate to let a new finding through; the baseline diff shows it.
- `reportUnmatchedIgnoredErrors` is off, so a fix merged without the baseline update stays green.

## What does new code do with a newer API?
- Guard it (`function_exists()`, `defined()`, `PHP_VERSION_ID`) and mark the line, since PHPCompatibility cannot see guards: `// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.imageavifFound -- guarded by function_exists()`. PHPStan reads the guard itself.
- A function that always throws or exits gets `@return never` in its docblock, or PHPStan reports a missing return after a call to it (`pg_webauthn_fail()`).
- "Constant not found" is ignored: constants come from the config at run time.

## Why the bootstrap file?
- Some product files include others relative to `pinegrap/`, the working directory at run time. `tools/phpstan-bootstrap.php` puts `pinegrap/` on the include path so PHPStan resolves them while it runs from the repository root, whose relative file names GitHub needs for annotations.

## Where do the tools come from?
- Nothing enters the product or a Composer file. The script downloads the pinned phars (SHA-256 checked) and fetches both standards by commit into `$RUNNER_TEMP/pinegrap-analysis` in CI, `~/.cache/pinegrap-analysis` elsewhere (`PG_ANALYSIS_TOOLS` overrides). A cloud session reaches GitHub releases and git; Packagist is blocked there.
- A version change edits the pins in the script and regenerates both baselines in the same PR. PHPCS 4 refuses a ruleset that excludes a sniff its standard lacks: the `trim()` form-feed sniff (PHP 8.6) is not in 10.0.0-alpha2, so the ruleset excludes nothing yet.

## How long does it take?
- PHPStan cold 2-4 minutes on two cores (its result cache makes a rerun take a second), phpcs about 40 seconds.

Related: docs/memory/plans/php-compatibility-and-bugs.md, docs/memory/workflow/sandbox-and-environment.md
