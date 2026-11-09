---
name: image-editor-menu
description: The image editor's context menu: where it opens (right click, long press, Shift+F10, menu key), which items M.menuList() gives, the keys and focus, the long-press traps it fixes
module: image_editor
---

## What is it, and why?
- Part `menu` (`pg_image_editor_menu.js`, bundled last; labels `pg_image_editor_strings_menu()`; rules under its line at the end of `pg_image_editor.src.css`). Owner 2026-11-04 (E32, #6840, `workflow/decisions-made-product-27.md`).
- Opens on a right click on the stage, a rail item or a scene's card; a finger held 550 ms without moving 10 px; Shift+F10 or the menu key (on the focused rail item or card, else the stage under the selection). Text fields, the panel and the top bar keep the browser's own menu (`menuPlace()` answers null there).

## Which items show?
- `M.menuList(s)` (the model's MENU section), `s` from `menuState()`. Layers: Cut, Copy, Paste, then `M.actionList()`'s Duplicate and Delete, Order and Align submenus, Lock/Unlock and Hide/Show (the inverse when every selected layer has it), then the type's own: Combine shapes (two areas or more, the five pathfinder ops), Convert to outlines, Expand warp, Break apart (one imported SVG). The picture and an area: `actionList()` as the selection bar shows it, without Options. Empty canvas: Paste, Select all, Import (a submenu with From the site when the API is there; nothing without the Import button), Canvas size (Canvas tool offered), Fit to screen. A group rail item: its modes (menuitemradio, the shown one ticked); a one-tool item has no menu. A scene: Duplicate, Delete, Move earlier, Move later, each off at its limit.
- Paste uses the editor's own clipboard (`this.clip`, what Ctrl+C and Cut put there); a picture on the system clipboard still comes in by Ctrl+V only.
- The panel's Arrange, Align and Distribute call `arrangeLayers()`, `alignLayers()`, `spreadLayers()` (layers part), as the menu does.

## What does a right click select?
- On the stage the layer under it is selected as Select's press would (`Editor.pointers.select.down` with a fake press, then `endDrag()`); an empty spot clears the selection; a tool that does not select layers gives way to Select when a layer is hit. Locked layers are not hit, as with Select. Select area keeps its area; Crop has no menu.
- A long press on the stage only with Select, the tools that share its pointer (Sticker) and Select area: elsewhere a held finger draws or types. The tool's press already selected; the timer ends its drag in place.
- A right click on a closed scene's card opens that scene first: the scene commands work on the open one.

## Which traps does it handle?
- The menu opens under the finger: the release's click would press the item there (or the rail item held). The click within 400 ms of the long press's own pointerup is swallowed (`ctxReleaseAt`).
- The browser's own contextmenu after the menu key or a long press is prevented and ignored; the key's dedupe ends on the next pointer press, else a quick right click after Shift+F10 was dropped.
- A scene command redraws the strip and the core's `render()` gives the lost focus to the stage; the open card takes it back after the redraw.

## How was it checked?
- `tests/image_editor_menu_test.php`: the lists for layers (types, locked, hidden), the picture, an area, the empty canvas with and without Import, a rail group, scenes; the bundle, the labels, a command for every item.
- Headless Chromium on a blank canvas (1280×800 and iPhone 13 touch): items, Order by keys (ArrowRight into the submenu, Enter), Lock by Shift+F10, Copy/Paste and undo, a rail group's mode, a scene moved later with the focus kept on its card, a long press on a layer (selected, not moved) and on a rail item (menu, item not pressed).
