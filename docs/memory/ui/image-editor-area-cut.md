---
name: image-editor-area-cut
description: The image editor's Cut to a layer (owner, 2026-11-05), why Erase is named Erase inside, and why the hole an erase leaves is the picture's own, not a piece
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

## Is the hole a piece of its own?
- No (owner, 2026-11-07): a cut leaves two pieces, the cut-out and the picture with its hole. An erase or adjust layer is the picture's own (`M.pictureArea()`): a press on the stage never takes it (its type's `hit` is false), so a press or a marquee in the hole takes the picture when it is free or nothing; the Select tool shows its box without handles and neither drags, nudges nor lines it up (`fixed` in `Editor.layerTypes`, `fixed()` in `pg_image_editor_layers.js`); a copy keeps its place. It is chosen in the Layers list ("Silinen alan") or right after it is made, and Delete there brings the picture back. A moved or scaled picture takes it along (`M.placePicture()`), a deleted one drops it (`M.dropPicture()`).
- Before, Erase inside selected the new layer with handles: a drag in the hole moved the hole off and showed the uncut pixels. Area redactions keep their own press inside their outline (`M.areaHit()`); the picture's press (`startDrag()` in `pg_image_editor_artboard.js`) skips erase and adjust areas.

## How was it checked?
- Tests: `M.areaHit()` on a ring with a hole (`tests/image_editor_area_test.php`); the bar's list with Cut to a layer (`tests/image_editor_actions_test.php`); erased and adjusted areas moved, scaled and dropped with the picture (`tests/image_editor_picture_test.php`).
- Browser (sandbox, 2026-11-05), a 1600×1200 PNG at 1280×800: cut, move, undo and redo; a copy saved with the hole see-through and the piece moved. 2026-11-07, a photo at 1280×800: after Erase inside a drag in the hole and the arrows move nothing, Delete brings the picture back; after a cut the piece moves, a press, a drag or a marquee in the hole takes nothing; a free picture dragged, or pressed in its hole, carries the hole along. Phone 390×844: a tap or a drag in the hole takes nothing.
