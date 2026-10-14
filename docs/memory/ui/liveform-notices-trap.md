---
name: liveform-notices-trap
description: Pitfall: get_warnings() and output_notices() read from session without clearing; screens must call remove_form() and output_notices().
---

## Why does a warning reappear every time a screen opens?
- `get_warnings()` and `output_notices()` READ from the session and do NOT delete. A warning shown once reappears on every entry to that screen.
- After rendering, the screen must call `remove_form()` (see `import_zip.php`, `marketplace_settings.php`, `api_settings.php`).

## Why does an add_notice() message never show?
- Every screen that calls `add_notice()` must also call `output_notices()`; otherwise the message accumulates in the session and is never displayed.
