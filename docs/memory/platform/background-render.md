---
name: background-render
description: pg_background_render(): the one in-process page render of the passes that draw many pages (SEO structure, accessibility, later the search index); what it catches, restores and lends
module: background_render
---

## What is the background renderer?
- `pg_background_render($render, $get)` in `includes/fn/background_render.php`, loaded by the passes, not by `functions.php`. `$render` is a callable returning the page's HTML; `$get` the address values the render reads (`page`, a widget's `r`/`id`/`form_id`).
- `pg_seo_render_page()` (`seo_structure.php`) keeps the type check and `pg_seo_render_context()` and renders through it; the structure pass and the accessibility pass (`pg_a11y_check_page()`) both go through `pg_seo_render_page()`. The search index moves to it in SA4 of `plans/search-and-audits.md`.

## What does it catch and restore?
- `catch (Throwable)`: an `Exception` from `output_error()` and an `Error` (a call on null, an undefined constant on PHP 8) both come back as `error`; an `Error` message carries its class, file and line. Before, only `Exception` was caught: one page with an `Error` ended the pass, and the structure queue and the accessibility cursor never got past it.
- Output buffers above the caller's level are discarded, so a render that threw half way does not swallow the next page's output or the process's own.
- Lent and put back in `finally`: the `$_GET` keys (removed if they were absent), the session token (an empty placeholder unset afterwards, never left blank: `validate_token_field()` rejects an empty token), the `pg_seo_rendering()` flag (its previous value, not off). The catalog item resolver is reset before each render.
- An `exit()` inside the render still ends the process; that is not catchable.

Test: `tests/background_render_test.php`.
