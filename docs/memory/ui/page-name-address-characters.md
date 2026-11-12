---
name: page-name-address-characters
description: Which characters a page name saved on Add Page, Edit Page, Mass Edit and the Pages quick edit loses (space, &, ?, %, + become _), why, and what # and older names do
---

## What does the classic page form do with the name?
- `pg_page_form_clean_name()` (`includes/forms/page_form.php`) turns spaces, `&`, `?`, `%` and `+` into `_`; Add Page, Edit Page, Mass Edit (`pg_page_form_bulk_name()`) and the Pages list quick edit all go through it. The rename box of Edit Page reads the typed name the same way (`bindPageRenameRedirect()`, `backend.src.js`).
- The name is cleaned, not refused, as spaces always were: "Sorular?" is saved as `Sorular_`.

## Why these three?
- The name is the page's address, and IIS decodes the path before PHP reads it (`ascii-names-in-urls-iis.md`). A `?` sent as `%3F` reached the router as `?` and cut the name there: the page saved, but every link the panel gave for it answered 404.
- IIS request filtering refuses a path holding `+` (raw or `%2B`) or a `%` followed by two hex digits (`%2520` once encoded) as a double escape while `allowDoubleEscaping` is off, its default (neither `web.config` nor `server_config.php` sets it): its own "HTTP 404.11" before PHP runs, right after a successful save. Turning the setting on would loosen the filtering for the whole site, so the name rule is the fix.
- Symptom before (#8984, #9055): `pg-test-soru?isareti`, `pg-test/alt yol?x=1#y%20&z.php` and `pg-test-arti+isaret` were saved and never viewable.

## What is kept?
- `#` (`%23` reaches the router whole) and `/` outside catalog and catalog detail pages.
- A page saved earlier with `?`, `%` or `+` keeps its name until it is saved on Edit Page, which then renames it to the `_` form.
- The API's design resource refuses `? # % \ < > "` and control characters with a message instead (`api_design_page_name_in()`); the Visual Page Editor's own save (`pg_designer_save_page()`) does not clean the name yet (#9003).

Tests: `tests/page_form_save_test.php`, `tests/mass_edit_page_name_test.php`.
