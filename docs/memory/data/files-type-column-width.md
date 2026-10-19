---
name: files-type-column-width
description: files.type is four characters wide, so long extensions are stored cut (webmanifest as webm); the file manager reads the kind from the name instead; which views still read the column
---

## What is wrong with files.type?
- `files.type` is `varchar(4)` in every installation (`data/backups/*/sql.sql`); no migration widened it. Uploads and renames write the extension into it, and MySQL in the non-strict session mode the product pins cuts it: a `.webmanifest` is stored as `webm`, a `.markdown` as `mark`. Short extensions (jpg, pdf, docx, xlsx) fit.
- Symptom fixed in PR #518 (found by reading the code): the file manager's file window and Quick Look played a `.webmanifest` as a video (`webm` is in the video list) and never offered its text editor, and a `.markdown` lost its icon.

## How does the file manager decide a file's kind now?
- `pg_explorer_file_type($file)` in `view_folder_and_files_f.php`: the extension of the name, lower-cased; the column only for a name without an extension. `pg_explorer_file_payload()` uses it for `type` and `is_image`, so the grid, the file window, Quick Look, the editor choice (`pg_explorer_editable_formats()`) and the icon all follow the name. Test: `tests/explorer_file_type_test.php`.
- A new place that needs a file's kind calls that function; it does not read `$file['type']`.

## Where is the column still read, and why is that fine?
- The flat views Images, Media and Documents filter in SQL on `LOWER(files.type)` (`explorer_list`), the optimize and rotate checks and the zip check compare short extensions, and the search index reads it. All compare against extensions of at most four characters, except that a `.webmanifest` is listed under Media as a `webm`; widening the column is a schema step (owner's call) and was not done.
