---
name: app-screen-messages
description: Application-like screens (File Manager, visual designer) show liveform messages as their own toasts; a send_to redirect leaves its notice on the screen it opens
module: core
---

## Where do the File Manager's messages go?
- `view_folders.php` prints no `output_errors()`, `get_warnings()` or `output_notices()`. It merges the three forms it reads (`view_folder_and_files`, `view_pages`, `view_folders`) through `pg_designer_screen_messages()`, sorts errors, warnings, notices, drops repeats and hands the list to the script as `SERVER_MESSAGES`.
- The script shows each with `toast(message, ok, null, { html, tone, sticky })`: errors (danger) and warnings (warning tone) stay until closed; notices (success) hide after 9 s. The message is markup already escaped by whoever added it, set as `innerHTML` as the alert box printed it.
- Owner standard (2026-11-02, `workflow/decisions-made-panel-2.md`): an application-like screen never prints the panel's alert boxes. Same pattern as the designer (`designer/designer-screen-messages.md`).

## When are they shown?
- Inside `pgUI.ready()`, after Bootstrap's script has run. Bootstrap 6 is a deferred module (`pg_bs6_bridge.js`), so while the inline explorer script runs `pgUI.toast.get()` is null; `toast()` then waits for `pgUI.ready()` itself instead of calling `show()` on null.
- Symptom fixed: every visit with a pending message (Edit File Delete or Save & Return, Duplicate Folder, Import ZIP) threw in `toast()`, the script stopped before `load()` and the screen stayed on "Loading..." for good.

## Why?
- #5216: a one-line box pushed `#explorer_app` down by 98 px; the status bar and the list's bottom fell below the window and the page got its own scroll bar.

## Where does a send_to redirect leave its notice?
- `pg_redirect_notice_form($location)` (`includes/fn/core.php`, pure) gives the form named after the script the redirect opens (`/pinegrap/welcome.php` -> `welcome`), or `''` for a target that is no script (a site page): no notice is left then.
- `edit_file.php` (Save & Return, Delete) and `duplicate_folder.php` use it. Before, they always wrote to `view_folders` and the notice surfaced whenever the File Manager was next opened, hours later too (#5216).
- `add_file.php`, `create_file.php` and `optimize.php` still write to `view_folders`: they land there (or, in `optimize.php`, pick the form per target themselves).

## Tests?
- `tests/explorer_server_messages_test.php`.
