---
name: office-docx-picture-bar
description: The document bar's Picture button (pg_office_view.js, DA7): the file window's picker kind office_picture, a channel's picture window with Upload, the checks before drawing, Undo and the save entry
module: office_docx_picture, picker
---

## Where is the Picture button and what does it open?
- "Picture" (`bi-image`, after Table) shows only when the host gives `pickPicture` or `pictures` in the view's options. The picture goes after the paragraph open or picked when it was pressed (after its list, for a list item), as a table does.
- File window (`view_folders.php`): `pickPicture` opens the panel picker `pgPicker.open({kind: 'office_picture'})` (`ui/picker-overview.md`): png, jpg, jpeg, gif, by id, pictures, Upload into the open folder.
- A channel's preview (`workspace.js`, `previewFile()`): `pictures` lists the channel's PNG, JPEG and GIF files (`ws_channel_files`, first page) as tiles in a window under the button; Upload there reads a file from the device into the document only, nothing is posted to the channel. The `ws_file_office` answer carries `channel_id` when editing.

## What is checked before it is drawn?
- The bytes, as the writer does (`office-docx-pictures.md`): PNG, JPEG or GIF by the first bytes (`pictureType()`), one at most 2 MB, those put in before a save at most 8 MB together (`pictureRefusal()`, `DOC_PICTURE_LIMITS`, tested equal to `PG_OFFICE_MAX_IMAGE` / `PG_OFFICE_MAX_IMAGES`). The writer still refuses a document whose pictures together pass 8 MB on save.
- Not in a new table, row or column ("Save first"), not after a deleted paragraph or one in `edit.shut` (the table's refusals, `pictureSpot()`).

## How does the page hold it?
- A `div.office-added-picture` (data-after: the file paragraph it follows; data-t a name) holding the picture as a data address at its own size up to the text width (`.office-img`), aligned as the paragraph it follows. Bytes and description (the file name without its extension) wait in `pictures` by name.
- One change of Undo; captured, restored and reset with the added paragraphs and tables. It is not typed in; Undo takes it out.

## What does a save send?
- `{after, picture: {data, name}}` among the added paragraphs and tables, in reading order (`office-docx-pictures.md`); the count counts each.

## What was checked?
- `tests/office_docx_picture_bar_test.php`: `pictureType()` and `pictureRefusal()` in Node, the limits against the writer's, the picker kind, the strings and the hosts' wiring.

Related: docs/memory/platform/office-docx-pictures.md, docs/memory/platform/office-docx-table-bar.md
