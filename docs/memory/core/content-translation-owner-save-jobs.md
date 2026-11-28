---
name: content-translation-owner-save-jobs
description: Translate on save for a product, product group, offer and form (TS6, PR #12228): scopes product:/product_group:/offer:/form:<id>, one item read again, which save paths open a job and which do not
module: translate
---

## What happens when one is saved?
- `pg_tr_owner_auto_saved($type, $id, $user_id)` (`includes/fn/translate.php`): for the languages with "Translate on save" and a ready Pinegrap AI, Claude or Google engine (`pg_tr_auto_languages()`, served languages only), `pg_tr_auto_update()` opens one job per language under scope `product:<id>`, `product_group:<id>`, `offer:<id>` or `form:<page id>`: same lock and open-job guard as a page save, no engine call during the save.
- With no engine ready the item is not read again.

## What is read again?
- `pg_tr_extract_owner()` (`includes/translate/content.php`) reads only that item and replaces only its uses (`pg_tr_store_owner_type()` with `$only`): a product or group by `pg_tr_catalog_owner_segments()`, an offer by its messages, a form by `pg_tr_form_owner_segments()` (title, confirmation message, sender's e-mail subject, field labels, help texts, options). A disabled product, group or offer keeps no uses and opens nothing.
- The whole catalog and forms are still read by "Scan for new texts" on their group.

## Which saves open a job?
- Product: Edit Product, Add Product (each new product, and the set in group mode), the API's product create and update.
- Product group: Edit Product Group, Add Product Group, the API's create and update.
- Offer: every save through `_pg_offer_save()` (the offer screen, its list's quick edit, the API's create) and the API's offer update.
- Form: a page save that writes its form (`pg_cf_reconcile_page_form()`, not read-only), Add Field and Edit Field on a page form.

## Which do not?
- Imports, duplicates, bulk edits, a product's on-sale switch and price changes through the API, the Workspace, supplier feeds and design templates: their texts wait for the next save or a scan, so an import never opens thousands of jobs.

## How does Activity name such an update?
- `pg_tr_activity_scope_label()`: a product, group or offer by the catalog group's label "Products, product groups and offers", a form by "Forms".

## Where is it tested?
- `tests/translations_settings_promise_test.php` (scopes), `tests/translations_activity_test.php` (labels).

Related: docs/memory/core/content-translation-record-save-jobs.md, docs/memory/core/content-translation-jobs-duplicates.md
