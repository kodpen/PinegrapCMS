---
name: designer-screen-messages
description: The visual designer screen shows liveform errors, warnings and notices as its own toasts, never as the panel's alert boxes (#4892)
---

## Where do the screen's messages go?
- `pg_designer_screen_render()` prints no `output_errors()`, `output_notices()` or `get_warnings()`. It hands `pg_designer_screen_messages($liveform->get_errors(), $liveform->get_warning_messages(), $liveform->get_notices())` to the editor as `sdDesign.messages` (list of `{type, message}`: error, warning, success, in that order).
- `style_designer.js` keeps them in `_sdServerMessages` at `init()` and shows them with `sdToast()` when the curtain comes down (`_sdPreloaderDone()`), so a notice's timer does not run out behind the curtain. Errors and warnings stay until closed; notices hide after 9 s.
- Messages are markup already escaped by whoever called `add_error()`/`add_warning()`/`add_notice()`; the toast sets them as `innerHTML`, as the alert boxes printed them.

## Why?
- The editor is a full-height application screen: an alert box above `style_designer_form` pushed the toolbar, canvas and side panels down by its own height and cut the bottom off (seen with the classic style's "The style opened in the visual designer" notice, #4892).
- `edit_system_style.php` still calls `remove_form()` after rendering, so the messages show once.

## Tests?
- `tests/designer_screen_messages_test.php` (order, types, empty messages skipped). The toasts were not run in a browser.
