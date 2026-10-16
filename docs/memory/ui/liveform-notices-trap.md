---
name: liveform-notices-trap
description: Pitfall: get_warnings() and output_notices() read from session without clearing; screens must call remove_form() and output_notices().
---

## Why does a warning reappear every time a screen opens?
- `get_warnings()` and `output_notices()` READ from the session and do NOT delete. A warning shown once reappears on every entry to that screen.
- After rendering, the screen must call `remove_form()` (see `import_zip.php`, `marketplace_settings.php`, `api_settings.php`).

## Does remove_form('other_form') clear another screen's form?
- No. `remove_form()` (and `remove()`) take no argument: whatever is passed is ignored and the form the object was made for is cleared. To clear another screen's form, make an object for it: `(new liveform('other_form'))->remove_form()`.
- `edit_javascript.php` and `editor_edit_file.php` called `remove_form('edit_design_file')` on their own object, which read as clearing the design file screen; they always cleared their own form (checked in the sandbox). The calls are written without the argument since PR #PRNUM.

## Why does an add_notice() message never show?
- Every screen that calls `add_notice()` must also call `output_notices()`; otherwise the message accumulates in the session and is never displayed.
