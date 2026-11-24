---
name: launch-summary-file-lines
description: An agency summary line asking for files (LS2): the drawer's "Answer" select, the customer's file field, upload checks, the private "From the customer" folder, remove, the bell's count
module: launch_summary
---

## What does it do?
- In the "Agency summary" drawer every line has an "Answer" select: Text, One file, Several files (panel action `launch_summary`, op `kind`; `answer_kind` 0/1/2, `launch-summary-lines.md`). A changed kind clears the line's answer after a confirm; files already sent stay in their folder.
- On `launch_summary.php` a file line draws the files sent (name, size, a quiet remove) as `.pg-gather` rows and, while the line takes more, a file field (`multiple` for several) with the kept types and the server's limit under it. No textarea. A one-file line with its file drawn has no field.
- The drawer lists the files sent under the line, each a link through `get_file.php`.
- Owner asked for it in `workflow/decisions-made-product-101.md`; the column in `workflow/decisions-made-schema-50.md`. LS2 of `plans/launch-summary-files.md`.

## What does an upload check?
- On the server, whatever the page drew: the link is open (`pg_launch_summary_link_state()`), the form token, the line's kind (`pg_launch_summary_upload_gate()`: `link`, `kind`, `none`, `count`), then each file on its own.
- Per file: the server's single-file limit (`pg_upload_file_refusal()`), `is_uploaded_file()`, and the type from its first 16 bytes (`pg_launch_summary_file_type()`): jpg, png, gif, webp, pdf, zip; a zip container keeps docx, xlsx, pptx, odt, ods, odp from the name, else zip; a compound file is doc, xls or ppt by name, else refused. svg, html, scripts and anything else are refused by name in an error; the other files are kept.
- At most 20 files a line (`PG_LAUNCH_SUMMARY_FILES_MAX`), one on a one-file line; a request over the count is refused whole.
- An upload over `post_max_size` arrives empty: the forms post to `launch_summary.php?t=<token>`, so the page reads the link from the address and says the request was too large (`pg_post_body_discarded()`).
- Rate: `launch_summary.php` is in `waf_sensitive_scripts()`, the same allowance as the text answers.

## Where are the files kept?
- `pg_file_store_upload()` into the folder "From the customer" under the file manager's top folder, `private`, made on the first file (`pg_launch_summary_folder_id()`; a folder of that name made public is not used). `get_file.php` serves a private folder only to a signed-in user with access: the panel's staff.
- Stored name `<stem>-<8 random hex>.<type from the bytes>` (`pg_launch_summary_file_name()`): never the sent extension, never a guessable address; `data/` itself is closed to HTTP. Nothing is placed on a page, the logo or a gallery.
- `pg_file_store_upload()` now writes `user` 0 and logs with no username when no one is signed in.

## How is the list kept?
- The line's `answer` is a JSON list of file ids (`pg_launch_summary_file_ids()`); `pg_launch_summary_line_files()` reads it against the files table, so a file deleted in the file manager drops out when the line is read. The pill and "Answered" follow the files still there.
- A remove on the customer's page deletes the file (`pg_delete_file_record()`) and takes it off the list; an empty list makes the line unanswered.

## Who hears it?
- The `launch_answer` bell line to the line's writer, `form_id` the files the line holds: "The customer sent files for "{line}" ({n} in all)"; at most one a line an hour, like a text answer.

## Tests?
- `tests/launch_summary_files_test.php`: a refused type (svg, a script named .jpg), an expired or withdrawn link, a text line, the 21st file, a second file on a one-file line, the stored name, the multiple field, the schema step 11.0.278.
