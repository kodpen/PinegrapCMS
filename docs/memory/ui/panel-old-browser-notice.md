---
name: panel-old-browser-notice
description: The "update your browser" bar (pg_old_browser_notice()): its three CSS.supports tests, where it is printed (panel head, four standalone screens), the session close, why ES5 and fixed colours
module: output
---

## What does it do?
- `pg_old_browser_notice_script($text, $close)` (`includes/fn/output.php`) returns one classic `<script>` for `<head>`. It asks `CSS.supports()` for `light-dark(#000, #fff)`, `selector(:has(*))` and `oklch(from red l c h)`; one false answer, a missing `window.CSS` / `CSS.supports` or a throw counts as old (below Chrome/Edge 123, Firefox 128, Safari 17.5).
- Only then, once the body is there, a small bar `#pg-old-browser-notice` (`role="status"`) is appended to `<body>`: fixed at the bottom centre, at most 40rem wide, with a × button named by `lang('Close')`.
- The button removes the bar and writes `pg-old-browser-notice` = `1` to `sessionStorage`, so the bar stays away until the browser session ends. With storage blocked it closes for that page only.
- Non-blocking: the page keeps working and nothing is fetched. A page inside a frame (`window.self !== window.top`) shows no bar, so a tab shows one.
- `pg_old_browser_notice()` passes the one text, `lang('Your browser is out of date, so parts of this page may not look or work right. Please update your browser.')`.
- Decision: `workflow/decisions-made-panel-3.md`; what old browsers lose: `bootstrap-6-browsers.md`.

## Where is it printed?
- First in `output_control_panel_header_includes()`: every `output_header()` screen, every `output_header_secure()` screen (sign-in, password screens, MFA, device limit, OAuth consent, print views, installer) and `theme_designer.php`.
- The four standalone screens that load Bootstrap 6 without that head (the pages of `panel-css-tokens.md`), right after `<title>`: `workspace_guest.php`, `workspace_approval.php`, the prototype viewer (`pg_proto_shell_html()`) and the prototype link password page (`pg_proto_link_password_html()`).
- Not printed: the customer pages `erp_quote.php` and `cart_quote.php`, and the answer pages of a direct post (`get_erp_edoc_test.php`, `parasut_connect.php`), though they load Bootstrap 6 too. Site output stays on 5.3.8 and gets nothing.

## Why ES5, text nodes and fixed colours?
- The browsers it is for must run it: no arrow functions, `const` / `let`, template strings or modules; every `CSS.supports()` and storage call sits in `try`.
- The texts are JSON with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` and go in through `createTextNode()` / `setAttribute()`, never `innerHTML`; a text that is not UTF-8 prints no script.
- The bar's colours are fixed (`#fff3cd` with `#3d2e00` text): the theme's `--pg-*` / `--bs-*` colours are `light-dark()` values, which such a browser cannot read.

## How is it tested?
- `tests/old_browser_notice_test.php`: the three tests and the guard, the ES5 and no-network scan, the session close, the escaping, the panel head and the four screens. No browser run.
