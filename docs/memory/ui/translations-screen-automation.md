---
name: translations-screen-automation
description: Translations screen automation (TS4): the automation line under the head, the dialog "Automation for <language>" that saves the engine, fallback and Translate on save, and the progress card
module: translate
---

## What does the automation line show?
- `#pg_tr_automation`, a `pg-card` of 46 px, radius 14, under the head: "Automation", then gray pills "Engine", "Fallback" (none with "By hand") and "Translate on save" On/Off (only once the `auto_update` column exists), then "Change".
- Engine not ready: the line turns amber, the Engine pill amber, and the engine's own reason follows (`pg_tr_engines()[…]['reason']`, which says where it is put right).
- "By hand": a pencil icon and the sentence 'This language is translated by hand: "Scan for new texts" scans the pages and lists the pending texts here.'
- A browser engine: as ready, its note under the line as before (`.pg-tr-chrome-note`, with the browser pages; the script adds the model's fallback line there).

## What does the dialog save?
- "Change" opens `#pg_tr_automation_modal` "Automation for <language>": "Translation engine" (a not-ready one says "(not ready)"), the engine's note under it, "Fallback engine" ("-None-", never "By hand" or the engine itself; what it takes: `core/content-translation-fallback-engine.md`), "Translate on save" as a switch row, the five-minute line (only for a server or queue engine), "Languages and Translation", Cancel, Save.
- The switch's line: "A saved page, form, form record, product, product group, offer or picture is sent to the engine by itself."
- Nothing is saved before "Save". The `automation` action checks the values with `pg_tr_automation_values()` (`includes/translate/activity.php`), the rules of the settings card (`includes/settings/languages.save.php`), and writes `site_languages.engine`, `fallback_engine` and `auto_update` in one UPDATE; the screen reloads with a message.
- With "By hand" the switch is off and disabled with 'Off with "By hand": there is no engine to send the texts to.', and `auto_update` is written 0.
- Before TS4 (#12193) a select in a card line saved the engine on its pick (`action=engine`, now gone) and the fallback and Translate on save were set only on the settings card, which still writes the same columns.

## What is the progress card?
- `#pg_tr_progress` takes the line's place while an update runs (`showProgress()` hides the line, `hideProgress()` brings it back): spinner, 'Translating "<scope>" with <engine>…' (`titleJob()`), "{n} of {m} translated", a 4 px bar, "Stop", and "Open in Activity", which picks the tab and opens that update's drawer.
- While the scope is only scanned the title is empty and "Open in Activity" hidden.

## Where is it tested?
- `tests/translations_activity_test.php` (`pg_tr_automation_values()`), `tests/select_commit_test.php` (no save on a pick).

Related: docs/memory/ui/translations-screen-activity.md, docs/memory/ui/translations-screen-frame.md, docs/memory/ui/translations-screen-drawing.md
