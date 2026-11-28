---
name: translations-screen-drawing
description: The Translations screen's redraw (2026-11-27): where its sheet is, what each board A to J shows, the measures, the keys, and the drawn choices that bind the steps of the plan
---

## Where is it?
- `ui/translations-screen-drawing/translations.html`: boards A to J on the look's tokens; `?theme=dark` for the dark theme, `?f=<letter>` for one board. `translations-light.jpg` and `translations-dark.jpg` are the whole sheet.
- Built in steps TS1 to TS8 (rules: `translations-screen-overview.md`). The sheet wins over the plan's words; what the sheet leaves open is the step's own call, in the look.

## What does each board show?
- A: today's screen, its five faults numbered (`translations-screen-today.md`).
- B: the screen as it opens at 1440 px. Head on one line: language button, the tabs (the look's segmented control), "Translate pending" with the scope's pending count, "Scan for new texts", "More". Under it the automation line, the rail, six filter tiles, the tool line, 100 texts a page.
- C: the row in nine states and its state column enlarged.
- D: rows ticked, the selection bar and its three dialogs.
- E: the language menu, "More", the automation line (ready, not ready, "By hand"), the progress card in its place, the dialog "Automation for English".
- F: a page chosen (the index note, "Why?" filtering in place) and the Images group.
- G: "Activity": the table of updates, the drawer (finished with failed texts, running), the empty state.
- H: "Glossary": the table with its search and today's term dialog.
- I: no target language, never scanned, not served, 1024 px, the 390 px phone.
- J: sizes, tones and keys.

## Which measures?
- Rail 300 px (240 at 1024 px), 20 px from the main column; a rail row 42 px, radius 9, a 3 px accent edge when chosen.
- Six tiles of equal width, 10 px apart, 62 px high (76 with a two-line label), radius 12.
- A row: 14 px above and below, 18 px sides; tick column 28 px, state column 176 px, 16 px between columns; source and translation share the rest in halves; the box 39 px or more, radius 10.
- Selection bar 48 px, automation line 46 px, both radius 14; the drawer 34rem.

## Which tones?
- "Pending" gray, "Machine" amber, "Reviewed" teal; "Suspicious" rose on its own line, never in the state's place. An unsaved row's "Save" is accent; a refused save's reason is rose under the box.

## Which keys?
- Ctrl+Enter saves and moves to the next row's box; Alt+Down and Alt+Up the next and the previous box; "/" puts the cursor in "Search in the texts"; No key for "save and mark as reviewed": "Save" stores the text as reviewed.

## Which drawn choices bind?
- The rail is one card and stays while the list scrolls.
- "Activity"'s count is the updates still open plus the finished ones that left failed texts. Its table holds 20 a page, newest first.
- "Try again with the failed ones" sends to the same engine; its caret offers the fallback engine by name.
- With "By hand" the automation line has no fallback chip, and the dialog's "Translate on save" is off with the reason "Off with "By hand": there is no engine to send the texts to."
- A pending row has no eye button. "Draw the waiting pictures" sits in the Images group's tool line.
- A page's index note stands under the tool line.
- Phone: language button and "More" on the first line, the tabs on the second, "Translate pending" full width; "Scan for new texts" is the first item of "More"; the rail is a button that opens its list in a bottom sheet with 44 px rows; tiles scroll sideways; a row stacks; the selection bar shows "Approve" and "Translate again" as icons, the rest under "More".

## What is not drawn?
- The automation line for a browser engine: as "ready", with the sentence today's screen has for it.
- The drawer's error sentence and the failed texts' reasons are stand-ins: the stored text is shown.
- The sample figures are made up.

Related: docs/memory/ui/look-translations-screen.md, docs/memory/ui/translations-screen-editor-rows.md
