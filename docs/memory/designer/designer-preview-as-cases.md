---
name: designer-preview-as-cases
description: The Preview's case model (PW1): the cases each widget kind has, the visitors a page offers, the choice read back from the browser, what a case sets per widget (record, visitor, flags)
module: designer_preview_as
---

## What is the case model?
- Pure functions in `includes/fn/designer_preview_as.php` (PW1); no query, no session. The server (PW2) turns a setup into a render; the bar (PW3) lists the choices. Tests: `tests/designer_preview_as_test.php`.
- Cases, in list order: `typical`, `empty`, `long`, `sold_out`, `error` (`pg_preview_as_cases()`). Visitors: `signed_out`, `signed_in`, `member`, `first_visit`, `returning` (`pg_preview_as_visitors()`). No choice = `typical` + `signed_out` + record 0: the Preview as before.

## Which cases does a kind have?
- `pg_preview_as_kind_cases($kind)`, kind from `pg_sw_widget_kind()`:
- Lists (`form_list_view`, `submitted_forms_list`, `form_list`, `blog_list`, `calendar_view`, `photo_gallery`, `folder_view`, `search_results`, `comments`, `comments_recent`): typical, empty, long. `catalog_listing` adds sold_out.
- Record widgets (`form_item_view`, `calendar_event_view`): typical, long; `catalog_item_view` adds sold_out.
- Carts (`shopping_cart`, `order_preview`, `express_order`): typical, empty, long.
- Forms (`custom_form`, `login_form`, `registration`, `forgot_password`, `affiliate_sign_up_form`, `billing_information`, `shipping_address_and_arrival`, `order_form`): typical, error.
- Any other kind: typical alone. A new kind with a case of its own is added to `_pg_preview_as_kind_groups()`.

## Which record does a widget draw?
- `pg_preview_as_record_source()`: `form_item_view` → form submissions, `catalog_item_view` → products, `calendar_event_view` → events; other kinds draw no chosen record (`record` is null).
- `pick`: `default` (today's: newest submission, first product), `chosen` (the record id), `longest` (case long), `sold_out` (case sold out). Long and sold out override a chosen record.

## How is the browser's choice read back?
- `pg_preview_as_choice($input, $kinds, $page)`: a record only on a page with a record widget and only a whole positive number; a visitor only if `pg_preview_as_page_visitors()` offers it (member needs `access_rules`, first/returning need `audience`); a case only if a widget on the page has it (`pg_preview_as_page_cases()`). Anything else falls back to the no-choice value.

## What does a case set per widget?
- `pg_preview_as_setup($kind, $choice)` → `{case, record, visitor, flags}`. A page case the widget lacks draws that widget as typical. Flags: `empty`, `long`, `sold_out`, `form_error`.
