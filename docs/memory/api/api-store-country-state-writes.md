---
name: api-store-country-state-writes
description: Country and state writes (store:write): the write path shared with the four screens (includes/fn/store_regions.php), the screens' checks, partial updates, code_taken, what a delete takes
module: store_regions
---

## Where is a country or state written?
- `includes/fn/store_regions.php`: `pg_store_country_save()`, `pg_store_country_delete()`, `pg_store_state_save()`, `pg_store_state_delete()`, `pg_store_state_unlink()`. `add_country.php`, `edit_country.php`, `add_state.php`, `edit_state.php` and the API handlers call them. They return 0/false on a failed query; the screens answer `output_error('Query failed.')`, the API 500. The callers check the values first (`includes/forms/country_form.php`, `state_form.php`).
- A country saved as default takes `default_selected` off every other country.
- A country delete takes its states (their zone places and verified shipping addresses through `pg_store_state_unlink()`), then its own zone places, then the row. A state delete the same for one state. Orders keep the codes they were written with.

## What does the API take?
- Routes (`store.countries.*`, `store.states.*`): GET one (`orders:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`store:write`); handlers in `includes/api/resources/store_regions.php`. A country answers `StoreCountry` (as `GET /store/countries`), a state `StoreCountryState` (id, country_id, country_code, code, name).
- Country fields: name, code (both ≤ 50, varchar(50), trimmed), zip_code_required, transit_adjustment_days (0 or more), default. State: name, code, and the country as `country_id` or `country_code` (the first country with the code); required on create.
- An update lays the sent fields over the stored row (`api_store_country_values()`, `api_store_state_values()`, pure). The screens' checks decide: blank name or code 422, a code another country (another state of the country) has is 409 `code_taken`; a kept code is never refused, as on the screens.
- Each handler checks (`_prepare()`), stops for X-Dry-Run, then writes (`_commit()`): `check_api_schema.php` looks for the stop in the handler itself. No events (the plan names none for countries and states).

## Tested?
- `tests/api_store_regions_test.php`: routes, scopes and field limits, the partial update, the order check-stop-write, the screens calling the shared functions with no SQL of their own, the delete clean-up. Not run against a database.
