---
name: picture-text-translation-steps
description: Plan picture-text-translation, steps PT1 to PT4b: the table and the texts in the store, the page pass, the Pictures group, drawing a canvas picture and a photo in the Design Canvas
---

## Which steps are there?
- PT1 (M) Done (PR #10028). The table and the texts. Schema (the owner's yes, poll 98): the draft PR's body repeats the table of `workflow/decisions-made-schema-25.md`; a change to it is asked of the architect there.
  - New: `includes/fn/picture_translation.php` (the reader of a live document's words: a text layer's `text`, a sticker's `label`; the uses of owner type `file`; the prune; the state rules as pure functions), `tests/picture_translation_test.php`, the migration step with `install_create_table()`, run twice.
  - Existing: `includes/fn/image_editor.php` (`pg_file_versions_after_save()`, `pg_image_editor_canvas_keep()`: a save refreshes the file's uses and sets its rows out of date; `pg_file_versions_delete_file()`), the place a translation is written (a changed string sets the rows of the files that use it out of date).
  - Tests, red first: the words read and what `pg_tr_translatable()` skips; a page's same words giving the same string; a flat row giving none; uses gone when the file is deleted, binned or changed outside the editor; each state move.
  - No screen.

- PT2 (M) after PT1: the page pass. Done (PR #10106).
  - Existing: `includes/translate/render.php` (`pg_tr_render_finalize()`).
  - The swap of this site's picture addresses in `src`, `srcset`, a `<source>`'s `srcset`, an `og:image` meta, an `href` to a picture and `url()` in a `style` attribute, plain and with `?w=` or `?f=`. One query for a page, no document decoded. Only a drawn row is swapped. A picture with uses met in a language for the first time gets its waiting row; none for a right-to-left language.
  - Tests: each place swapped, each left-out place untouched; an out-of-date row not swapped; the query count; a private folder's picture served by the source's rights.

- PT3 (M) after PT1: the "Pictures" group on the Translations screen.
  - Existing: `includes/translate/content.php` (`pg_tr_owner_groups()`), `includes/translate/jobs.php` (`pg_tr_scope_owners()`: for this group the owners are the files that have a row, never every file), `includes/translate/extract.php` (the group's extraction, the prune).
  - A row names the file and opens it in the Design Canvas. The group joins "translate the whole site" with the same bound.
  - Tests: a picture no page showed neither counted nor sent; the counts by language; a job's scope.
  - Pictures to the architect: the group and its rows, 1440 and 390, light and dark.

- PT4 (M) after PT2 and PT3: drawing a canvas picture ("New image") in the Design Canvas.
  - A "Languages" list for a picture with words: each site language with its state (Not translated, Translated, Drawn, Out of date, Look by hand), "Draw" and "Draw all". The browser puts the language's words into the layers, fits each to its box, draws with the save's renderer and posts the picture; the server keeps it as the language's `files` row, sets the row drawn and marks the page cache (`platform/page-cache-background-writers.md`).
  - The language's rows are not listed by File Manager, the file pickers and "Where used".
  - Tests: the fit (70% floor, the mark); a post without the right refused; the picture of another file or language refused; the states after a draw.
  - Pictures: the list in each state, a drawn picture beside its source.

- PT4b (M) after PT4: the same for a photo with text layers (a save row over a base row): the browser sends the overlay, the server draws it on the base as a save does.

## Where are the later steps?
- PT5 to PT7 and which steps wait for each other: `plans/picture-text-translation-steps-2.md`.
