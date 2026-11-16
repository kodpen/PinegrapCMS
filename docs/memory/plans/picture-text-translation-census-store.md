---
name: picture-text-translation-census-store
description: Picture text translation PT0 census, store: picture texts in translation_uses, the Translations screen group, a job, pg_tr_translatable() on short words and prices
---

## How would a file's texts enter the store?
- No schema change: `translation_uses.owner_type` is `VARCHAR(32)` (2026.4.6), so a new type `file` with `owner_id` = `files.id`, `node_id` = the layer's `id`, `field` = `text` (or `label` for a sticker), `position` = the layer's order.
- `translation_strings` keyed by the SHA-1 of the normalized text, `format` `text`, `kind` `content`: a banner's "Free shipping" is the same string as the page's, so an existing translation, the glossary and a review apply at once.
- `translations` unchanged: one row per string and language, `machine` or `reviewed`.
- Today's owner types: `core/content-translation-uses.md`.

## Who would write and remove the uses?
- An extractor per file reading the live document (`-census.md`): the newest save row while the file holds its pixels. Places it could run: the editor's save (`pg_file_versions_after_save()`, `pg_image_editor_canvas_keep()`, `ws_canvas_keep()`) and a group extraction (`pg_tr_scope_extract('group:…')` calls `pg_tr_extract_group()`).
- Nothing removes them today: `pg_tr_uses_prune()` deletes only `page`, `page_seo`, `page_ref`, then unreferenced `shared` and `menu` rows. A `file` use needs its own prune: the file deleted (`pg_file_versions_delete_file()` and the daily sweep), binned, or its document no longer live (changed outside).
- Until pruned, a dead file's texts stay pending and are sent to engines on every run (the #849 symptom for pages).

## What do the Translations screen and a job need?
- The screen lists groups from `pg_tr_owner_groups()` (`catalog`, `forms`, `ui`); a `pictures` group with types `array('file')` is listed, counted (`pg_tr_language_stats()` reads uses) and sent as scope `group:pictures`: `pg_tr_scope_owners()` turns any group into `(type, '*')` owners, `pg_tr_pending_string_ids()` reads them. `translation_jobs.scope` is `VARCHAR(64)`.
- Needed beside: `pg_tr_extract_group('pictures')`, an owner's name for a row (the file name) and a link to open it (the Design Canvas with the language picked, T2).
- Whether the group joins the whole-site scope (`pg_tr_extract_all()`) or stays apart as `ui` does is open (owner question 4).
- A translated text is not yet a picture: a job ends with translations; drawing is a step after it (`-census-draw.md`), and the picture's state (translated, drawn, out of date) is not a translation status.

## What does pg_tr_translatable() do with a banner's words?
- Rule: `core/translation-translatable-text.md`.
- Skipped, never stored: a text with no letter (`%50`, `-30%`, `₺199`, `2026`), a code of capitals with a digit (`KT-1`, `X5`), Lorem ipsum, a lone `^^token^^`.
- Kept: one plain word (`SALE`, `Yeni`, `İndirim`), a price inside words (`199 TL`, `Only $9`), an address or an e-mail alone. An engine may change `TL` or a brand name; the glossary or a review keeps it.
- A layer whose text is skipped draws its source letters in every language, which is right for a price.
