---
name: liveform-notices-trap
description: Pitfalls: notices and warnings stay in the session until remove_form(), screens must print them, and a request run twice stacks the same notice.
---

## Why does a warning reappear every time a screen opens?
- `get_warnings()` and `output_notices()` READ from the session and do NOT delete. A warning shown once reappears on every entry to that screen.
- After rendering, the screen must call `remove_form()` (see `import_zip.php`, `marketplace_settings.php`, `api_settings.php`).

## Does remove_form('other_form') clear another screen's form?
- No. `remove_form()` (and `remove()`) take no argument: whatever is passed is ignored and the form the object was made for is cleared. To clear another screen's form, make an object for it: `(new liveform('other_form'))->remove_form()`.
- `edit_javascript.php` and `editor_edit_file.php` called `remove_form('edit_design_file')` on their own object, which read as clearing the design file screen; they always cleared their own form (checked in the sandbox). The calls are written without the argument since PR #474.

## Why does an add_notice() message never show?
- Every screen that calls `add_notice()` must also call `output_notices()`; otherwise the message accumulates in the session and is never displayed.

## Why can the same notice show twice?
- A notice waits in the session until a screen prints it and removes the form. When the request that adds it runs twice before that (two tabs, a request the browser or a tool repeats), the list holds the sentence twice and the screen prints "Notices:" with it twice.
- A notice that describes a state rather than an action is added only when it is not already waiting: `if (!in_array($notice, $liveform->get_notices(), true))`. The theme designer's redirect to the CSS editor for a theme without designer settings does this since PR #NNN; the custom form's "off" notice in `includes/fn/widgets.php` did already.
- The class itself does not drop repeated notices: two copies made by two requests are two facts. Errors are different: `output_errors()` and `get_errors()` print a repeated sentence once.
