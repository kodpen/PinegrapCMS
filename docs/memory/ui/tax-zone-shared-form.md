---
name: tax-zone-shared-form
description: The tax zone screens share includes/forms/tax_zone_form.php: cards, options, links; a blank name is refused and redrawn; states under the country's shown name (PR #4883)
---

## What is shared?
- `includes/forms/tax_zone_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_tax_zone_form_cards($values, $country_options, $state_options, $errors = array())` (options, countries, states cards; `$values` null on create, the name box always `required` with a placeholder), `pg_tax_zone_form_choices($tax_zone_id = 0, $selected = null)` (country and state options, a zone's own links or the posted ids selected), `pg_tax_zone_form_post_values()`, `pg_tax_zone_form_name_error()` and `pg_tax_zone_form_save_links($tax_zone_id, $_POST)` (the posted country and state links inserted). PR #2587.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, buttons (edit adds Delete), the edit screen's hidden `id`, the INSERT / UPDATE, the edit screen's link delete before a save or a delete, the log line and the redirect.

## Is a blank name refused?
- Yes (#4713, PR #4883), as on the shipping zone form (`zone-shared-form.md`): `pg_tax_zone_form_name_error()` refuses an empty or spaces-only name; the screens read the post through `pg_tax_zone_form_post_values()` (name trimmed, rate as typed) and draw the form again with what was typed, the posted countries and states selected and "Enter a name." under the name; nothing is written. Edit's delete is never held back. The trimmed name is what is stored. Before, a nameless zone was written and listed as an empty row of All Tax Zones.
- A blank or unreadable rate is still refused afterwards with `output_error()` (`parse_tax_rate()`); the box is `type="number" required`, so a browser rarely sends one.

## How are the options ordered and named?
- Countries: `pg_geo_country_label()` and `pg_geo_sort_by_label()` (`commerce/country-names-display.md`).
- States: `pg_tax_zone_form_state_items()` (pure) labels each "Country: State" with the country's shown name and orders by it, state name order kept within a country, like the shipping zone form. Before (#4713, PR #4883) the label used the stored English name and SQL ordered by it ("Turkey: Adana").
- `pg_tax_zone_form_option_list()` (pure) keeps the given order with the zone's own selected in place; unlike the shipping zone form, selected options are not moved first. Tests: `tests/tax_zone_form_test.php`.

## What does a posted link list accept?
- `pg_tax_zone_form_link_ids()` (pure): the non-empty scalar values as text; a posted value that is not a list gives none instead of a foreach warning.
