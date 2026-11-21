---
name: settings-picker-field
description: The picker field inside Site Settings cards: one 32px line, a 20px thumb, a quiet Browse icon at the end, and placeholders that say what to choose
module: picker
---

## How does a picker look in a settings card?
- Architect (2026-11-21, agent channel request 2372): inside `.pg-settings-body` and `#pg_settings_sections` a `pg_picker_field()` row is one 32px field, like a plain `.form-control` beside it.
- The row (`.pg-pfield-row`) carries the field's ground, line, 9px radius and shadow. The box inside has no frame of its own.
- On one line: a 20px thumb (radius 5px), the name, the muted meta cut with an ellipsis, Clear (24px), the caret.
- Browse is a quiet 32px icon button at the field's end, behind a hairline (`.pg-pfield-browse`). Its word is hidden; its `aria-label` ("Browse…: <title>") names it. Its `::after` stretches the touch area over the row's border.
- Focus: the row takes the field's focus ring while the box has focus; Browse has its own 2px outline. Tab order is the markup's: field, Clear, Browse.
- Warning and error marks colour the row's border (`is-warning`, `is-danger`, `is-error`).
- Style: the settings picker block of `backend.src.css`, just before the field's phone search.

## What stays as it was?
- Outside Site Settings (Add/Edit Short Link, the File Manager's short link window) the field keeps its look.
- The markup and the signature of `pg_picker_field()` did not change.
- A picker cell is not one of the two-column rows: name over the field, help under it, capped by its `pg-f-*` class.

## What does an empty picker say?
- What to choose, through the `placeholder` option. Before, it read "Type to search…", which says how, not what.
- Site Icon: "Choose a site icon". Application Icon, Default Share Image and Organization Logo: "Choose a picture" (`$picture_title` in `prep.php`). Fediverse avatar: "Choose a picture".
- Page fields: "Choose a page" (`ecommerce_reward_program_email_page_id`, `ecommerce_retrieve_order_next_page_id`, `membership_expiration_warning_email_page_id`, `ecommerce_kvkk_page_id`).
- A new picker in a settings card is given a placeholder that names the thing.

Related: docs/memory/ui/picker-field.md, docs/memory/ui/settings-card-shared-style.md
