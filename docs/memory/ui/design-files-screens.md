---
name: design-files-screens
description: All Design Files and Edit Design File: type read from the name, Design Canvas script outside the table, Recycle Bin left out, POST branch, blocked rename, optimize way back
module: files
---

## Where does the Type column come from?
- `pg_file_name_extension($name)` (`includes/fn/files.php`, pure): the lower-cased part after the last dot, `''` for a name without one. `files.type` is four characters wide (`data/files-type-column-width.md`), so a `.woff2` showed "woff". Sorting by Type orders by the same expression in SQL. Edit Design File takes the preview, image tools, edit buttons and the Theme switch from it, and its save writes it into `files.type` (a name with no dot stores an empty type, not its first four letters).

## Why is the Design Canvas configuration after the table?
- `pg_image_editor_includes()` prints its ~19 KB `<script>` only once; called in a row, it landed in the first image's Action cell. DataTables sized the column by that text (3,341 px, a 4,688 px table) and the list search matched it ("css", "Kaydet"). The list sets `$has_image` and prints it once before `</main>` (#5821).

## Are Recycle Bin files listed?
- No. `pg_design_files_bin_where()` adds `files.folder NOT IN (bin and its sub-folders)` (`pg_recycle_bin_folder_ids()`) to the list and the count, and to Edit Design File's not-found gate. `select_folder()` leaves the bin out, so a Save posted the first folder and moved a private bin file to the public root (#5823). Restoring stays the bin's own job.

## How does Edit Design File pick its branch?
- On the method (`$_SERVER['REQUEST_METHOD'] !== 'POST'` draws the form), not on `empty($_POST['name'])`: a POST with an empty name drew a blank form ("not on the disk", 0 B) and Delete never ran. Every read uses the gated `$id`, never `$_GET['id']` (#5822).
- A changed name that `pg_upload_name_blocked()` refuses stops with `pg_upload_blocked_message()`, as Upload and Edit File do; before, `prepare_file_name()` stored `x.php` as `x_php` and said it was saved (#5824).

## Where does Optimize go back to?
- Both optimize buttons (list and edit) send `send_to` = the design list path, which `optimize.php` recognises; without it the user landed in the File Manager with the notice (#5825).

## What else is aligned with the File Manager?
- Dimensions are `pg_image_dimensions_label()` ("20 × 10 px") in the edit screen and the list thumbnail title; WebP has a preview on the edit screen; Upload Design Files with an empty queue shows "Please select a file." in `#pg_upload_error` (#5826).
- Tests: `tests/design_files_screens_test.php`.
