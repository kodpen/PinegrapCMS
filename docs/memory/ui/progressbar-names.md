---
name: progressbar-names
description: Names of panel progress bars (role="progressbar"): the sentence goes on the bar as aria-label, the hover title is only for the mouse; which Workspace bars are named, which are not yet
---

## How is a progress bar named?
- The element with `role="progressbar"` carries its sentence as `aria-label`; without it a screen reader hears "progress bar, 60" with nothing saying what is 60% (axe `aria-progressbar-name`). A `title` on the wrapper names nothing and stays only for the mouse.

## Which bars are named?
- The Workspace task card in channel messages (`ws_task_card_html()` in `includes/workspace/tasks.php`): "{var:1} of {var:2} items done".
- The channel board card (`assets/js/workspace_board_channel.js`): `t('progress_text')` ("{var:1} of {var:2} items · {var:3}%").
- Pinned by `tests/progressbar_names_test.php`.

## Which are not yet?
- `progressBar()` in `workspace.js` and `shareBar()` in `workspace_task_time.js` (the `workspace.min.js` bundle), and the bars of `includes/fn/output.php`: same pattern, same fix.
