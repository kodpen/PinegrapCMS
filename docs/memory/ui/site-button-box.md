---
name: site-button-box
description: Editing buttons over a site page (get_page.php) as a top-right corner box in the site page's colours (#3316): place, collapse arrow, narrow default, SEO panel and save/cancel pair under it
---

## Where does the box sit, and why?
- `#software_pinegrap_button_container` (SEO ring, edit-mode toggle, full-screen, collapse arrow) is a row fixed to the top-right corner, 50 px high, as it was before #2879. The owner asked for the corner back in #3316, replacing the right-edge vertical strip of #2849 (`workflow/decisions-made-panel.md`).
- Where a theme keeps its account menu, cart or language switch in that corner, the collapse arrow folds the box away (#2519).
- It is staff-only editing chrome: nothing changes for visitors or site data.

## What is fixed inside it?
- The arrow `#softwareminimizebuttons` is fixed at `right: 0; top: 49px` above the box, rotated 90deg when collapsed and -90deg when open. The save/cancel pair `#software_inline_editing_buttons` sits under the box (`right: 35px; top: 46px`; collapsed `right: 1px; top: 68px`).
- The box has no transform: a transformed element becomes the containing block of its fixed descendants, and the arrow and pair are fixed children.

## When is it open?
- `toggle_control_buttons()`: the `sessionStorage` choice ("show"/"hide") when there is one; without one, collapsed under 768 px (`max-width: 767.98px`) and open otherwise. The default is not stored, so widening the window opens it. The arrow carries `aria-expanded`; the box is `role="toolbar"` (horizontal), labelled "Page editing controls".

## Where does the SEO panel go?
- On the site (seo.php): under the box, `top: 50px; right: 0`, no transform, open at the top and right edges, `max-width: calc(100vw - 8px)` for phones. In the toolbar frame (toolbar.php) the panel is a column under the bar; its rule resets `right: 0`, `transform: none` and the borders.

## Which colours does it take?
- The page's, not the panel theme's: `dress_control_buttons()` (get_page.php) and the panel's `dress()` (seo.php) put `pg_seo_tb_dark` on when `<html>`'s, then `<body>`'s `data-bs-theme` is "dark"; with neither, when the root `color-scheme` holds only `dark`, or `light dark` and the machine prefers dark. A MutationObserver on html and body (`data-bs-theme`, `class`, `style`) and the `prefers-color-scheme` change event re-dress it.
- Symptom fixed (#3316): with a dark panel preference, a dark strip sat on light sites. Both read `localStorage['pinegrap backend color scheme']` before.
- The SEO panel uses the same rule, since it opens from the box and shares `--pg-sp-*`. In the toolbar frame toolbar.php maps both states to panel tokens.
- Every icon reads 3:1 on both grounds (`tests/seo_score_contrast_test.php`); `tests/site_button_strip_test.php` pins the corner, the narrow default and the panel place.
