---
name: image-editor-area-cut
description: The image editor's Cut to a layer (owner, 2026-11-05), why Erase is named Erase inside, and why an area layer takes a press only inside its outline
module: image_editor
---

## What does Cut to a layer do?
- After Select area: the picture inside the outline as a see-through picture layer ("Cut-out", the copy Copy to a layer makes, `areaCutout()`) on top, and an erase layer of the same outline under it, in one undo step (`areaCut()` in `pg_image_editor_area.js`); the selection goes and the piece is selected with the Select tool. The piece moves on its own and leaves a see-through hole; a save writes the hole (a JPEG as a PNG copy, as any see-through erase) and the piece over it.
- The uncut picture stays restorable (owner): one undo, or deleting the erased area ("Silinen alan"), brings it back; the saved versions keep the file as it was.
- In the panel after Copy to a layer; in the small screen's bar (`image-editor-actions.md`) with the scissors icon, Keep only taking the crop icon. Its name in `M.actionList()` is `cutToLayer`: `cut` is the context menu's clipboard Cut of layers.

## Why was it added?
- On dev the owner selected a subject, inverted, erased the background, then pressed the kept subject and deleted what was selected: it was the erase layer, so the background came back and nothing separate was left. He expected the kept part to behave as a separate PNG piece and chose a new command over changing Erase or Keep only.

## Why is Erase called Erase inside?
- Its Turkish label was "Sil", the same as the bar's Delete that shows right after it for the new layer. Now "Erase inside" ("İçini sil"), beside "Adjust inside".

## Where does an area layer take a press?
- Only inside its outline (`M.areaHit()`: the press in the layer's own frame against its rings, even-odd), for erase and adjust layers and area redactions; other shapes keep their box. The picture's own press (`startDrag()` in `pg_image_editor_artboard.js`) uses it too. Before, an inverted area's box covered the whole picture, so a press on the kept subject selected the erase layer.

## How was it checked?
- Tests: `M.areaHit()` on a ring with a hole (`tests/image_editor_area_test.php`); the bar's list with Cut to a layer (`tests/image_editor_actions_test.php`).
- Browser (sandbox, 2026-11-05), a 1600×1200 PNG at 1280×800: cut, move, the hole hit only inside it, undo and redo; a copy saved with the hole see-through and the piece moved; after an inverted Erase inside, a press on the subject selects nothing. Phone 390×844: Cut to a layer from the bar.
