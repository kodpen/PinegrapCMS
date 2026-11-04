---
name: container-shared-form
description: add_container.php and edit_container.php draw and check the container form through includes/forms/container_form.php; wide layout, limits, unit conversion, required boxes, the list's figures
---

## What is shared?
- `includes/forms/container_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_container_form_cards($liveform, $status_rows = '')` (the form and status columns), `pg_container_form_status_row($label, $value)`, `pg_container_form_layout($cards, $buttons)` (the row with the sticky save bar), `pg_container_form_script()` (unit switch and Summary, printed after `</form>`) and `pg_container_form_validate($liveform, $container_id = 0)`. PR #2514.
- A screen keeps its gates, prefill (edit: record values, weight and cost blank when 0), the form tag, the edit screen's hidden `id`, its buttons (edit adds Delete), the error redirect and the INSERT / UPDATE.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11): the form column holds Options (name, cost) and Package (the Dimensions fieldset and Weight); the sticky status column holds Status (the Enabled switch) and a Summary of the dimensions, weight and cost in the shown units, kept current by the script; edit adds Last Modified. Before, one full-width card held every field.

## Which fields are posted?
- Only `length`, `width`, `height` (inches) and `weight` (pounds). The cm and kg fields have no stored value; the script fills them from the inch and pound boxes on load (so an edited container shows its cm and kg) and converts as the user types, rounded: cm to 2 places, inches, pounds and kg to 4, the columns' scale. Before #5833 the metric boxes opened blank and showed float noise such as 2.7940000000000005.
- The save writes the four figures through `pg_container_figure()` (decimal comma read, four places, blank 0).

## What does the edit call change?
- It passes the posted `id`: the name check (`db_q`, `name = ? AND id != ?`) then leaves the container itself out. The create call passes 0, which no row has.

## How are the dimension boxes named?
- `Dimensions` is the `<legend class="form-label fs-md">` of a `<fieldset>` holding the Inches/Cm radios and both rows. Each of the six boxes has its own visible `<label for>` (`Length`, `Width`, `Height`) and is a `form-adorn form-adorn-end` with an `aria-hidden` `in`/`cm`, three per `row g-3` (`col-12 sm:col-4`). The old English `L`/`W`/`H` add-ons and the placeholders that named the boxes are gone. Ids and the toggled wrappers `#length_inches` / `#length_cm` are kept. Test: `tests/commerce_form_group_names_test.php`.

## Which figures are refused?
- `pg_container_number_check($value, $max, $positive)` gives '', 'number' or 'max'. Length, width and height must be above zero at four places; weight and cost may be blank or zero, never negative or non-numeric. Every figure fits its column: dimensions and weight `decimal(12,4) unsigned` (`pg_container_dimension_max()`, 99999999.9999), cost `int(10) unsigned` cents (`pg_container_cost_max()`, 42949672.95). A name over 100 characters is refused before the uniqueness check. Under the non-strict sql_mode these were stored as 0 or the column limit with "saved" (#5832). The boxes carry the same `min`/`max`. Test: `tests/container_form_test.php`.

## How are the dimensions marked required?
- The six dimension labels print the `pg-required-marker` star themselves; the script moves `required` to the shown unit group (inch or cm) on load and on every switch, since a required box hidden with `d-none` blocks the save silently (#5834). Weight takes a full row so Cost no longer sits beside the unit radios.

## How does All Containers print a row?
- Active: a `bi-check-circle` with `aria-hidden` and a `visually-hidden` Yes; an inactive row says No. The old `material-icons` text was read aloud (#5835).
- Length, width, height and weight through `pg_container_format_measure()` (panel separators, trailing zeros dropped) with `lang('in')` / `lang('lbs')`.
