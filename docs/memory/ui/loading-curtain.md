---
name: loading-curtain
description: Panel transitions (pg_preloader_markup()): arrival curtain after 120 ms and held screens, the leaving bar after 150 ms until pagehide, what never shows it, the download cookie
---

## Where and how is it printed?
- `pg_preloader_markup()` (`includes/fn/output.php`) prints inside `output_header()` right after `<body>`: the curtain `#pg-preloader`, the leaving bar `.pg-leave-bar` and their inline script together, before any stylesheet or script below loads. CSS is the *Loading curtain and leaving bar* block of `assets/css/backend.src.css` (run `php tools/build_css.php`); never a screen's own second curtain.
- Excluded: `toolbar.php` (the strip above the edited site) and `output_header_secure()` (in-modal picker screens).

## What does arrival show?
- The light transitions are an owner decision (#6080, `workflow/decisions-made-product-21.md`). The curtain (page background + thin top bar) is transparent for its first 120 ms and fades in over 100 ms; `hide()` on DOM ready pins the reached opacity and fades out in 100 ms. A screen ready within 120 ms shows nothing.
- A screen still building after DOM ready (Visual Editor, `designer_screen.php`) sets `window.pgPreloaderHold = true` and calls `window.pgPreloaderDone()` when painted; the script marks that curtain `is-hold`, and **only a held curtain draws the mark** (logo, or the dots alone with `PRIVATE_LABEL`), 300 ms late. Everyday transitions never show the logo.
- No list screen holds the curtain any more: the hold for a hidden `table.chart` until DataTables drew it (#6073) went with DataTables (#8253); the driver prints its first rows with the page.
- Ways out: DOM ready, `pgPreloaderDone()`, the 9 s timer (stored in `timer`, cleared by the first `hide()`; it ran unstored before and cut exit curtains short, #6056), and the 10 s CSS failsafe animation in case the script never ran (`hide()` removes it).
- Reduced motion keeps every delay (a delay is not motion) and drops fades and loops (#6057).

## What does leaving show?
- No curtain. 150 ms after a navigation starts `html.pg-is-leaving` shows `.pg-leave-bar` and `cursor: progress`; the old page stays visible and usable. Nothing takes them down while the navigation runs (no timer, no wheel/key/click exit, #6058); the page leaving (`pagehide`) ends them, and a `pageshow` from the back/forward cache resets them.
- Trigger: the Navigation API `navigate` event (every way of leaving: links, forms, `location.href`, `history.go()`, `form.submit()`, #6059), skipping hash changes, same-document entries, `<a download>`, other origins and a `sourceElement` inside `[data-pg-no-curtain]`. Without the Navigation API, `beforeunload` starts it; click/submit listeners only mark `data-pg-no-curtain`, `mailto:`-like and external links to skip.
- Never shown: a link or form opening another window (`target`, a submitter's `formtarget`, #6061: they do not navigate this window); a click or submit whose handler cancelled it (`confirm()` → Cancel, #6062: the 150 ms check reads `defaultPrevented`); a navigation whose `beforeunload` asked for a prompt (read after the dispatch; a confirmed one shows nothing either).
- Downloads (#6060): every answer with `Content-Disposition: attachment` gets a 60 s `pg_download` cookie through `pg_download_marker_hook()` (`pg_header_callback_add()`, registered in `init.php` after `session_start()`); the bar polls `document.cookie` every 400 ms, drops the cookie and stops. Export links need no attribute.

## Brand and ids?
- With `PRIVATE_LABEL` on, no brand is printed, only the dots.
- The brand is `pg_logo_markup('-pl')` (`ui/brand-logo.md`): the mascot's loop and the wordmark, its own gradient id, its size from `--pg-logo-h` on `.pg-preloader-logo`.

## Do loops run behind a done curtain?
- No. `.is-done` pauses the bar and dot loops (#3592, #3676); the leaving bar is `display: none` until wanted.
