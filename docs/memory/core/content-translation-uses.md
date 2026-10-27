---
name: content-translation-uses
description: translation_uses (where a text of the front-end translation is drawn): owner types, who rewrites them, and how a deleted or binned page's texts leave the counts and the pending list
---

## What is a use?
One row of `translation_uses`: a string (`translation_strings`) drawn by an owner. Owner types: `page` (body), `page_seo` (title, description), `page_ref` (string 0; `node_id` = referenced owner type `shared` / `menu` / `form_records`, `field` = its id), `shared` (shared component or system widget), `menu`, `product`, `product_group`, `attribute`, `offer` (an offer's message, suggestion and button label), `form`, `form_records`, `ui` (cookie window). The site counts (`pg_tr_language_stats()`), the pending texts sent to an engine (`pg_tr_pending_string_ids()`), "Approve all" and "Clear translations" all read uses. `pg_tr_map()` only loads translations of strings that still have a use.

## Who writes them?
`pg_tr_extract_page()` rewrites a page's `page`, `page_seo` and `page_ref` rows and, through the references, its components and menus; shared components and menus are extracted only through a page. The catalog and forms groups replace all uses of their types. `pg_tr_ui_sync()` keeps `ui` to the cookie window.

## What happens to the texts of a deleted or binned page?
`pg_tr_uses_prune($page_ids)` (`includes/translate/extract.php`) deletes the `page` / `page_seo` / `page_ref` rows of every page that is not in `pg_tr_translatable_pages()` (deleted, in the recycle bin, no tree any more), then the `shared` and `menu` rows no remaining `page_ref` row points at. Strings and translations stay: a page restored from the bin gets its texts and translations back on the next extraction. It runs at the end of a whole-site extraction (`pg_tr_extract_all()`, after the last turn, before the pending texts are read) and when the Translations screen opens with a language (like the cookie window sync). With no translatable page at all it removes nothing, so a failed page query cannot empty the store.

## Why?
Before #849 (PR #1018) a deleted page's uses stayed for ever: the whole-site counter never reached 100 %, "Update translations" sent the dead texts to Google / Pinegrap AI / Claude on every run, and Approve all / Clear translations counted them. Page delete paths are many (page editor, bulk edit, designer, File Manager, recycle bin moves), so the store is cleaned where it is read rather than on each of them.

## What is not pruned here?
`form_records` of a widget no page places: the Forms group keeps the records of every widget that includes them (`pg_tr_record_forms()`). The external API counts reflect the last prune (screen open or whole-site update).
