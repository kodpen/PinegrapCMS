---
name: whatsapp-template-approval
description: WhatsApp template designer, second half (WA9, #9395): header from Files, buttons, Send for approval with the sample upload, a rejected template edited, test send with media
module: whatsapp_design
---

## What does the header take?
- None, a text (at most 60, no variable) or a picture, video or document from Files: JPG/PNG up to 5 MB, MP4/3GP up to 16 MB, PDF up to 16 MB (Meta takes 100 MB; a sample is read into memory). `pg_whatsapp_design_header_formats()`.
- The list is `pg_whatsapp_design_header_files()`: non-design files by name ending (files.type is cut at four characters), newest 200, never `pg_file_withheld()`. The chosen file is kept in samples `header_file` {id, name}; the stored HEADER has no example until it is sent.

## Which buttons?
- Up to three (drawing): Link (`https://…`, a `{{1}}` only at its end with a sample, at most two), Phone call (`+` and 7–20 digits, one), Quick reply. Text at most 25. `pg_whatsapp_design_check_buttons()`.
- Saved quick replies come last (Meta wants them grouped). A link's sample is kept in its `example` (the full address); `pg_whatsapp_design_url_sample()` reads it back.

## What does Send for approval do?
- Saves first, then `pg_whatsapp_design_submit()`: refused on a demo site, without WhatsApp, or when not editable. A media header's file (`pg_file_reader_path()`, object store too) goes up through the Resumable Upload API (`/<app id>/uploads`, then the bytes with `Authorization: OAuth`); its handle goes into `example.header_handle`.
- A new template: `POST /<account id>/message_templates` (POSITIONAL); a rejected one: `POST /<template id>` with category and components only. Driver: `includes/whatsapp/cloud_templates.php`.
- The row takes Meta's id, status (PENDING, or APPROVED at once), category and the sent components; reason cleared. The six-hourly refresh (`whatsapp-templates.md`) brings the review's result.

## What can be edited?
- A draft, or a REJECTED template (`pg_whatsapp_design_editable()`): its name and language stay (fields disabled, kept by `pg_whatsapp_design_posted()`). In review, approved and others are read-only. A rejected template saved but not sent again is overwritten by the next refresh.

## What does the test send do with media and links?
- A media header goes as a `link` to the file's public address, only when `pg_file_open_to_everyone()`; otherwise refused. A link button's variable is filled with its sample. Copy-code and one-time-code buttons are still refused (WA10).

## Which trap was found?
- `db_q()` reads every `?` of its SQL as a placeholder, so a text with "?" was spliced into the SQL and the query refused: drafts with a question mark did not save (WA8) and a Meta template with one was never stored by the refresh (WA3). Both now bind their values (`data/db-q-placeholder-helper.md`).

## How was it verified?
- `tests/whatsapp_cloud_templates_test.php`, `tests/whatsapp_design_test.php`. Sandbox: an image header and two buttons saved, a bad link refused, Send for approval refused without WhatsApp. No Meta account: the upload and submission were never run against Meta.

Related: docs/memory/platform/whatsapp-template-designer.md, docs/memory/platform/whatsapp-templates.md
