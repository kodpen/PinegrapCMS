---
name: print-views-light-paper
description: The order print view and packing slip force the light theme (they stand for the paper), carry their own title and the store icon; the e-mail campaign popups' title and icon
---

## Why did the order print view print dark?
- `print_order.php` prints the panel head (`output_header_secure()`): its head script copies the stored panel theme onto `<html data-bs-theme>` and `backend.src.js` `setTheme()` applies it again on `DOMContentLoaded`. Nothing forces light in print: neither `backend.src.css` nor the Bootstrap 6 dist has a print rule for it, and the view's own `@layer custom` style set no body colour.
- In the dark theme the page was the dark body with light text; with background graphics off Chromium only darkens the text to mid grey, so it printed faint, and with them on the page printed black. The `#333` rules nearly vanished.

## How is it forced light now?
- Both print views (`print_order.php`, `print_packing_slip.php`) print an inline script right after the header that sets `data-bs-theme="light"` on `<html>` at once and again in a `DOMContentLoaded` listener. `backend.src.js` is a synchronous head script, so its listener is registered first and this one runs after it.
- The script never writes `localStorage`: the panel keeps the stored choice on every other screen.
- `print_packing_slip.php` also keeps its own white body and black text.

## Window title and icon?
- Both print views pass `'icon' => 'store'`: without `icon` the favicon links point at icon files that do not exist.
- `print_packing_slip.php` passes `'title' => lang('Packing Slip')`. It used to echo a second `<title>` inside `<body>`; `document.title` reads the first one, so the window read "Pinegrap <version> | host".
- The two `send_email_campaign.php` screens (progress and finished) were titled `lang('Modify Pages')` with the page icon; they use `lang('Send E-mail Campaign')` and the `campaign` icon.

## Which labels of the order print view are translated?
- The fax label, the gift card table (Code, Amount, Recipient Email, From Name, Message, Delivery Date) as `lang('X') . ':'`, and the ship-to address type through `pg_order_view_address_type()` (`includes/fn/order_view.php`), as the order view does.

## How is it checked?
- `tests/print_views_theme_test.php` pins the script, the titles, the icons and the labels (#2659). Not checked by a unit test: the printed result itself.

Related: docs/memory/ui/dark-theme-fixed-fills.md, docs/memory/ui/panel-css-tokens.md
