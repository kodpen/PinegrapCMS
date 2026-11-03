---
name: api-store-arrival-dates
description: /store/arrival-dates over the external API: shape, partial update, shipping cut-offs sent or kept, the plain-text description, refusals
---

## Which routes are there?
- `/store/arrival-dates`: list (one page, checkout order: `sort_order`, `arrival_date`, id) and GET one (`orders:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`store:write`); ids `store.arrival_dates.*`, handlers in `includes/api/resources/store_lookups.php`, writes through `pg_store_arrival_date_save()` / `_cutoffs_save()` / `_delete()` (`api-store-lookups.md`). No events.
- A row answers `enabled`, `default`, `custom` as booleans, dates as `YYYY-MM-DD` (null for the zero date) and `shipping_cutoffs` as `{shipping_method_id, date_and_time}` with `date_and_time` as `YYYY-MM-DDTHH:MM`, the form it is sent in.

## How is a body checked?
- Dates must be `YYYY-MM-DD` (`api_store_lookup_check_dates()`): the form helpers also read the site's own order, which a client never sends.
- `api_store_arrival_date_post()` (pure) lays the sent fields over the stored row in the screen's post shape; `pg_arrival_date_form_errors()` then decides, as on the screen (blank name, missing or impossible dates, end before start, sort order outside a smallint: 422).
- `description` refuses `<` and `>` (422, `api_store_arrival_date_plain_text()`): checkout and the layouts print it raw as HTML, which the screen allows only to e-commerce users.
- Saving one as `default` takes the switch off every other one (`pg_store_arrival_date_save()`).

## What happens to the shipping cut-offs?
- Sent: `shipping_cutoffs` replaces them all. An item the screen would drop (`pg_arrival_date_form_cutoff()`: a method used twice, a date and time outside start 00:00:00 to end 23:59:59) or a method that does not exist is 422, never dropped in silence.
- Not sent: a create has none; an update keeps the stored ones, but checks them against the new dates (`api_store_arrival_date_stored_cutoff_items()`), so moving the dates past one is 422 asking for `shipping_cutoffs`. The screen re-posts them on every save and would drop that row.

## Tested?
- `tests/api_store_lookups_test.php`: routes, field limits, the post shape, the answer shape, stored cut-offs read back, the plain-text rule, check-stop-write order. Not run against a database.
