---
name: zone-shared-form
description: add_zone.php and edit_zone.php draw the shipping zone form and read its rates through includes/forms/zone_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/zone_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_zone_form_cards($values, $country_options, $state_options)` (name, fees, countries, states cards), `pg_zone_form_choices($zone_id = 0)` (country and state options, a zone's own links selected) and `pg_zone_form_rate($value)` (a posted rate in minor units). PR #2533.
- A screen keeps its gates, the edit prefill (`pg_format_input_amount()` of the stored rates), the form tag, buttons (edit adds Delete), the edit screen's hidden `id`, the xref rewrite and the INSERT / UPDATE.

## How are the options ordered?
- `pg_zone_form_option_list()` (pure, tests in `tests/zone_form_test.php`): the zone's own countries or states first and selected, then the rest, each in name order. The create screen passes no ids, so all come in name order.
- The edit screen used to run one query per country and per state to find its links; `pg_zone_form_choices()` reads each xref table once.

## What changed for a posted rate?
- Both screens round to minor units (`round()`); the edit screen used to store `rate * 100` unrounded and an empty or non-numeric rate was a TypeError on PHP 8. Now such a rate is 0.
