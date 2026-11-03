---
name: loading-curtain
description: Panel transitions (pg_preloader_markup()): arrival curtain clear for 120 ms, no exit curtain, 150 ms navigation bar held until pagehide, logo only on held screens, skips.
---

## What does the operator see between two panel screens? (#6080, owner decision A)
- **Leaving:** no curtain. The old page stays visible; after 150 ms a thin bar (`#pg-progress.is-on`) and the waiting cursor (`html.pg-navigating`) show, held until the page goes. No 6 s timer and no wheel/key/click exit (#6058): they dropped the bar while the navigation was still running. Only a 30 s ceiling remains, for a navigation that never commits (an unmarked download).
- **Arriving:** the curtain (`#pg-preloader`) is fully clear for 120 ms, then fades in over 100 ms and out over 100 ms; a screen ready by DOM ready shows nothing. The dots follow 300 ms later.
- **The logo** prints as before (`pg_logo_svg('-pl')`, none under `PRIVATE_LABEL`) but shows only on a held screen (`html.pg-preloader-held`). It used to show at once on every exit (#6057).

## Where and how is it printed?
- `pg_preloader_markup()` (`includes/fn/output.php`) prints inside `output_header()` right after `<body>`; markup and script come together because nothing below can be trusted yet. CSS: *Loading curtain* block at the end of `assets/css/backend.src.css` (run `php tools/build_css.php`); never a screen-specific second curtain.
- Gradient ids of the logo copy take a suffix in one `preg_replace` pass (array `str_replace` re-enters its own output: `pgLogoGradient-plIcon-pl`).

## What holds the arrival curtain?
- A screen still building after DOM ready (Visual Editor, `designer_screen.php`) sets `window.pgPreloaderHold = true` in `<head>` and calls `window.pgPreloaderDone()` when painted. The script marks `html.pg-preloader-held` before the first paint: opaque from the first frame, logo shown.
- Ways down: DOM ready, a 9 s safety timer (cleared by the first `hide()`, #6056), and a 10 s CSS failsafe animation for a script that never ran. `hide()` pins the computed opacity before dropping the animations, since an animated opacity outranks `.is-done`, so the fade-out starts where the fade-in was.

## Why is a cancelled click or submit silent? (#6062)
- The click and submit listeners run in the capture phase, before `onsubmit="return confirm(…)"` or an Ajax handler. They only arm the 150 ms timer; when it fires, `e.defaultPrevented` is read again. A `beforeunload` listener keeps the last event; if it asked (`defaultPrevented` or a `returnValue`) and the page is still here, the answer was Stay.

## Which navigations start the bar?
- Same-origin link clicks and form submits, and, through the Navigation API `navigate` event, script navigations (`location.href` row buttons, `pgSettingsGo()`, the command palette, `form.submit()`, #6059). Skipped: `target`/`formtarget` other than `_self` (the submitter's `formtarget` first, #6061), `download`, `data-bs-toggle`, `data-pg-menu-panel`, `data-pg-no-curtain`, links `pgSettingsModal.asked()` claims, `#`/`javascript:`/`mailto:`, other origins, fragment-only changes, modifier keys. A skipped click or submit sets a one-tick flag so the `navigate` event of the same navigation is skipped too.
- **An address that answers with a file** carries `data-pg-no-curtain` (`pg_screen_toolbar()` button `'file' => true`), or the bar spins until its ceiling (#6060: invoice export, profit Excel, short link CSV). Ajax forms keep it too (settings dialog, quick account forms).

## Where is it excluded?
- `toolbar.php` (the strip above the edited site) and `output_header_secure()` (in-modal picker screens).

## Do the loops keep running behind a done curtain?
- No. `.pg-preloader.is-done` pauses the bar and dot animations (`animation-play-state: paused`, #3592, #3676). Reduced motion drops fades and loops but keeps the 120 ms and 300 ms delays (a delay is not motion).
