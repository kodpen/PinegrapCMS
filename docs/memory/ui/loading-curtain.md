---
name: loading-curtain
description: Loading curtain printed by pg_preloader_markup() on every panel screen: structure, PRIVATE_LABEL, logo ids, Visual Editor hold, exit curtain, four ways out, exclusions.
---

## Where and how is the curtain printed?
- `pg_preloader_markup()` (functions.php) prints inside `output_header()` right after `<body>`. Markup, style hook and script come **together**: the curtain stands before any stylesheet or script below loads, since nothing else can be trusted at that point.
- **The curtain is not an image.** Page's own background (`--bs-body-bg`) + a thin top bar + centered brand + three dots. It appears dozens of times an hour; anything with its own personality tires by the third visit. The center block is 300 ms delayed: a fast page shows only the bar, the logo does not flash twice a minute.
- **With `PRIVATE_LABEL` on, no brand is printed**, only the three dots: that mode rests on the software never naming itself; the curtain is no exception.
- **The brand comes from `pg_logo_svg($id_suffix)`**; no second copy (header and curtain share the same eight-kilobyte path list). Both copies are on the same page, so gradient ids take a suffix, added in **a single `preg_replace` pass**: `str_replace` with arrays runs each pair over the whole string in turn, and the second pass re-enters the first's output (`pgLogoGradient-plIcon-pl`).
- Classes are in the *Loading curtain* block at the end of `assets/css/backend.src.css`; never write a screen-specific second curtain.

## When does it drop and when does it show again?
- **No artificial floor.** The criterion is DOM ready. A screen still building after ready DOM (Visual Editor) sets `window.pgPreloaderHold = true` and calls `window.pgPreloaderDone()` when painted.
- **It shows on exit too:** link clicks and form submits call `pgPreloaderShow()`. Filters: `target`, `download`, `data-bs-toggle`, `#`/`javascript:`/`mailto:`, external origin, fragment-only change, modifier keys.
- **There must be four ways out of a stuck curtain:** DOM ready / first interaction after a redirect that never happens / timeout / if the script never ran, the CSS animation. The first `hide()` proves the script ran and **removes** that animation; if the `forwards` fill stayed, on a long-open page the curtain could never open again.

## Where is it excluded?
- `toolbar.php` (the strip above the edited site) and `output_header_secure()` (in-modal picker screens).
