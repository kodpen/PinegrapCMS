---
name: container-shared-form
description: add_container.php and edit_container.php draw and check the container form through includes/forms/container_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/container_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_container_form_card($liveform)` (the options card: name, enabled, dimensions, weight, cost), `pg_container_form_script()` (unit switch, printed after `</form>`) and `pg_container_form_validate($liveform, $container_id = 0)`. PR #<n>.
- A screen keeps its gates, prefill (edit: record values, weight and cost blank when 0), the form tag, the edit screen's hidden `id`, its buttons (edit adds Delete), the error redirect and the INSERT / UPDATE.

## Which fields are posted?
- Only `length`, `width`, `height` (inches) and `weight` (pounds). The cm and kg fields have no stored value; the script converts them into the posted fields as the user types.

## What does the edit call change?
- It passes the posted `id`: the name check (`db_q`, `name = ? AND id != ?`) then leaves the container itself out. The create call passes 0, which no row has.
