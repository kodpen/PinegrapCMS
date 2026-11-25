---
name: record-rename-address
description: A renamed record (post, category) keeps its old pretty address: form_old_address (11.0.279), the 301 from /<page>/<old name>, ?category=<old name>, live names first, 20 a record (#11439)
module: record_old_address
---

## What happens to a record's old address when its title or slug changes?
- Owner (2026-11-24): the earlier names are kept in a table (`form_old_address`, step 11.0.279), not as short links. Before (#11439), the save overwrote `forms.address_name`: `/<page>/<old name>` answered 404 and `?category=<old name>` listed nothing and turned the page noindex.
- `update_submitted_form_address_name()` (`includes/fn/core.php`), which every save path calls, passes the old name and the new one to `pg_record_old_address_saved()` (`includes/fn/record_old_address.php`).

## When is an old name kept?
- Only for a complete record whose earlier name was not empty and differs from the new one (`pg_record_old_address_kept()`). A draft was never on the site.
- One row per name of a record. At most 20 a record (`PG_RECORD_OLD_ADDRESS_MAX`): the oldest go.
- There is no backfill: names are kept from the upgrade on. Import redirects stay short links (`import-redirects.md`).

## Which name wins?
- A live name always does. A record that takes a name removes that name's old rows on the same form page, whether it is a rename back or another record taking it. Every lookup asks the live names first.

## How does an old name answer?
- `pg_record_old_address_record()` joins `forms`, so a row whose record is gone, is a draft or has no code answers as an unknown name. When two records held the name, the newest row wins.
- An old name never draws a page: it answers 301 to the record's address of today, the visitor's query string kept (`pg_record_address_redirect()`).
- On a record view page (`/<page>/<old name>`), `pg_sw_route_old_record_address()` checks a public record view, a folder the visitor may open and the view's filters, as a list's redirect does.
- On a list page with a detail page (`pg_sw_route_record_address()`), the old name goes through the live name's checks.
- On a classic form list view (`get_page.php`), the visitor must be able to open the form item view page's folder.
- A failed check answers as an unknown name, so an old name tells no more than a live one.
- In a list filter, `pg_coll_reference_lookup()` matches an old name byte for byte after the live names and codes, so `?category=<old name>` lists as the name of today does.

## When do the rows go?
- With their record: `pg_submitted_form_delete()`, the import undo (`pg_import_unwrite_record()`) and the personal data erasure (`form_old_address` in `pg_privacy_erase_tables()`, the inventory and the form submissions retention rule).

Tests: `tests/record_old_address_test.php`, `tests/privacy_erase_test.php`.
