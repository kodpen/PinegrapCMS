---
name: translations-screen-row
description: Translations screen row (TS2): the grid, the box, the state column with "Suspicious" on its own line, "Details" read on opening, the refusal under the box, Ctrl+Enter and Alt+Up/Down
---

## How is a row laid out?
- One CSS grid on `.pg-tr-row` in `translations.php`: tick 28 px, source and translation in halves, state column 176 px, 16 px between, 14 px above and below. At 767 px and under it stacks: tick and source, the box, then the state on one line with the actions at its end.
- The source is text (`.pg-tr-source`), four lines at most until "Show all" (`.pg-tr-clamp`; the button shows only when the text overflows). Under it one line of marks: the owner pill of "Why?", the picture chip, "SEO", "HTML", the hash's first 8 characters.
- The box is `textarea.pg-input.pg-tr-target`, 39 px or more, no resize handle, `field-sizing: content`; `pg_tr_screen_target_rows()` gives 1 to 12 rows at 50 characters a row for browsers without it.

## What does the state column hold?
- Top to bottom: the state pill (Pending gray, Machine amber, Reviewed teal), the engine's name (`.pg-tr-engine`, hidden when empty), "Suspicious" as a rose pill on its own line (`.pg-tr-suspicious`), then "Save", the eye (not on a pending row) and the "Details" caret.
- The "Suspicious" pill's title is the reason (`pg_tr_screen_suspicious_reason()`): "The translation is the same as the source text." or "The length of the translation is out of proportion to the source text.". Only the flag is stored, so the reason is read again from the two texts.

## What is in "Details"?
- The caret (`.pg-tr-details-toggle`, `aria-expanded`, `aria-controls`) opens `.pg-tr-details` under the source and the translation. It is read on its first opening from the action `details` of `translations_action.php` (managers only; a user limited to a page has `node`, `save`, `status`).
- "Used in:": `pg_tr_screen_details()` reads the text's `translation_uses`, `pg_tr_screen_use_lines()` gives one line a place (a page's title and description folded into the page; pages first, then shared components, menus, the catalog, the forms, the pictures, the cookie window), 12 at most and "and {var} more". A page links to the screen scoped to it, the catalog, forms, pictures and cookie window to their group; a shared component and a menu are plain text.
- "Earlier wording:": the wording the text replaced (`prev_string_id` of its first use with one) and that wording's translation in the language.
- The suspicious reason in rose, then "Keep as in the source and approve" for a row not reviewed (the `accept_source` action with its one id; the row turns Reviewed in place).
- "Translate again" in Details (a row with a translation, an engine ready) opens the selection's "Translate again" dialog for that row (`translations-screen-selection.md`).
- A change of state (save, eye, keep) drops the loaded Details; an open one is read again.

## What happens when a save is refused?
- The server's sentence stands in rose under the box (`.pg-tr-why`, `role="alert"`, the box's `aria-describedby` names it), the box gets `aria-invalid="true"` (the field's rose ring), the typing stays and "Save" keeps its accent. A request that got no answer (`error.transient`) is a toast instead. The next stored save takes it away.

## Which keys?
- Ctrl+Enter (Cmd+Enter) in a box saves it and, once stored, moves the cursor to the next row's box. Alt+Down and Alt+Up go to the next and the previous box. "/" is the frame's (`translations-screen-frame.md`).

## Where is it tested?
- `tests/translations_screen_test.php` (the reason, the "Used in:" lines), `tests/translations_screen_bugs_test.php` (box rows, source checks of the refusal, keys and Details).

Related: docs/memory/ui/translations-screen-drawing.md, docs/memory/ui/translations-screen-frame.md, docs/memory/ui/translations-screen-editor-rows.md
