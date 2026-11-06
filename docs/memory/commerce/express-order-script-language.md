---
name: express-order-script-language
description: The express order page's fetch() calls to api.php carry pg_lang on a translated page, built by one helper.
module: widgets_express_order
---

## Which address do the express order scripts post to?
- `pg_express_order_api_url()` (`includes/fn/widgets_express_order.php`):
  `OUTPUT_PATH` (or the `$base_path` the widget script is given) +
  software directory + `/api.php`, with `?pg_lang=<code>` while the page is
  drawn in another site language (`pg_tr_active()`).
- Used by both installment fetchers (`eo_get_installments`) and the widget
  script (live tax, shipping methods). No other place builds an api.php
  address in that file; `tests/express_order_language_test.php` checks it.

## Why does the address carry the language?
- These scripts use `fetch()`, which the jQuery prefilter of
  `frontend.src.js` (adds `pg_lang` to software-directory requests) never
  sees. Without it a translated checkout showed shipping method names and
  the "cannot ship here" message in the source language. Same pattern as the
  booking widget (`pg_booking_widget_endpoint()`).
