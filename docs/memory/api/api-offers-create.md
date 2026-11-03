---
name: api-offers-create
description: POST /offers and POST /offers/{id}/duplicate through the offer editor's validate and save: body shape, refusals before the editor, key code 409, offer.* events
---

## What do the two routes write?
- A new offer, through the editor's own `_pg_offer_validate()` and `_pg_offer_save()` (`edit_offer_f.php`), so the screen's rules hold (`includes/api/resources/offers_create.php`). `offers:write`, `dry_run`, 201 `Offer` as GET /offers/{id}.
- `/duplicate` is the screen's Duplicate saved at once: `_pg_offer_new_state($id)` copies the source, the sent `code` (required), `description`, `enabled`, dates lay over it. 404 for a missing source.

## How is the body read?
- `api_offer_payload($params, $base, $today)` (pure) turns the GET shape into the editor payload: snake_case types to the stored names, `amount` or `percentage` to `unit`/`value`, `tiers` `{min_subtotal, percentage}` to `{min, percent}`.
- It refuses what the editor would bend silently: a fraction or text where a whole number goes ((int) would read 2.5 as 2), an unknown `mode` (reset to no_orders), `amount` on discount_shipping (unit switched to percent), both units, a key the type does not take. A row error says `Condition 2: …` / `Result 1: …` with field `conditions` / `actions`.
- A date sent makes the period `range`: the editor's open period cannot start later than today, and a range that ends on `PG_OFFER_OPEN_END_DATE` is how it stores an open end starting later.
- `api_offer_missing_refs()` checks that the products, groups and shipping methods of conditions and shipping results exist; the editor only checks result products and groups (its pickers list real rows).
- The editor's errors come back through `api_offer_editor_error()` (first error, editor key → body field). A key code with the same code → 409 `code_used_by_key_code`. Offers may share a code, as in the editor.

## Who is named in the log?
- `_pg_offer_save()` takes `$username` and `$activity` like `_pg_offer_delete()`: the API passes the owner's username and its "through the API by the application" line; the screen passes nothing and the session names the operator.

## Which events are announced?
- `offer.created` / `offer.updated` from `_pg_offer_save()`, `offer.updated` from the editor's on/off switch, the API update and the Workspace change, `offer.deleted` from `_pg_offer_delete()`. Payload `{id, code, enabled}`; a deletion `{id, code}`.

## Tests
- `tests/api_offers_create_test.php`: routes, payload reading and refusals, the editor checks on the result, check-before-stop order, events.
