---
name: translations-screen-modern
description: Plan (owner 2026-11-27): the Translations screen redrawn (tabs, one rail, filter tiles, a new row, a selection bar, Activity, Glossary) and made fit for automation; steps TS1 to TS8
priority: 5
---

## What is asked?
- Owner (2026-11-27, agent channel message 15186): "çeviri ekranı hem modernleştirelim hemde baştan çiz kurgusunu kaliteli hale getirelim. otomasyona yatkın olsun."
- Drawn: `ui/translations-screen-drawing.md`; its sheet wins over these words. What today's screen does: `ui/translations-screen-today.md`; nothing a user can do there is lost.

## What did the architect settle? (2026-11-27; not the owner's words)
- No table, no column, no new stored value. A label of today keeps its words.
- No engine select that saves on its pick: the dialog "Automation for <language>" saves the engine, the fallback engine and "Translate on save" with its button, into the columns Site Settings › Languages and Translation writes; that card stays.
- "Update …" is split: "Scan for new texts" scans, "Translate pending" scans and sends.
- A save, a mark and a bulk action change the list in place.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it: one screen, one file)
- TS1 (M) The frame: the head (language button with shares, tabs "Texts", "Activity", "Glossary", "Translate pending", "Scan for new texts", "More"), the one rail with "Find a page", the filter tiles, the tool line. "Activity" and "Glossary" hold today's two cards until TS4 and TS5.
- TS2 (M) The row: the grid, the box that grows, the pills with "Suspicious" on its own line, "Details" ("Used in:", "Earlier wording:", the reason, "Translate again", "Keep as in the source and approve"), the refusal under the box, the keys.
- TS3 (S) Selection: a tick on every row, the bar ("Approve", "Translate again", "Keep as in the source and approve", "Clear"), the "Translate again" dialog. A reviewed text is replaced only with the dialog's tick (`pg_tr_reviewed_kept()`).
- TS4 (M) Automation's line and dialog, the progress card, and "Activity": the table of every update, the drawer with the failed texts and their reasons (`translation_job_items.error`), "Try again with the failed ones", "Stop".
- TS5 (S) "Glossary" as a table with its search; the dialog as today.
- TS6 (M) What the settings promise:
  - the fallback engine takes the texts an update's engine failed on (stored today, used nowhere);
  - "Translate on save" also for a saved product, product group, offer and form (today a page, a form record and a picture);
  - a language not served is offered with "Not served", to be translated before it goes live. If the store or a job needs "Served", the step asks the architect.
- TS7 (M) The API, by `api/` rules and `check_api_schema.php`, each write with a dry run:
  - an update started (language, scope; the language's engine; refused for the browser engines and "By hand");
  - the updates and an update's failed texts read with `translations:read`;
  - a glossary term deleted; the groups Images and Cookie window in the strings listing;
  - the event `translation.update_finished` through `pg_announce()`. If an event needs a stored value, the step asks first.
- TS8 (S) Close-out: 1024 px and the phone against the sheet, `ui/translations-screen-*.md` as one set with an overview, this file deleted.

## What does every step keep?
- The look and `ui/screen-composition-rules.md`; `lang` and `dir` on a text as `translations-screen-text-language.md` says; the leave question for unsaved rows.
- The owner's decisions: "Cookie window" is translated here only (`decisions-made-product.md`), the group "Images" (`decisions-made-product-70.md`), a page's search verdict (`decisions-made-product-38.md`), Lorem ipsum never listed.
- The visual editor's "Translate" section uses the same actions: none is renamed.

## What is left out?
- The picture draw queue and the Languages and Translation card: unchanged.
- Machine translations approved by themselves, a translator's role: the owner's to ask for.
