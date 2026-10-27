---
name: ai-write-marks-designer
description: The AI mark on the visual designer's page settings (Page Title, Meta Description): page aiText/ai_fields in style_designer.js, pg_ai_fields_page_save() on the save
---

## What does the designer show?
- Page settings (Page Title, Meta Description; PR #2453): the same marker, hidden, after each label (`pg_designer_setting()` option `marker`), shown for the open tab. `style_designer.js` keeps per page `aiText` (column => text the mark was put on, from the server's `ai_fields` list) and derives `ai_fields`, sent with the page's settings (`_PG_PAGE_FIELDS`, so a mark change makes the page unsaved). Typing, an undo, the x and a duplicated tab follow the same rules as the panel script.

## What does the save write?
- Save: `pg_designer_save_page()` writes `page.ai_fields` through `pg_ai_fields_page_save()`: the list when the page sends one, else (API, page assistant) the stored marks whose text is unchanged.

## How was it checked?
- `tests/ai_fields_test.php` (the save function, the screen and the script agree), `node --check`. Not run in a browser.
