---
name: component-overrides-this-place
description: A placed shared component with open fields (CO4, #11663): "This place", the dot and reset, typing in place, read-only elements and "Edit the component", content editors, save checks
module: component_overrides
---

## When is "This place" drawn?
- For a selected `shared_ref` whose component (not a system widget) has an open field: `_sdCoPlaceSect()` puts it before "Shared Component", so it is the panel's own section, first in Content. The part `overrides` fills it (`fillPlace()`); a component with no open field keeps today's panel.

## What does a row do?
- Values live on the placing node, `props._place` (id => value), written by the part (`placeSet()` / `placeReset()`, one `saveState()` step, `render()`); Save sends the page.
- An empty value, or one equal to the component's, resets the row. "Shown" stores only `false`. A link or picture that would run a script is refused (rose field, nothing kept).
- A changed row has the panel's `.sd-chg-reset` dot with "Reset to the component"; the holder stops the click so the panel's own reset never runs for these rows. The section head is filled by the part (`_sdSectHeadExtras()`).

## What does the canvas do?
- `_sdCoCanvasWire()` adds capture listeners on such a place only. A click on an open element selects the place and focuses its row (`_sdCoFocus`). A double click on an open text types in place (`typeIn()`); a double click on another element does nothing.
- A selected place's open elements get `.sd-co-open-el` (`_sdCoOutline()`, run from `_sdPlaceSelectedChrome()`).

## Why is the rest of the component read-only?
- `_sdCoLocked(node)`: an element of a component with an open field, outside "Edit the component". It is checked first in `_sdCanEditNode()` and `_sdMayEdit()`, and `_sdPanelLockReason()` answers `component`. The Options panel is disabled under the line "Part of “name”: …" (`_sdCoLockPanel()`), and `_sdTrackSharedEditing()` does not mark it changed.
- "Edit the component" (`_sdCoEditComponent()`, panel foot, the read-only line, palette `act_edit_component`) sets `_sdCoEditing[sid]` until the selection leaves the component. `_sdCoWrite()` sets it too, so opening a field never locks the element under the hand.
- At content level the lock applies only in a component opened to the operator; a closed one is read-only by its own rule.

## What does a content editor get?
- On a page that is theirs: "This place" alone. In view mode: the read-only box with the rows disabled.
- Server: `_pg_dm_take_place()` in `includes/designer_access.php` takes only `props._place` of a placed component from the submission (cleaned, markers inert); the reference stays as stored.

## What does the save check?
- `pg_designer_gate_tree()` runs `pg_co_clean_tree_places()` for every role: valid ids, a text (at most 20,000 characters), a switch or a picture `{src, alt?}`, at most 200. Which field takes a value is checked when drawn (`pg_co_safe_values()`, `component-overrides-render.md`).

## Tests?
- `tests/component_overrides_place_test.php`.
