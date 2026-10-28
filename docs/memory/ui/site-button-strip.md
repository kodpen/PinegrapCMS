---
name: site-button-strip
description: Editing buttons over a site page (get_page.php) as a right-edge vertical strip (#2849): placement, centring without a transform, narrow default, SEO panel beside it
---

## Where does the strip sit, and why?
- `#software_pinegrap_button_container` (SEO ring, edit-mode toggle, full-screen, collapse arrow) is a vertical strip fixed to the right edge, vertically centred. The old 136 × 50 px box in the top-right corner covered the theme's account menu, cart or language switch until collapsed (#2519); the bottom corner belongs to back-to-top and chat buttons. Decision #2849 (B).
- It is staff-only editing chrome: nothing changes for visitors or site data.

## How is it centred?
- `top: 0; bottom: 0; margin: auto 0; height: fit-content`, never a transform: a transformed element becomes the containing block of its fixed descendants, and the inline save/cancel pair (`#software_inline_editing_buttons`) is a fixed child of the strip.
- The pair sits beside the strip (`right: 52px`, 24 px when collapsed), centred with its own transform. The separator `.grid_toggle_button_vh` is a horizontal rule in the column.

## When is it open?
- `toggle_control_buttons()`: the `sessionStorage` choice ("show"/"hide") when there is one; without one, collapsed under 768 px (`max-width: 767.98px`) and open otherwise. The default is not stored, so widening the window opens it. The arrow is the strip's last button and all that stays when collapsed; it carries `aria-expanded`. The strip is `role="toolbar"`, labelled "Page editing controls".

## Where does the SEO panel go?
- On the site (seo.php): beside the strip, `right: 48px`, centred on it with `translateY(-50%)`, full border and radius. In the toolbar frame (toolbar.php) the same panel is a column under the bar, so its rule resets `right: 0`, `transform: none` and the borders.
- Colours and dark set of the strip and panel: `site-page-toolbar-theme.md`.
- `tests/site_button_strip_test.php` pins the column, the centring without transform, the narrow default and the panel places.
