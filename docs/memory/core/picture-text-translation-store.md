---
name: picture-text-translation-store
description: A Design Canvas picture's texts in the translation store: owner type file, layer fields read, file_translation states, what saves, deletes and translation writes do
module: picture_translation
---

## What enters the store?
- `includes/fn/picture_translation.php`. A picture whose live document (the newest `file_versions` row, `ui/image-editor-versions.md`) has words gives them as owner type `file`: `owner_id` the file's id, `node_id` the layer's id, `field` `text` (a text layer) or `label` (a sticker), `position` their order (`pg_picture_tr_doc_words()`).
- Only `doc.layers`, visible layers, through `pg_tr_normalize()` and `pg_tr_translatable()` (`translation-translatable-text.md`): `%50`, `₺199`, `KT-1` stay out, `199 TL only` enters. Same hash as a page's plain text, so one string, one translation.
- Owner (2026-11-16): first version only pictures made in the Design Canvas, shown by a page in another language; product decision in `workflow/decisions-made-product-64.md`.

## Which saves write the uses?
- `pg_picture_tr_saved()` from `pg_file_versions_after_save()` (a photo's replace), `pg_image_editor_canvas_keep()` with `$words` true (only `pg_image_editor_save_canvas()`, a canvas in Files) and the Versions drawer's Restore (`pg_image_editor_action_restore()`).
- A Workspace canvas (a member's or an agent's) keeps no texts: its callers leave `$words` false.
- A flat save (no document kept) removes the file's uses. Every save turns the file's drawn rows out of date.

## Why does the whole site leave them out?
- `pg_tr_owner_where(array())` is `u.owner_type NOT IN ('ui', 'file')`: every canvas picture with words has uses, but only one a page in another language showed is counted and sent, by its own group. Without it a banner nobody shows went to the engines and weighed on the site counts.

## What does file_translation hold?
- One row per source file and language (schema `workflow/decisions-made-schema-25.md`): `state` 0 waiting, 1 drawn, 2 out of date; only 1 is shown (`pg_picture_tr_shown()`). Moves: `pg_picture_tr_state_after()`: a save or a changed translation turns 1 into 2; a draw makes any row 1.
- `pg_picture_tr_translation_written()` runs in `pg_tr_save_translation()` before the write (after the reviewed-kept refusal): drawn rows of the language whose file uses the string go to 2 when the text is new or differs.
- `pg_picture_tr_translations_removed()` after an emptied box (`translations_action.php`), back to pending over the API and Clear translations (`pg_tr_clear()`): a drawn row whose file uses a text with no translation goes to 2.

## When do a file's texts and rows go?
- Deleted for good: `pg_file_versions_delete_file()` calls `pg_picture_tr_forget()` first (uses, rows and each language's picture file). Strings and translations stay.
- Binned or changed outside the editor (rotate, Optimize, an upload over it): the daily `pg_file_versions_sweep()` ends with `pg_picture_tr_prune()` in what is left of its budget (`pg_picture_tr_prune_reason()`).

## Where is it tested?
- `tests/picture_translation_test.php`: words read and skipped, the shared hash, flat versions, prune reasons, state moves, the hooks' places, the whole-site condition. The database paths were not run.
