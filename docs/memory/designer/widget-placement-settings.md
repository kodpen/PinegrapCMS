---
name: widget-placement-settings
description: W21 per-placement content of a system widget (owner 2026-10-27): what a placement may narrow, props.placement, the pg-placement marker, the narrowing rules, the panel section, what is left.
module: widgets_placement
---

## What may a placement change?
- Only content, never look or texts ([[widget-designability-decisions-2]]). Keys per kind, `pg_sw_placement_keys()` and its JS twin `_swPlacementKeys()`: form lists max_results and order; catalog listing product group, max_results and order; calendar view calendars and upcoming count; menu region the menu; ad region the region.
- Not yet: the records filter of form lists (W21b).

## Where is it stored?
- On the placement, the `shared_ref` node: `props.placement` (`pg_designer_tree_objects()` keeps it an object). Empty values are dropped (`_swPlacementSet()`); a placement with none has no key, and its page body is byte-identical.
- No schema change. Content-level editors cannot change it: the save gate copies a stored `shared_ref` (`designer_access.php`), and the panel section is hidden for them.

## How does it reach the render?
- `_render_tree_node()` writes `<!--pg-placement:BASE64URL-->` just before `<!--pg-shared-ref:ID-->` (`pg_sw_placement_marker()`). `_expand_shared_refs()` keeps it before a system widget's marker and drops it before any other component; `_expand_system_widgets()` reads it, and `$render` narrows `$cfg` with `pg_sw_placement_narrow()` before the kind's renderer.
- Restricted submissions defuse every `<!--pg-` comment typed into text, this one too.

## What does "narrow" mean?
- A count never goes above the widget's (max_results 0 = no cap, so any; upcoming events default 10). A product group must be the widget's or under it (`pg_sw_placement_group_within()`); calendars must be among the widget's (none chosen = every calendar). A widening value is ignored and the widget's own stands. Menu, ad region and order are free choices.

## What does the editor show?
- Section "This placement" in the widget panel (`_swPlacementSection()`), every control "As the widget" when empty. The canvas keeps the widget's own records; Preview and the site follow the placement.
- Tests: `tests/widget_placement_test.php` (twin via Node).
