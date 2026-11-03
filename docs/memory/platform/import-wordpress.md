---
name: import-wordpress
description: WordPress WXR reader of the import framework: streaming walk, what each item becomes, skipped internals, comment threads, dates, shortcodes, cursor, mapping rows
---

## How is a WXR file read?
- `includes/import/wordpress.php`, the reader contract of `import-framework.md`. `XMLReader` walks `<item>` elements one at a time; each item's outer XML is read inside a wrapper that declares the namespaces again and parsed with SimpleXML. The whole file is never in memory.
- The head before the first item gives the old site's host (`base_site_url`, `www.` dropped) as the source key prefix, the export namespace (WXR 1.0, 1.1 and 1.2 are in use) and the authors by login (`pg_import_wp_site()`).
- PHPExcel switches libxml's entity loader off for the request, which stops `XMLReader::open()`; `pg_import_wp_reader()` switches it on again as `office.php` does (no `LIBXML_NOENT`, always `LIBXML_NONET`). Without it the reader saw an empty file whenever a product import had run before in the same process.

## What does each item become?
- post and every custom post type: `record`; page: `page`; attachment: `file` (`url`, caption, parent post). Source key `<host>:post:<id>`, comments `<host>:comment:<id>`.
- Left out: `nav_menu_item`, `revision`, block editor and customizer internals (`pg_import_wp_skipped_types()`), status trash and auto-draft. publish and inherit are published; draft, pending, future and private are drafts.
- A record's data: post type, status, body, excerpt, time, slug, old link (for redirects), parent, author (login, display name, e-mail), categories, tags, and the featured image as the attachment's source key (`_thumbnail_id`): the file item may come later in the file, so the writer looks it up then.

## Which comments come in?
- Type comment (or empty) with approved 1 (published) or 0 (held, unpublished). Spam, bin, pingbacks and trackbacks are left out. `parent` is the source key of the comment answered, so threads are kept. Comments follow their post as separate items.

## How are dates, shortcodes and paragraphs read?
- `pg_import_wp_time()`: the GMT date when it is a date (drafts carry 0000-00-00), else the local date in the given zone, unknown zone as UTC.
- `pg_import_wp_shortcodes()`, a fixed list: caption keeps what it wraps, embed becomes a link to its address (only http and https), gallery is removed (its pictures are the attachments); every other shortcode stays as text.
- `pg_import_wp_autop()`: classic editor text (no `<p>`) gets paragraphs and line breaks as WordPress shows it; block editor HTML stays as it is. The writer still passes the body through `pg_sanitize_rich_text()`.

## How does the cursor resume?
- The cursor is the number of items read plus the comments of the last item not yet handed out. Within one request the reader stays open; a later request opens the file again and skips that many items.

## Which mapping rows does it ask for?
- The form posts become records of (required), its fields for title (required), body, summary, date, featured image, categories and tags, and the folders for pages and files.

Tests: `tests/import_wordpress_test.php` on `tests/fixtures/import/wordpress.xml`.
