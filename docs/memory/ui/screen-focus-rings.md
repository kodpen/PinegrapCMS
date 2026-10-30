---
name: screen-focus-rings
description: Focus rings drawn by a screen's own styles use the 2px --pg-primary-ink outline, inset inside clipping boxes; which screens were moved and which still wait
---

## Which colour does a screen's own ring use?
- `--pg-primary-ink` (2px outline), like the global ring (`panel-focus-ring.md`), never `--pg-primary` (`#64b5f6`, about 2.2:1 on white) or the 35% `--pg-focus-ring-color` alone.
- Inside a box that clips (a scroll box, a card) the outline is inset: `outline-offset: -2px`.

## Which screens were moved, and why?
- #3435: the API application rows (`api_settings.php`; the row had `outline: 0` in the unlayered page style and showed only the hover tint, `.api-scroll` clipped the global halo), the dashboard card lists (`welcome.php`, `dashboard-scroll-regions.md`), the guided tour's text and buttons (`pg_tour.css`; on Next the light ring matched the button fill) and the File Manager tree and list-heading rings (`view_folders.php`).
- `tests/faint_focus_rings_test.php` pins them.

## Which still use the light accent?
- `.pg-sort-btn` and `td[data-pg-edit]` in `assets/js/backend.src.js`, and the chat launcher in `chat.php`.
