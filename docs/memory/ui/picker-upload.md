---
name: picker-upload
description: The picker dialog's Upload (picker.php request upload), its Recently used list kept in the browser, and several picks (multiple): rules, keys, where each is checked
module: picker
---

## How does a file get uploaded from the picker?
- Upload in the bar, Upload File in an empty folder, or files dropped on the pane. They go into the open folder, one POST per file in sequence: `picker.php?kind=&request=upload&folder=` with `token` and `file`. PK3 of `plans/one-picker.md`, PR #8630.
- The server stores it with `pg_file_store_upload()`, the path Upload File uses (image profile, file size limit, name rules), and answers `{item}` as a list item, or `{error}`.
- The first stored file becomes the pick; with `multiple` every stored file is added. A progress panel names the folder and each file: percent, uploaded, or Not uploaded with the reason.

## Which folder takes an upload?
- `pg_picker_upload_verdict()` (pure): the kind has `upload` and source files; the folder is above 0, allowed (`pg_picker_folder_allowed()`, never the Recycle Bin) and the user has the edit right (`pg_folder_edit_access()`).
- A public kind (`public_image`, `site_icon`) only into a public folder (`pg_public_folder_ids()`). Why: its list leaves private files out, so the file would vanish the moment it was stored.
- The list answer carries `upload: {ok, reason}`; a refused folder shows Upload disabled with the reason as its title, and the drop overlay says the reason. Recently used is no folder: Upload is disabled there.

## What is checked before a file is stored?
- `picker.php`: POST only (405); a body PHP threw away is named first (413, `pg_upload_body_discarded_message()`), as the token is lost with it; then the token (`pg_picker_token_valid()`, 403). All after the kind's access verdict, as every request.
- `pg_picker_upload_answer()`: the folder's verdict 403, a missing file 400, a `$_FILES` error by `pg_upload_error_message()`, a type the kind does not take 415 (`pg_picker_upload_type_ok()`). Every refusal is written to the Site Log.
- The dialog refuses a wrong type or a file over `file_max` (the smaller of the file and request limits) before sending it.

## What is Recently used?
- The last 12 picks of a kind, per person, in this browser: `localStorage` `pg.picker.recent.<user id>.<kind>`, value, name, type and folder only (`addRecent()`). A pick is added when the dialog closes with it.
- Opening it resolves the values; what is missing, binned or out of reach now is dropped and the list written back. Label key `Recently used` (`Son kullanılanlar`), not `Recent`, which reads "Son Gezilenler" elsewhere.

## How do several picks work?
- `pgPicker.open({kind, value: [values], multiple: true})` resolves to the items in the order picked, or null. Up to 50 stored values are resolved on open.
- Mouse: a click adds or removes, Shift+click adds the files from the last click, a double click adds and chooses. Keys: arrows move only, Space adds or removes, Shift+arrow adds, Ctrl+A adds every file in view, Enter chooses (on a folder it opens it).
- The footer shows up to four pictures and `{n} chosen`. Pure helpers `togglePick()`, `rangePick()` in `pgPicker.pure`, tested in `tests/picker_js_test.php`.
- Not wired to a field yet: the product pictures move to it in PK9; until then it is tried from the console on any panel page that prints the picker.
