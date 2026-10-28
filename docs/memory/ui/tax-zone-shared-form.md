---
name: tax-zone-shared-form
description: add_tax_zone.php and edit_tax_zone.php draw the tax zone form and write its country and state links through includes/forms/tax_zone_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/tax_zone_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_tax_zone_form_cards($values, $country_options, $state_options)` (options, countries, states cards; `$values` null on create, where the name shows a placeholder and no value), `pg_tax_zone_form_choices($tax_zone_id = 0)` (country and state options, a zone's own links selected) and `pg_tax_zone_form_save_links($tax_zone_id, $_POST)` (the posted country and state links inserted). PR #2587.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, buttons (edit adds Delete), the edit screen's hidden `id`, the INSERT / UPDATE, the edit screen's link delete before a save or a delete, the log line and the redirect.

## How are the options ordered?
- `pg_tax_zone_form_option_list()` (pure, tests in `tests/tax_zone_form_test.php`): the given order (countries by name, states by country then name), the zone's own selected in place. Unlike the shipping zone form (`zone-shared-form.md`), selected options are not moved first: the markup stayed as it was.
- The edit screen used to run one query per country and per state to find its links; `pg_tax_zone_form_choices()` reads each xref table once.

## What does a posted link list accept?
- `pg_tax_zone_form_link_ids()` (pure): the non-empty scalar values as text, as the old `if ($id)` loops did; a posted value that is not a list gives none instead of a foreach warning.
