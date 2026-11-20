---
name: prototype-back-key
description: P in the visual editor opens the Prototype view with the selected element's link picked (_sdProtoBack()); where the key is left alone, what it picks, why P
---

## What does P do?
- P in the Visual Page Editor (on the canvas or the editor around it) opens the Prototype view with the selected element's link picked: its row and arrow marked, its frame selected and brought into view, the target end shown as a click on the mark shows it (`_sdProtoPick()`), and a screen reader hears `{var:1}: the link “{var:2}” is picked` (`{var:1}: “{var:2}” bağlantısı seçildi`).
- An open view stays open and picks the link at once. A view still loading picks once the drawing of the load the key opened is done (`_sdProto.goTo` with that `_sdProto.seq`; a later load or a close drops it).
- Nothing selected, or an element with no link: the view opens with its frame selected (the open page's, or the shared component's the element lives in; a placed shared component selects its own frame).
- The key is in the Keyboard Shortcuts sheet under View & Preview: `Show the selected link in the Prototype view` (`Seçili bağlantıyı prototip görünümünde göster`).

## Which link is picked?
- `PGProtoHotspots.backTo(layout, spot)` finds the row: a text's link by its index among the text's `<a href>` (a link picked inside the text, `_sdLinkIndexOf()` counting as `_sdLinkNth()` does), a menu's item by its place among the node's rows, else the node's first row.
- "Open in the editor" in the view (`_sdProtoOpenRow()`) keeps the row it opened (`_sdProto.back`, `linkSpot()`): with nothing selected, or the same element still selected with no link picked inside it, P goes back to that exact row, so a menu's third item or a text's second link is found again.

## Where is the key left alone?
- `PGProtoHotspots.backKey(e)`: P with no Ctrl, Alt or Meta (Shift and Caps Lock allowed), not held down, not in an IME composition, not taken by an earlier listener; never in a field (input, textarea, select), a text being written in (contentEditable), a dialog, a menu or listbox (letters jump to an item there), a note, the code editor (`.cm-editor`) or the Prototype view itself.
- An e-mail design has no Prototype view (`body.sd-email-design`): `_sdProtoBack()` answers false and the key is not taken.
- Both listeners ask it: the editor's document (`initKeyboard()`) and the canvas frame (`_sdShortcutKeydown()`), right after the N note key and before the view-mode guard (opening the view changes nothing, so a view-only session may use it).

## Why P?
- No editor shortcut used a bare P (2026-11-20): Ctrl+Shift+P is the command palette and Ctrl+Alt+P the preview, both with modifiers. The other bare letters are N (note) and O (outline, shown in the View menu); the Prototype view's own L (target menu) and 0 (Fit) work only inside the view. P for Prototype (designer-panels-rules K9).

## Which tests cover it?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_back_key` (where the key counts), `test_proto_hotspots_back_to_the_link` (rows found, every row found again from its spot), `test_proto_hotspots_view_back_from_the_editor` (the two listeners, the e-mail design, the wait for the draw, the sheet row).

## Which PR?
- DX2, PR #10524 (2026-11-20). Before it the way back from the editor was the Prototype button, which opened the view on the open page's frame with no link picked.
