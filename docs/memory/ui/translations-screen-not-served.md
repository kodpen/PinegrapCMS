---
name: translations-screen-not-served
description: Translations screen (TS6): a target language with "Served" off is listed with a "Not served" pill and a notice, so it is translated before it goes live; what the store and jobs ask of it
module: translate
---

## Which languages does the screen offer?
- Every target language (`pg_tr_languages(false)`), "Served" on or off. With no language in the address the first served one opens; a site with only languages not served opens the first of them.
- The language menu shows "Not served" as a gray pill after the share for each language not served.

## What does a language not served show?
- Under the head, above the automation line, a quiet notice (`#pg_tr_not_served`, `alert theme-secondary`, eye-slash): "This language is not served yet: visitors do not see it." with a link to Languages and Translation.
- "Open the page" is not drawn: the address would not be served.
- Everything else works as for a served language: scan, update, rows, Activity, Glossary, CSV.

## Did the store or the jobs need "Served"?
- No: `translations_action.php`, `pg_tr_update()`, the jobs and the CSV export check `pg_tr_language_row()`, which reads every language. Only the screen's list skipped them. So the step asked the architect nothing.
- "Translate on save" still works only for served languages (`pg_tr_auto_languages()`), so a language switched off spends no engine credit by itself.

Related: docs/memory/ui/translations-screen-frame.md, docs/memory/ui/translations-screen-drawing.md
