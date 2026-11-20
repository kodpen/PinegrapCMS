---
name: place-coordinates-fields
description: Which fields "Find from the address" and "Find missing coordinates" use: the Map of places block's chosen fields first, then the name rule; whose block wins for a form on several pages (#10628)
module: place_map
---

## Which fields do the two buttons use?
- `pg_place_map_fields()` takes the block's choice (`data-pg-map-fields`, read by `pg_place_map_block_choice()` with `pg_place_list_saved_choice()`, as `places.php` reads it). No second parser.
- A chosen latitude, longitude or address wins over a name-rule field when the form still has a field of that exact name, it fits (a coordinate a number field, the address a text box or text area) and it is not office-use-only (`pg_place_list_chosen_ids()`, shared with `places.php`). A chosen address is the address alone.
- Every part not chosen, and a chosen field renamed, deleted or no longer fitting, is found by the name rule (`place-coordinates.md`). No block: the name rule, as before.
- Why (#10628): a collection whose fields are `y_coord`, `x_coord` and `location`, picked in the block, drew markers but got neither button; with `latitude` beside a chosen field the button wrote into `latitude`.

## Whose block wins for a form listed on several pages?
- The page with the lowest page id, not in the bin, whose first form list shows the form and whose map block names at least one field (`pg_place_map_pick_choice()`). A block naming nothing is passed over.
- Page id, not title: a renamed page never moves the choice. The page tree is read as `places.php` reads it (`pg_page_tree_json()`), so a block inside a shared component keeps the name rule.

Tests: `tests/place_map_test.php` (chosen, chosen beside a name-rule field, no choice, stale choice, the pick).
