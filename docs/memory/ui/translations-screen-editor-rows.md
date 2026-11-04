---
name: translations-screen-editor-rows
description: Translations screen (translations.php, translations.js): engine wait, row state and count sync, scoped chips, glossary duplicates and refused saves, return view, CSV guard, box size
---

## What if the browser's Translator or AI model never gets ready?
`Translator.create()` / `LanguageModel.create()` of a browser with the API but no service or language pack never settles. `guardCreate()` gives up after 30 s with no `downloadprogress` event (each event restarts the wait) and shows "The browser did not get its translator ready…". The progress line says "Preparing the browser translator…" while it waits, and "Stop" ends the wait without a question (`preparing.stop()`); nothing has been sent yet. A translator that arrives after the wait gave up is destroyed. (#5994, PR #6016)

## How does a row follow its state after a save?
`rowState()` stores the state on the row (`data-state`), adds or removes the "keep as in the source" tick box (`.pg-tr-pick`, a `.pg-tr-pick-spacer` stands in for a reviewed row) and fires `pg-tr-picks-changed`, which re-counts the selection. `countState()` moves every server-drawn count with each change of state: the chips (`.pg-tr-chip-count[data-state]`), "All pages" (`.pg-tr-all-done`), the Pages header (`.pg-tr-reviewed-total`), "Approve all" (`data-plain`/`data-flagged` on `.pg-tr-review-all-go`, read when the dialog opens; the toolbar button is always printed and hidden with `d-none` at 0) and "Clear translations" (`data-all`, `data-machine` on `.pg-tr-clear`). (#5999)

## What do the chips count?
The selected page's or group's texts (`pg_tr_language_stats($language, $owner_where)`), the whole site's otherwise. A scope with no text at all says "No text has been scanned yet", whatever the filter.

## What happens after a refused glossary save?
`glossary_save` stores the typed values in the liveform (`glossary_form` = 1) before going back; the screen prints them into the dialog with `data-reopen="1"` and the refused field `is-invalid`, and `translations.js` opens it again without the reset of its `show` handler.

## Can a glossary term be saved twice?
No. `glossary_save` looks for the same term with `pg_tr_glossary_duplicate()`: compared without case unless both rows match case exactly; a term for one language against that language's rows and the all-languages ones, an all-languages term against every row; the edited row is skipped. A repeat is refused with "The term … is already in the glossary". The table has no unique key; the check is in code. (#5996)

## Where does a glossary or import post return to?
To the view it was posted from: each form carries `$view_fields` (page_id, group, filter, q) and `translations_action.php` builds `$back` with `pg_tr_screen_view_query()`. (#5997)

## Are the CSV cells guarded?
`pg_tr_csv_export()` writes source and translation through `pg_csv_formula_guard()` (hash, format, status as they are); `pg_tr_csv_import()` reads the translation through `pg_csv_formula_unguard()`, so an unchanged export imports without a leading apostrophe. (#5995)

## How tall is a translation box, and what is it called?
`rows` comes from `pg_tr_screen_target_rows()` (one row per 70 characters of each line, 2 to 12); `field-sizing: content` with `max-height: 24rem` grows it while typing where supported. The box and the Save button are described by the source text (`aria-describedby="pg_tr_source_<id>"`). (#5998)

## Which Turkish words does the screen use?
A page is "açıldı / gösterilen" (never "çizildi"), the setting is "üslup notu", the menu says "CSV içe aktar" / "CSV dışa aktar". (#6000)
