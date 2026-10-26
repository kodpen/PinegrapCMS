---
name: widget-entity-fields
description: W8, entity values a system widget may show (owner allow-list 2026-10-26): the mechanism, the product family built, the group card's description fix, the list still to build per entity.
---

## How does a widget get an entity's values?
- One allow-list per entity: `pg_sw_entity_fields($entity)` (`includes/fn/widgets_entity_fields.php`), token => (column, kind[, arg]). Kinds: text, measure, count, money (minor units, visitor's money text), rate (NULL: ''), period (translated words), yesno (`'1'` or `''`). Zero and NULL read as `''`, so `show_if empty` holds fallback words ([[widget-fill-shop]]).
- `pg_sw_entity_select($entity, $alias)` adds the columns to the renderer's own query (a column a migration added and the database lacks reads as NULL); `pg_sw_entity_values()` gives the values, merged with `+=` before the renderer's one escape.
- The editor's twin list (`SW_PRODUCT_ENTITY_TOKENS`) lands as the "More product details" group; an entry marked `'cond'` (yes/no) is offered by "Show only when a value…" only, never as a text binding (`_swTokenCondOnly()`). `tests/widget_entity_fields_test.php` keeps the twins equal.
- Rules and the owner's reasons: [[widget-designability-decisions]]. A token on the list is never removed.

## What is built?
- Product, in the catalog card, the product detail and the favourites: `__keywords`, `__length`, `__width`, `__height`, `__minimum_quantity`, `__maximum_quantity`, `__preparation_days`, `__extra_shipping_cost`, `__vat_rate`, `__payment_period` and `__number_of_payments` (recurring products only), yes/no `__is_free_shipping`, `__is_gift_card`, `__is_donation`.
- Product group card (catalog listing): `__description` held `''` before; now the group's long description, with `__details`, `__keywords` and its own `__featured` / `__is_new` (`pg_sw_catalog_is_new()`, the products' 30-day rule).
- Keywords of products and groups, and group details, are extracted for the site translation (`pg_tr_extract_catalog()`).

## What is still to build (one slice per entity family)?
- Calendar event: places left, last updated, reservation price, recurrence kind (words); yes/no reservations open, limited places, recurring.
- Form record: the quiz score, on the item view only (never public lists: other people's scores).
- Order (order view and receipt e-mail, the owner only): PO number, billing custom fields 1-2 with their settings labels, fax, gift card discount, currency, payment date, cancel date and reason, refund status (words), refund amount and date; yes/no tax exempt. The order view SELECT in `includes/fn/widgets.php` lacks them; `get_order_receipt.php` gives the classic layouts.
- Order line: quantity shipped, line VAT, subscription period, payments and first payment date, event name and date; yes/no added or discounted by an offer; the line's product values above.
- Ship-to and address book: address name, salutation, first and last name, address lines 1-2, city, state, zip, country, address type (words), shipped and delivered dates.
- Account (my account, profile, login region, account e-mail; the visitor's own): reward points, membership expiry, company, title, nickname, website, mobile, work and home phone, business city and country, time zone, salutation, tax number, tax office, affiliate code (`get_my_account.php` gives the classic layout these).
- Off the list: the product's notes, receipt BCC, commission, code, SEO analysis, marketplace and accounting ids; the group's price rules, code, SEO analysis, creator; the event's staff, next page, unpublish day, internal recurrence; the submitter's IP, editing staff and contact card. Compare-at price has its own plan. Reviews: nothing new.

## What is not done yet?
- The detail page's variant switch (`pg_civ_variants.js`) does not rewrite these values: they show the product the page opened with.
