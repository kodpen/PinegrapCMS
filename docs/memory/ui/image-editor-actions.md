---
name: image-editor-actions
description: The Design Canvas's selection bar, over the object on the desktop and at the stage's foot on a small screen: its commands (M.actionList()), labels, place and when it hides
module: image_editor
---

## What is it, and why?
- Part `actions` (`pg_image_editor_actions.js`, bundled before the context menu `ui/image-editor-menu.md`; labels `pg_image_editor_strings_actions()`; rules under its line in `pg_image_editor.src.css`): the selection's main commands as a bar on the stage.
- Small screen (the panel a sheet: under 768 px wide or 500 px tall): at the stage's foot. Owner (2026-11-04, a phone test): a selected sticker could not be deleted there.
- Desktop: over the selected object, so a command does not take the tool's panel away (#10003). Owner (2026-11-16, poll 103, message 7038): "Evet, böyle çıksın" (`workflow/decisions-made-product-67.md`).

## Which commands show on the desktop?
- `M.actionList(state)` with `state.place` `object` (`objectActions()`): one layer's own command (a text: Edit text; a video in Animate: Split), Motion in Animate (never a redaction), Duplicate, Delete, Lock, More. Several layers: Duplicate, Delete, Lock, More. Every layer locked: Unlock and More.
- The picture and an area keep their commands in the panel there: no bar. More opens the context menu's list at the bar (`openMenu()`): order, align, flip are there, not in the bar.
- Motion opens the Motion popover (`image-editor-motion.md`); a track row's button opens the same one (`image-editor-tracks.md`).

## Which commands show on a small screen?
- Layers: Remove pin while Free deformation has a pin chosen, Options (sheet closed), Split for one clip in Animate (`image-editor-phone-rows.md`), Duplicate, Delete (off when all are locked). The picture: Options, Lock, Delete. An area: Options, Erase inside, Keep only, Copy to a layer, Cut to a layer, Adjust inside, Redact, Invert, Deselect.
- Options opens the sheet (arrange, align, colours, feather). The list is kept as approved (`workflow/decisions-made-product-28.md`).

## Which labels does it use?
- The owning parts' keys: `duplicate`, `delete`, `lock`, `unlock`, the area's. Split over the object is the clip part's `clipSplit` (Split clip), on the phone the phone part's approved `actSplit` (Split): a command's fifth entry in `COMMANDS` is its key over the object.
- The bar's own: `actBar` (aria-label), `actOptions`, `actEditText`, `actMotion`, `actMore`, `actMoreOptions` (the popover's foot).

## Where does it sit, and when does it hide?
- Desktop: `M.actBarPlace(box, bar, stage)` from the drawn selection boxes: centred above, 8 px clear of the box and the turning handle; below it (`pgie-actbelow`) where there is no room; inside the stage, above the play bar and the tracks (`stageInset`). Redrawn with the selection box (zoom, pan, move).
- Small screen: centred above what covers the stage's bottom (an open sheet, the play bar) and left of a sheet over its right (`--pgie-act-bottom`, `--pgie-act-right`); at the top with the mini bar on a phone held upright.
- Hidden while dragging, typing a text, placing a polygon, cropping; over the object also while playing (not a pick's preview) or recording, and with no box drawn.
- Only the bar takes presses: pointerdown, pointerup, dblclick and wheel stop on it. Arrows, Home and End move along it, Escape goes back to the stage.

## How was it checked?
- `tests/image_editor_actions_test.php`: the lists per place, kind and lock; `actBarPlace()` at each edge; the stylesheet's two rules; the labels' owners (#10003, #10041).
- Browser, 390×844 (2026-11-04) and 1440 and 1024 light and dark (#10003): the bar, a text, a video in Animate with the popover open, a layer at the top edge, a locked one.
