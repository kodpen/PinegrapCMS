---
name: site-page-toolbar-theme
description: The editor toolbar over a site page (toolbar.php in #software_toolbar): why its root stays light and its body takes the theme under Bootstrap 6, the unreadable dark toolbar it fixed (PR #2448)
---

## How does the toolbar get its colours?
- `get_page.php` draws the iframe `#software_toolbar` over the site page; `toolbar.php` inside it is panel chrome on Bootstrap 6 (`pg_page_shell()` with `'toolbar' => true`).
- Bootstrap 6 writes its colours as `light-dark()` and resolves them from `color-scheme`, which it sets on `[data-bs-theme]` outside any layer. The panel puts `data-bs-theme` on `<html>`.

## Why is the root light and the body dark?
- A browser paints a frame opaque when the scheme of the element whose background the canvas takes differs from the page around it. The bar must show the site page through, so `:root` stays `color-scheme: light` and carries the bar's shade (`linear-gradient(180deg, #0000008c, transparent)`); `body.toolbar` gets `background: none`, since a body background is lifted to the canvas with the body's scheme.
- `:root[data-bs-theme="dark"] body { color-scheme: dark; }` makes everything inside the body resolve the theme's colours, and follows the account menu's theme buttons.
- The block is in `toolbar.php`, unlayered: a rule in `@layer custom` loses to Bootstrap's unlayered `[data-bs-theme=dark]{color-scheme:dark}`.

## What was wrong (PR #2448)?
- `backend.src.js` set `style="color-scheme:light"` on the toolbar's `<html>` for the transparent frame. Under Bootstrap 5 dark colours were plain values, so that was harmless; under Bootstrap 6 every `light-dark()` took the light value: dark navbar, sidebar and page bar with dark texts and icons. The line is gone.
- The SEO panel's `--pg-sp-*` palette in `toolbar.php` read v5 variables Bootstrap 6 dropped (`--bs-body-color`, `--bs-secondary-color`, `--bs-tertiary-color`, `--bs-tertiary-bg`, `--bs-secondary-bg`); it reads `--pg-*` tokens now.
- `tests/site_toolbar_theme_test.php` pins all three.

## Why does the iframe element say light too?
- `get_page.php` gives `#software_toolbar` `color-scheme: light` inline. A browser paints a frame on an opaque canvas when the frame element's scheme differs from its document's root; a site in dark mode (`<html data-bs-theme="dark">`, Bootstrap 5.3's `color-scheme: dark`) passed `dark` to the element while the root is `light`, so the open bar hid the page under a grey-white layer (#2517). The root is always light, so the two now match on every site.

## How is the open or closed state remembered?
- `frontend.src.js` (`send_toolbar_state()`) posts `update_toolbar_properties` the moment the bar opens or closes (fetch with `keepalive`, jQuery as the fallback); `get_page.php` opens the bar when the session flag is on. It was sent only on `beforeunload` with a synchronous request, which Chromium refuses during page dismissal, so the bar always opened closed on the next page (#2518). `frontend.min.js` is the hand-made twin: rebuilt with terser like the panel bundles.

## How do the score figures and the button box stay readable?
- The score's hex (`pg_seo_score_hex()`) is a fill for the ring and dots only; yellow was 1.5:1 as text. The figure carries its band (`score['band']`: success, warning, orange, danger, none): `.pg_seo_band_*` reads `--pg-sp-<band>` (seo.php: own light and dark inks; toolbar.php: the panel's `-fg-emphasis` tokens), the page bar's `.pg-pbar-band-*` reads the panel tokens. Points and weights use `--pg-sp-muted`, not the half-transparent `-faint`. #2519.
- The button box over a site page (`#software_pinegrap_button_container`, 5.3.8 page, own CSS) and the site SEO panel share the `--pg-sp-*` set and take `pg_seo_tb_dark` from the page's own `data-bs-theme` / `color-scheme`, never the panel theme (`site-button-box.md`); the pink icons have a lighter dark variant. `tests/seo_score_contrast_test.php` checks the band ratios and the 3:1 icons in both sets.
