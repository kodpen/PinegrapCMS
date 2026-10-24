---
name: widget-edit-chip-coverage
description: Which system widget records carry the front-end edit pencil (products, groups, form records, calendar events), the calendar rule, and which renders and widget kinds draw none.
---

## Which renderers call pg_sw_add_edit_chip()?
- `catalog_listing`: every product card and product group card (`edit_product.php`, `edit_product_group.php`, tone `ecommerce`, `pg_sw_can_edit_products()`).
- `catalog_item_view`: the product, or the group on a variant group's page.
- `form_list_view`: every record row; `form_item_view`: the record when the design places no `^^__edit_url^^` button (`edit_submitted_form.php`, tone `forms`, `pg_sw_can_edit_submitted_forms()`).
- `calendar_view`: every event row of the loop (month, week and upcoming views); `calendar_event_view`: the event, on the widget's first element (`edit_calendar_event.php`, tone `calendar`, `_pg_sw_calendar_event_edit_chip()`).
- Comments on a record or classic page: `get_page_content.php` (`edit_comment.php`, tone `page`).
- None draws in a search-index, SEO, canvas/Preview (`pg_seo_rendering(true)`) or e-mail render (`pg_sw_render_context()['email']`: campaigns, form and order e-mails went out with staff pencils): `pg_sw_edit_chips_on()`, rule `pg_sw_edit_chips_rule()` (`tests/widget_edit_chip_gate_test.php`).

## Who sees the pencil on a calendar event?
- The users `edit_calendar_event.php` lets in: role 0-2 always; role 3 only with the calendars right, one of the event's calendars among their own (`users_calendars_xref`) and, since a page shows only published events, the right to publish events.
- `pg_sw_calendar_event_edit_rule()` is that rule as a pure function (`tests/widget_calendar_edit_chip_test.php`); `pg_sw_can_edit_calendar_event()` reads the user's rights and calendars once per request.
- The address carries the occurrence's `recurrence_number` (none for the first), as the legacy event page's edit button does: with reservations kept per occurrence the screen shows that occurrence's spots. `pg_sw_backend_edit_url()` takes such extra parameters as its third argument.

## Why does the month grid get no pencil?
- Its entries are one-line links in a seventh of the table, cut with `text-truncate`; a 1.9rem chip would cover the title. Each links to the event page, which carries the pencil, and the starter's loop under the grid lists the same events with one each.

## Which widget kinds draw no pencil, and why?
- Customer-owned data: `shopping_cart`, `express_order`, `order_view` (lines are the customer's order, staff open it in the panel's order screen), `my_account` (the member's own submissions; their detail page has the pencil), `account_invoices`, `address_book`, `account_profile`, `email_preferences`, `account_security`, `change_password`, `set_password`, `account_email`, `logout`.
- No record behind them: `login_form`, `login_region`, `forgot_password`, `registration`, `membership`, `cart_link`, `error_page`, `custom_form` (the form is edited in the visual editor itself).
- `language_switcher`: its rows are the site's languages, a setting, not content.
- `catalog_item_view`'s cross-sell cards (`_civ_render_cross_sell()`): each opens its product page, which carries the pencil.
- `search_results`: a row is a link to a page, product or group and carries no record id (`_pg_sw_search_collect()` rows; advanced search keeps only an address in `search_items`); the page it opens carries the pencil, so the list repeats none.

## Where does a new kind's pencil go?
- In its renderer, on each record's rendered HTML after `pg_sw_uniquify_row_ids()`, behind a `pg_sw_can_edit_*()` check that copies the edit screen's own gate; helpers next to the others in `includes/fn/widgets.php`. Add the kind to the list above.
- A tone outside `page`, `ecommerce`, `forms`, `calendar` falls back to `page`.

Related: `designer-widget-ghosts-and-edit-chips.md` (the chip's markup and who sees it), `ui/calendar-event-edit-screen.md`.
