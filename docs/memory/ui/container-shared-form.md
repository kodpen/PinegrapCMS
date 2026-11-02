---
name: container-shared-form
description: add_container.php and edit_container.php draw and check the container form through includes/forms/container_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/container_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_container_form_card($liveform)` (the options card: name, enabled, dimensions, weight, cost), `pg_container_form_script()` (unit switch, printed after `</form>`) and `pg_container_form_validate($liveform, $container_id = 0)`. PR #2514.
- A screen keeps its gates, prefill (edit: record values, weight and cost blank when 0), the form tag, the edit screen's hidden `id`, its buttons (edit adds Delete), the error redirect and the INSERT / UPDATE.

## Which fields are posted?
- Only `length`, `width`, `height` (inches) and `weight` (pounds). The cm and kg fields have no stored value; the script converts them into the posted fields as the user types.

## What does the edit call change?
- It passes the posted `id`: the name check (`db_q`, `name = ? AND id != ?`) then leaves the container itself out. The create call passes 0, which no row has.

## How are the dimension boxes named?
- `Dimensions` is the `<legend class="form-label fs-md">` of a `<fieldset>` holding the Inches/Cm radios and both rows. Each of the six boxes has its own visible `<label for>` (`Length`, `Width`, `Height`) and is a `form-adorn form-adorn-end` with an `aria-hidden` `in`/`cm`, three per `row g-3` (`col-12 sm:col-4`). The old English `L`/`W`/`H` add-ons and the placeholders that named the boxes are gone. Ids and the toggled wrappers `#length_inches` / `#length_cm` are kept. Test: `tests/commerce_form_group_names_test.php`.

## Which dimensions are refused?
- Length, width and height must be numbers greater than zero (`pg_container_dimension_positive()`, `tests/container_form_test.php`). The columns are unsigned decimals under a non-strict sql_mode, so before #5133 a negative or non-numeric value passed the old `== 0` test on PHP 8 and was stored as 0. Weight and cost are not checked here.
