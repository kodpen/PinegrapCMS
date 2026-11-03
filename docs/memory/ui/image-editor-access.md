---
name: image-editor-access
description: The image editor's access part: tool letters, the keyboard help sheet, shortcut names on the controls, Tab kept inside, reduced motion, the two-finger twist
module: image_editor
---

## Where does it live?
- `assets/js/pg_image_editor_access.js`, bundled last; its pure maths in the model's access section (`toolForKey()`, `toolKey()`, `trapFocus()`, `twistAngle()`, `keyMap()`), tested in `tests/image_editor_access_test.php`; labels in `pg_image_editor_strings_access()`; rules under its line in `pg_image_editor.src.css`.
- Core hooks it uses: `footItems` (the keyboard button at the footer's end, new with it), `keys` and `escapes` (put first with `unshift`), `views`, and three core calls when the methods exist: `trapTab(e)` on Tab, `startTwist(ids)` on a second finger, `moveTwist()` while a twist runs.

## Which letters pick a tool?
- V Select, C Crop, A Adjust (Arrow on the mini bar), D Draw, T Text, R Redact, U Rectangle, S Sticker, F Frame, P Vector (Pen on the mini bar), B Canvas, I Image, E Export, L Line, O Ellipse. Only tools of the call; no letter with Ctrl, Alt or Shift; the tool already on is left as it is (a letter does not toggle the phone sheet).
- A tool button's title reads "Text (T)" and carries `aria-keyshortcuts`; undo, redo and save carry theirs too; the stage is named ("Press ? for the keyboard shortcuts").

## What does the help sheet show?
- `?` or the keyboard button in the footer: groups from `keyMap(tools)`: the call's tool letters, editing (Enter only with Crop), layer keys only with a tool that makes layers, view, touch (the twist only with layers). Mod shows as Ctrl, or ⌘ on a Mac. Escape, its close button or a press beside it closes it and the focus goes back where it was; while open, other keys do nothing.
- The footer is hidden on a phone, so the sheet is a keyboard thing there.

## How is Tab kept inside?
- `showModal()` makes the page inert, but Tab still leaves for the browser's own bar. `trapTab()` wraps from the last control to the first (Shift+Tab the other way) in the help sheet, the unsaved-changes question, else the whole editor; from the stage or between the ends the browser steps on its own. The New image dialog keeps its own loop.

## What does reduced motion change?
- Under `prefers-reduced-motion: reduce` every transition, animation and smooth scroll inside `.pgie-dialog` is off. The editor has no animation of its own today; the rule covers the ones tools add later.

## How does the two-finger twist work?
- On touch, a finger moving one unlocked layer (Select's `move` drag, also from Draw, Text, Redact and Vector) plus a second finger turns that layer with the line between the fingers, instead of zooming the view: `twistAngle()` adds the turn, keeps -180..180 and holds on multiples of 45° within 4°. Lifting either finger ends it; the move and the turn are one undo step. Two fingers elsewhere still pan and pinch.
