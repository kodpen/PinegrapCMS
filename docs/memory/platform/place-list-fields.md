---
name: place-list-fields
description: Fields of a "Map of places" answer: the block's pickers in its Options panel, data-pg-map-fields on the saved page, fit and office-use rules, name-rule fallback
module: place_list
---

## Which fields does an answer carry?
- Never a field the request names (architect, message 7423, for every later step too): the fields come from the saved page. The module reads no request value; `tests/place_list_test.php` checks it.
- First the block's own choice (MP3b): a part the block chose takes that field when the list's collection has a field of that exact name, it fits and it is not office-use-only (`pg_place_list_field_roles($fields, $choice)`). Fits: a coordinate a number field; name, address, hours and phone a text box or text area (`pg_place_list_field_fits()`). A chosen address is the address alone, not joined with others.
- Every part not chosen, or chosen but refused, is found by the name rule: the coordinates and address of `platform/place-coordinates.md`, the record's name (`name`, `title`, `branch`, `sube`…), hours (`hours`, `opening_hours`, `calisma_saatleri`…) and phone (`phone`, `tel`, `telefon`…) (`pg_place_list_names()`).
- An office-use-only field is never taken. A latitude or longitude out of range gives no point (`lat`/`lng` null).

## Where is the block's choice kept?
- On the block node itself, as the attribute `data-pg-map-fields` (`pg_place_list_choice_attr()`): a JSON object, part (`name`, `address`, `hours`, `phone`, `lat`, `lng`) => field name. No table, no column, no new endpoint, like the scroller's and the video's attribute sections.
- `places.php` reads it from `pg_page_tree_json($page_id)` (`pg_place_list_saved_choice()`): the first node carrying `data-pg-map`. A block inside a shared component is not in the page's tree and keeps the name rule. Saved by name, not id, as the list widget's order field: a renamed field falls back to the name rule.
- The panel: the block's Options panel, section "Places" (`propsPlaceMapOptions()` in `style_designer.src.js`): one select per part, "Found by name" first, then the fields of the page's first form list that fit (office-use-only never offered). Without a form list on the page it says so; a saved field no longer offered reads "Not in the list: found by name".

Tests: `tests/place_list_test.php` (the saved choice, chosen and refused fields, no request value read, the panel's attribute and parts).
