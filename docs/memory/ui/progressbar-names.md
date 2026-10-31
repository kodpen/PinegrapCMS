---
name: progressbar-names
description: Names of panel progress bars (role="progressbar"): the sentence goes on the bar as aria-label, the hover title is only for the mouse; which Workspace bars are named, which are not yet
---

## How is a progress bar named?
- The element with `role="progressbar"` carries its sentence as `aria-label`; without it a screen reader hears "progress bar, 60" with nothing saying what is 60% (axe `aria-progressbar-name`). A `title` on the wrapper names nothing and stays only for the mouse.

## Which bars are named?
- The Workspace task card in channel messages (`ws_task_card_html()` in `includes/workspace/tasks.php`): "{var:1} of {var:2} items done".
- The channel board card (`assets/js/workspace_board_channel.js`): `t('progress_text')` ("{var:1} of {var:2} items · {var:3}%").
- `progressBar()` in `workspace.js` (task drawer checklist, task rows, `/tasks` list chips): `t('progress_text')` from the progress's done/total, or the label its caller passes (the notes import reading and upload bars). A bar with neither stays unnamed.
- `shareBar()` in `workspace_task_time.js` (task drawer time bar): the "{var:1} spent of {var:2}" sentence the drawer head prints (`time.compare`).
- The Convert to text meter under voice messages (`pg_voice_text.js`): the status text (`ui/voice-text-keyboard.md`).
- Pinned by `tests/progressbar_names_test.php`.

## Which are not yet?
- The bars of `includes/fn/output.php`: same pattern, same fix.
