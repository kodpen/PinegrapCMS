---
name: image-editor-access
description: The image editor's access part: tool letters, the keyboard help sheet with its per-tool groups, shortcut names on the controls, Tab kept inside, reduced motion
module: image_editor
---

## Where does it live?
- `assets/js/pg_image_editor_access.js`, bundled last; its pure maths in the model's access section (`toolForKey()`, `toolKey()`, `trapFocus()`, `twistAngle()`, `keyMap()`), tested in `tests/image_editor_access_test.php`; labels in `pg_image_editor_strings_access()`; rules under its line in `pg_image_editor.src.css`.
- Core hooks it uses: `footItems` (the keyboard button at the footer's end, new with it), `keys` and `escapes` (put first with `unshift`), `views`, and three core calls when the methods exist: `trapTab(e)` on Tab, `startTwist(ids)` on a second finger, `moveTwist()` while a twist runs.

## Which letters pick a tool?
- V Select, C Crop, A Adjust (Arrow on the mini bar), D Draw, T Text, R Redact, U Rectangle, S Sticker, F Frame, P Vector (Pen on the mini bar), B Canvas, I Image, E Export, L Line, O Ellipse, M Animate; Q Select area, X Scissors, G Shape builder, W Warp, N Free deformation (E29). K stays Animate's play key. Only tools of the call; no letter with Ctrl, Alt or Shift; the tool already on is left as it is (a letter does not toggle the phone sheet).
- A tool button's title reads "Text (T)" and carries `aria-keyshortcuts`; a rail item holding several tools (Photo, Vector) lists its modes' letters ("Photo (C, A, R, Q, F)", `aria-keyshortcuts="C A R Q F"`), and each button of its mode switch carries its own letter (`modeSwitch()`). Undo, redo and save carry theirs too; the stage is named ("Press ? for the keyboard shortcuts").

## What does the help sheet show?
- `?` or the keyboard button in the footer: groups from `keyMap(tools)`: the call's tool letters, editing (Enter only with Crop), layer keys only with a tool that makes layers, a group per tool with keys of its own (`M.KEY_MODES`, titled with the tool's name: Select area's Shift/Alt and drag, double click and the first-corner tap closing a polygon; Free deformation's click, Alt+click and Delete on pins; Shape builder's Alt and drag), the timeline with the Animate tool (`image-editor-animation.md`), view, touch (the twist only with layers). A tool that gains keys adds its rows to `KEY_MODES`. Mod shows as Ctrl, or ⌘ on a Mac. Escape, its close button or a press beside it closes it and the focus goes back where it was; while open, other keys do nothing.
- The footer is hidden on a phone, so the sheet is a keyboard thing there.

## How is Tab kept inside?
- `showModal()` makes the page inert, but Tab still leaves for the browser's own bar. `trapTab()` wraps from the last control to the first (Shift+Tab the other way) in the help sheet, the unsaved-changes question, else the whole editor; from the stage or between the ends the browser steps on its own. The New image dialog keeps its own loop.

## What does reduced motion change?
- Under `prefers-reduced-motion: reduce` every transition, animation and smooth scroll inside `.pgie-dialog` is off. The Animate tool's preview never plays by itself, only on Play or K, so the rule leaves it alone.
- The two-finger twist and the touch target sizes: `image-editor-touch.md`.
