---
name: widget-entity-fields
description: W8, entity values a system widget may show (owner allow-list 2026-10-26): the mechanism, the product and calendar event values built, the group card's description.
---

## How does a widget get an entity's values?
- One allow-list per entity: `pg_sw_entity_fields($entity)` (`includes/fn/widgets_entity_fields.php`), token => (column, kind[, options]). Kinds: text, measure, count, number (zero kept), money (minor units, visitor's money text), rate (NULL: ''), stamp (a date), period and recurrence (translated words), yesno (`'1'` or `''`). Options: `when` (gate columns, all non-zero), `is` (the yes value). Zero and NULL read as `''`, so `show_if empty` holds fallback words ([[widget-fill-shop]]).
- `pg_sw_entity_select($entity, $alias)` adds the columns to the renderer's own query (a column a migration added and the database lacks reads as NULL); `pg_sw_entity_values()` gives the values, merged with `+=` before the renderer's one escape.
- The editor's twin lists (`SW_PRODUCT_ENTITY_TOKENS`, `SW_EVENT_ENTITY_TOKENS`) land as a "More … details" group; an entry marked `'cond'` (yes/no) is offered by "Show only when a value…" only, never as a text binding (`_swTokenCondOnly()`). `tests/widget_entity_fields_test.php` keeps the twins equal.
- Rules and the owner's reasons: [[widget-designability-decisions]]. A token on the list is never removed.

## What is built?
- Product (#1809), in the catalog card, the product detail and the favourites: `__keywords`, `__length`, `__width`, `__height`, `__minimum_quantity`, `__maximum_quantity`, `__preparation_days`, `__extra_shipping_cost`, `__vat_rate`, `__payment_period` and `__number_of_payments` (recurring products only), yes/no `__is_free_shipping`, `__is_gift_card`, `__is_donation`.
- Product group card (catalog listing): `__description` held `''` before; now the group's long description, with `__details`, `__keywords` and its own `__featured` / `__is_new` (`pg_sw_catalog_is_new()`, the products' 30-day rule).
- Calendar event (#1828), in the event view and the calendar view's event loop: `__event_spots_left` (only when places are limited; `0` shows), `__event_updated_date` (typed in the event view), `__event_reservation_price`, `__event_recurrence` (words); yes/no `__event_reservations_open`, `__event_spots_limited`, `__event_is_recurring`. Read from `get_calendar_event()` plus `pg_sw_entity_event_read()` (one query), only when the tree names one (`pg_sw_entity_wanted()`). The month grid's event lists do not carry them.
- Keywords of products and groups, and group details, are extracted for the site translation (`pg_tr_extract_catalog()`).

## What is still to build?
- The other families of the owner's allow-list, one slice each: [[widget-entity-fields-list]].

## What is not done yet?
- The detail page's variant switch (`pg_civ_variants.js`) does not rewrite these values: they show the product the page opened with.
