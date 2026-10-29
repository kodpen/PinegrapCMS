---
name: css-bundles
description: The panel stylesheet served minified (pg_css_bundles(), tools/build_css.php, tools/css_minify.php): what the minifier removes and keeps, src mode, the CI and pre-push check, how it was proved equal
---

## Which stylesheet is minified, and from what?
- `pg_css_bundles()` (`includes/fn/output.php`) maps each `.min.css` under `assets/css/` to its source: today `backend.min.css` from `backend.src.css` (#2054: 596 KB to 386 KB, 132 to 64 KB gzipped).
- `pg_default_control_panel_stylesheet_url()` answers with the `.min.css` when the site runs minified (`ENVIRONMENT_SUFFIX` `min`) and the file is there, else with the source, so `src` mode and an update that left the copy out still work. A private-label stylesheet (`CONTROL_PANEL_STYLESHEET_URL` in config) is not touched.
- After editing `backend.src.css`, run `php tools/build_css.php` (PHP only, no Node). CI and `tools/pre_push.sh` run `--check`; a stale `.min.css` fails the PR. The cache clear of System Status touches both files.

## What does the minifier take out, and what does it keep?
- `pg_css_minify()` (`tools/css_minify.php`, `tests/css_minify_test.php`): comments go; a whitespace run becomes one space; the space beside `{`, `}` and `;` goes. Quoted strings, escapes and an escape's closing space stay. Nothing is reordered or merged.
- A comma keeps its space: a custom property holds its value as written (`var(--x, 1px)`), and scripts may read that text with `getPropertyValue()`.

## How was it shown to be the same stylesheet?
- In Chromium both files were parsed as `<style>` and every rule's `cssText` compared: equal once whitespace runs are folded (a line break inside a custom property value becomes a space, the same whitespace token). Computed styles of whole panel screens were too noisy to compare (live clock, content loaded after the page).

Related: docs/memory/core/js-bundles.md
