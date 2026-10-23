---
name: designer-preview-page-scripts
description: Platform scripts a designer page gets from its markup (theme switcher, tooltips/popovers, scroller buttons) on the page and in the Preview; one Bootstrap bundle; Preview link guard and dark view.
---

## Which platform scripts does a designer page get, and from where?
- A marker in the final HTML (after shared components and widgets are drawn in) brings a small file, added in `get_page_content.php`, never in an e-mail:
  - `data-pg-theme-switcher` -> `assets/js/pg_theme_switcher.js` + an inline resolve in `<head>` (`pg_theme_switcher_inject()`).
  - `data-bs-toggle="tooltip"|"popover"` (`pg_bs_tips_pattern()`) -> `assets/js/pg_bs_tips.js`, deferred (`pg_bs_tips_inject()`), only when `style_layout` is `visual_designer` (classic pages start their own).
  - `data-pg-scroller` (`pg_scroller_pattern()`) -> `assets/js/pg_scroller.js`, deferred (`pg_scroller_inject()`; designer-scroller-controls).
- The Preview asks the same table: `_SD_PAGE_SCRIPTS` in `style_designer.js` (marker expression -> file, absolute URL from the editor's base, with the editor script's own `?v=` stamp so an upgrade is not served from cache). A new row needs its server step; `tests/designer_page_scripts_test.php` compares the two sides.
- The Preview writes its scripts before the last `</body>` with `_sdInsertBeforeBodyEnd()`, found by a regex on the markup itself. An index from `html.toLowerCase()` drifted one place per `İ` (lower-cased it is two code units): Bootstrap was cut out and `</body` showed as text on Turkish pages.

## Why does the published page need pg_bs_tips.js?
- Bootstrap starts neither tooltips nor popovers. Before it, Components > Popover never opened on the page and the panel's Tooltip option showed only the native `title`; the canvas (`_initCanvasTooltips()`) and the Preview had their own starts.
- It creates the instance on the first `mouseover`/`focusin`/`click` in the capture phase on `document`; the same event then reaches the listeners Bootstrap just bound, so the first hover or click works. Later elements need no rescan; an instance the design's own script made is kept (`getInstance`), not doubled. No `window.bootstrap` -> nothing.

## Why did the Preview's dropdowns and accordions not toggle?
- `_sdPreviewDocHtml()` wrote the framework's bundle after the assets panel's `bootstrap-js` row: two copies, each toggling once per click (opens and closes). Now the fallback bundle goes in only when no enabled `bootstrap-js` row is on the page (`_sdAssetsInPlay()`), the rule of `$pg_has_bs_js_sentinel` on the page.

## How does the Preview stay inside its window?
- The blob document has `<base href="<site>/">`, so `href="#x"` loaded `<site>/#x`. `_sdPreviewHarness()` (inline in `<head>`) listens on `window` (after Bootstrap's handlers, which cancel their own): a `#id` link scrolls to its target (`scrollIntoView`; the URL stays the blob), a bare `#` does nothing, middle click on either does nothing, a form with no action does not submit. Links to real pages still go there.
- Not `location.hash`: the live preview restores scroll only while the URL is the blob it sent.

## How do theme switchers and the editor's dark view meet in the Preview?
- The editor's Dark view (`_sdPreviewDarkView()`) writes `data-bs-theme="dark"` on `<html>`, and on `<body>` only when the page has no switcher (there it would outrank the switcher). `<body>` is kept for the rest as on the canvas: a Themes light block under `:root, [data-bs-theme="light"]` also matches a dark `<html>` and beats Bootstrap's dark block; with a switcher the Preview shows that as the page does.
- With a switcher on the page the harness defines the switcher engine first (`pgThemeInit`, `pgSetTheme`, `pgGetTheme`, `pgResolvedTheme`); `pg_theme_switcher.js` keeps an engine that is already there and only binds. The engine starts from the editor's view (light or dark) and writes nothing to `localStorage`: a choice made in the Preview is not the visitor's stored `pg-theme`. A live-preview reload starts from the editor's view again.
