---
name: import-media
description: Content import pages and media (2026.11.0): page items as draft designer pages, attachments and body pictures fetched into Files, body addresses rewritten, notes in the report
module: importers
---

## Where is it?
- `includes/import/media.php` (fetch and rewrite), `write_file.php` and `write_page.php` (the framework's file and page writers, `platform/import-framework.md`). Tests: `tests/import_media_test.php`.

## How is a file fetched?
- `pg_import_media_fetch()`: the address made normal (`pg_import_media_normal()`: http and `//` asked over https, fragment dropped; data: and relative addresses are not fetched), then `pg_curl_run()` with `check_address` (no address inside the network, pinned), a `sink` in `data/temp/import_<id>_media.tmp`, `cap` = `pg_upload_limits()['file_max']` (64 MB when unlimited), 20 s, no redirect.
- The file is stored by `pg_file_store_upload()` (blocked names, `prepare_file_name()`, unique name, picture size cap), in the mapping's `file_folder` (else the root folder); `files.user` is the importing person, also when the job runs it.
- A body's `<img>` needs an `image/*` answer; an attachment any type.
- A WordPress size variant (`-300x200`, `-scaled`) fetches the full-size file first and falls back to the variant, so the body and the attachment share one file.

## How do a body and an attachment share one file?
- Every fetched file gets an `import_items` row kind `file`, key `media:` + sha1 of the fetched address, besides the attachment's own `wp:post:<id>` row. Two rows may name one file: `pg_import_unwrite_file()` does nothing when the file is gone.

## How is a body rewritten?
- `pg_import_media_localize()`: the source of each `<img>` (data-src, data-lazy-src, src, then the widest srcset) is fetched, at most 25 per item; `pg_import_media_rewrite()` sets `src` to `OUTPUT_PATH` + stored name and drops srcset, sizes and the lazy loaders' attributes; other tags stay byte for byte.
- A picture that fails keeps its address and is named in the writer's reason. The report lists a made item with a reason as "Made, with a note" (`pg_import_report_add()`).
- The record writer (IM2) calls the same `pg_import_media_localize()` for its body and `pg_import_media_fetch()` or the picture's source key for the featured image.

## How does a page item become a page?
- Only a designer or an administrator's import writes pages (the tree is design markup); otherwise failed with a reason.
- Body pictures localised first, then the body wrapped in a document with the title and passed through `pg_designer_import_single_html()`; saved by `pg_designer_save_page()` with `page_draft` 1, `revision_source` import, the name from the slug (else the title) made unique, the excerpt as meta description, `page_folder` = mapping `page_folder` (where it is published from the drafts).
- Design: the folder's inherited `folder_style`, else the home page's, else the newest `visual_designer` style; none fails the item.
- Undo: `pg_delete_page_record()` (the pages screen's delete path).
