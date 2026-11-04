---
name: zone-shared-form
description: add_zone.php and edit_zone.php draw the shipping zone form and read its rates through includes/forms/zone_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/zone_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_zone_form_cards($values, $country_options, $state_options, $errors, $summary)` (form column and summary column), `pg_zone_form_layout($cards, $buttons)` (the row and the sticky save bar), `pg_zone_form_choices($zone_id = 0)` (country and state options, a zone's own links selected, and how many are chosen) and `pg_zone_form_rate($value)` (a posted rate in minor units). PR #2533.
- A screen keeps its gates, the edit prefill (`pg_format_input_amount()` of the stored rates), the form tag, the buttons it hands to the layout (edit adds Delete), the edit screen's hidden `id`, the xref rewrite and the INSERT / UPDATE.

## How are the options ordered?
- `pg_zone_form_option_list()` (pure, tests in `tests/zone_form_test.php`): the zone's own countries or states first and selected, then the rest, each in the order given: countries by their label, states by their country's label then name (`commerce/country-names-display.md`).
- The edit screen used to run one query per country and per state to find its links; `pg_zone_form_choices()` reads each xref table once.

## Is the name required?
- Yes (#4413, PR #4456): `pg_zone_form_name_error()` refuses a blank name; the screens read the post through `pg_zone_form_post_values()` and draw the form again with what was typed (rates as typed, `pg_zone_form_choices($id, $values)` selects the posted countries and states) and the message under the name; edit's delete is never held back (its button is `formnovalidate`, so an emptied name does not stop it in the browser). Before, a nameless zone was written.

## What changed for a posted rate?
- Both screens round to minor units (`round()`); the edit screen used to store `rate * 100` unrounded and an empty or non-numeric rate was a TypeError on PHP 8.
- A rate that is not blank and not an amount of 0 or more (a negative, text) is refused like a blank name (#5566): `pg_zone_form_rate_errors($values)` gives field => message, printed under the box; the boxes carry `data-inputmask-allowMinus="false"`. Before, `pg_store_zone_save()` clamped a negative to 0 without a word.
- A rate above `PG_ZONE_FORM_RATE_MAX` (4294967295 minor units, the INT UNSIGNED top) is refused the same way ("{var:1} can be at most {var:2}."); MySQL without strict mode clamped it silently. The boxes have no `maxlength` (12 cut a grouped amount of 10,000,000 or more) and carry `data-inputmask-max`.

## How is the screen laid out?
- The wide edit layout (`edit-screen-wide-layout.md`, PR #5203): form column with the Zone Name card, the Fees card (rates two per row) and the Countries and States cards stacked full width as on the tax zone form (paired from `xl`, the longer States header wrapped and pushed its picker a line lower); a sticky Summary column reading the chosen countries and states (kept current by a jQuery `change` listener, which a combobox pick fires) and, on edit, the products and shipping methods using the zone. Edit reads `pg_zone_form_delete_counts()` once into `$usage` for the summary and the Delete confirmation. Layout pinned by `test_zone_form_wide_layout`.
