---
name: datetime-picker-site-steps
description: Site steps S1-S4 of plans/datetime-picker.md (after the panel stop): on-demand loader, classic renderers, checkout, designer widgets and palette; claim as [plan:datetime-picker] S<n>
---

## Which site steps are there?
- They start after P11 (the panel stop). Sites keep Bootstrap 5.3.8 and jQuery UI loaded; only the picker changes, and each field posts what it posts today (site date, time in TIME_FORMAT, ISO on native inputs).

- S1 (M) done (#3113). On-demand loader: `pg_datetime.js` + vcp with vcp's own light/dark CSS, injected only when the markup carries a marker (the `pg_fx_inject` pattern in `get_page_content.php`); options from `get_date_picker_format()` / `get_date_time_picker_format()`. Files: `get_page_content.php`, a new `includes/fn/` module (named in its memory file's `module:`), `pg_datetime.js`. Tests: inject on/off. Ends the "never hook site renderers" rule in `ui/pg-field-wrappers.md`.

- S2 (L) done (#3132). Custom forms, form views, comments: `content.php` front-end rows (1309-1412, 1437-1445), `get_custom_form_screen_content.php`, `get_form_item_view.php`, `get_form_list_view.php`, comment publish in `get_page_content.php`, `widgets_comments.php`, `frontend.src.js` + hand-made `frontend.min.js`. Time-only mode for time rows; hard-coded hints follow the clock. Keep id-based binding (custom layouts in site databases). Shrink `tests/bs6_timepicker_addon_test.php`. After S1; not beside P4.

- S3a (M) done (#3168). Checkout and order pages: classic boxes hooked (recurring start, gift card delivery, custom arrival date), custom layouts bound by id (product, shipping and billing rows, `time_fields` of `prepare_custom_form()` too), `init_custom_arrival_date` without a picker; no file loads the add-on (the test's site list is empty). `product_form.php` and `generate_layout_content.php` needed nothing: their hints were already on `lang()` and the clock.

- S3b (S) The order form widget's start date (`widgets_order_form.php` `_pg_of_schedule_html()`: `data-pg-date`, no script, no `get_date_picker_format()` prefix), the add-on files deleted (`assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.*`, about 1,950 vendored lines) and listed in `clean_up.php`, `tests/bs6_timepicker_addon_test.php` checking they are gone. After #3164 (the widget) and #3141 (`clean_up.php`) have merged. **Stop** after it.

- S4 (L) Designer widgets and palette, ISO kept: `widgets_cart.php`, `widgets_catalog.php`, `widgets_express_order.php`, `widgets_shipping_address.php`, `style_designer.js` (CF_PALETTE ~2755, starter arrival day ~62321, preview, Type-select and `f:date`/`f:datetime` inputs), magazine/news templates and their tests, `layout_convert.php` 592-593, starter layouts' time rows (`data/backups/english_default/layouts/248.php` and 256, 476, 497). Fix the express-order submit rewrite of `input[type=date]` to site text (`widgets_express_order.php` ~3892) in or before S4. Designer `datetime-local` into `date and time` (`custom_form.php` 351) needs the T-to-space step. After S3b; not beside P11. May be cut into S4a/S4b.

## What risks stay open?
- vcp is in the panel twice (Bootstrap's months-only copy): pin both versions and test together on a Bootstrap update.
- vcp "today" is the browser date; stores are the site wall clock (`ui/panel-times-user-time-zone.md`).
- `26.10.2026` typed with dots is refused by `validate_date` today and still will be.
