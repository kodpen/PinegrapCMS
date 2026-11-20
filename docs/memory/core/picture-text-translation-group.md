---
name: picture-text-translation-group
description: Pictures group of the Translations screen (#10102): file texts bounded to pictures with a file_translation row, whole site alike, extraction, Design Canvas link
module: picture_translation
---

## What does the group hold?
- Its name is "Görseller" (key `Images`), the word its note and the Design Canvas use; the owner chose it on poll 118 (agent channel message 8070). "Pictures" stays "Resimler" on other screens.
- `pg_tr_owner_groups()` (`includes/translate/content.php`) lists `pictures` ("Images", Turkish "Görseller", `bi-image`, types `file`) under Other texts, between Forms and the Cookie window.
- Its owners are never every file: `pg_tr_owner_where()` turns `('file', '*')` into `pg_tr_picture_owner_bound()`, the texts of a file with a row in `file_translation` (written when a page showing the picture is saved in the Visual Page Editor, `picture-text-auto-rows.md`, or by the page pass when a page in another language shows it). Before the table exists it is `0 = 1`.
- The bound is the file's, not the language's: a picture a page showed in one language counts for every target language.

## How does the whole site take it?
- `pg_tr_owner_where(array())` is `u.owner_type NOT IN ('ui', 'file')` plus `OR (` the same bound `)` (`pg_tr_picture_owner_or()`), so "translate the whole site", its counts, CSV, Clear and Review all reach the same pictures as the group. Every caller wraps the condition in parentheses (`tests/picture_translation_group_test.php` checks).
- `pg_tr_extract_all()` runs the group as its third step after catalog and forms.

## What does extraction do?
- `pg_tr_extract_pictures()` (`includes/translate/extract.php`) reads every file with a row: a live document (`pg_picture_tr_live()`) has its words written again; a binned, flattened or gone file is forgotten (`pg_picture_tr_forget()`: uses, rows, language pictures). A file with no row is never read; its uses go with the daily prune.

## What does a row show?
- On the group's view each text names the pictures it comes from (`pg_tr_picture_files()`, same bound), as a badge button with `data-pg-image-edit` (title "Edit this image in the Design Canvas"), which opens the file in the Design Canvas (`ui/image-editor-callers.md`); the loader is printed only when such a badge is listed.

## What does the empty group say?
- The list reads "None yet" (as the Cookie window), never "Not scanned": Update lists no picture before a page showing it is saved in the Visual Page Editor or opened in another language. The panel carries a note of its own saying which pictures are listed and when, and the empty panel says the same instead of "press Update".
- Since #10448 the note also says that a language's picture is drawn by itself once all its texts are translated (by an open panel, or with nobody in the panel by the server where it can) and that "Draw the waiting pictures" draws them now (`picture-text-auto-panel.md`, `picture-text-auto-server.md`).
- A chosen group is the one current row: All pages is current only with no page and no group, and a page link or All pages leaves the group (before, a page pressed while a group was open stayed in the group).

## Tests?
- `tests/picture_translation_group_test.php`. Checked in a sandbox (#10102): a picture no page showed neither counted nor sent; counts per language; the extraction dropping a picture with no live document.
