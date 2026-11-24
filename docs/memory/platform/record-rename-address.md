---
name: record-rename-address
description: A renamed record (post, category) keeps no old pretty address: the save rewrites forms.address_name and the old one answers 404 (#11439, open for a decision)
---

## What happens to a record's old address when its title or slug changes?
- Every save path (Edit Submitted Form, Workspace, API, the designer's collection rows, imports) calls `update_submitted_form_address_name()` (`includes/fn/core.php`), which writes the new name over `forms.address_name`. Nothing keeps the old one.
- `/<page>/<old name>`: `pg_sw_route_record_address()` (`includes/fn/widgets.php`) finds no record and answers 404 (a `*` short link pattern is still tried at 404).
- `?category=<old name>` on a list page: `pg_coll_reference_lookup()` (`includes/fn/collections.php`) matches the address name or the reference code only, so the list is empty and the page turns noindex.
- Only the code address (`?r=<reference code>`) survives a rename.

## How do pages and imports keep an old address, for comparison?
- A renamed page: a 301 short link to the page, offered on the screen (`page-rename-redirect.md`).
- An imported WordPress post: a 301 short link with a URL destination to the record's address (`pg_import_redirect_record_url()`, `import-redirects.md`); it does not follow a later rename.

## Is it decided?
- No. Issue #11439 waits on a decision between a 301 short link per rename (URL destination, as imports do) and a list of a record's earlier names (a schema change); the `?category=` lookup is reached only by the second.
