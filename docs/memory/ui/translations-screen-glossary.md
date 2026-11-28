---
name: translations-screen-glossary
description: Translations screen "Glossary" tab (TS5): the table of terms, "Search the glossary", the filled "Add a term", the remove buttons on one form outside the table; the term dialog unchanged
---

## What does the tab show?
- Above the card: "Search the glossary" (pill search, 360 px, `#pg_tr_find_term`) and "Add a term" (`pg-button-fill`, at the right). The search is drawn only while the language has a term.
- The card holds one table (`.pg-tr-terms`), one row a term (`tr.pg-tr-term`): Term (with the gray "Aa" pill when matched with exact case), Translation to use (or the gray pill "Keep as written"), Language ("Every language" or the language's name), Note for the translator, then Edit and Remove icon buttons.
- No term: the empty state with today's sentence "No term yet. A term with the translation to use, …".
- Before TS5 (#12210) the tab held the old left-column card: a narrow list with "keep as written" and "all languages" pills and a quiet small "Add a term".

## How does the search work?
- In the browser (`translations.js`), as typed: a row stays when its term, translation or note contains the words (case ignored); none left hides the table and shows "No term matches the search." (`#pg_tr_term_none`). Nothing is posted.

## How is a term removed?
- No form sits in a table row (`list-and-table-screens.md`): each Remove is `type="submit" form="pg_tr_glossary_delete" name="id" value="<id>"` on the one hidden form after the table, which posts `glossary_delete` with `tab=glossary` and the view fields. Its `submit` listener asks today's "Remove this term from the glossary?" (`data-confirm`).

## What stayed?
- The term dialog `#pg_tr_glossary_modal`: its fields, labels, the kept term hiding the translation, the refused save reopening it, the duplicate check (`translations-screen-editor-rows.md`). Edit reads the row's `data-*` values as before.
- The plan's drawing (board H) switches the translation field off instead of hiding it; the plan says "the dialog as today", so it still hides.

Related: docs/memory/ui/translations-screen-frame.md, docs/memory/ui/translations-screen-drawing.md, docs/memory/ui/look-translations-screen.md
