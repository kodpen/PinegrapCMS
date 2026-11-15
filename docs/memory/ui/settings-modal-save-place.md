---
name: settings-modal-save-place
description: After a save the Site Settings dialog keeps the form's scroll, opens the drawer the save came from again and says "saved" in one toast; when the drawer stays shut (#9751)
---

## What does the dialog keep after a save?
- The pane is still read again (`settings-modal-endpoint.md`), but `send()` notes the form's scroll and the open card drawer (`spot()`), `paint()` puts the scroll back and `reopen()` opens that drawer again with its own scroll, focus returning to its opener. Built by #9751.
- Before, `paint()` emptied the drawers and set the scroll to 0: the agent's drawer closed and the pane jumped to its top. Owner (2026-11-15): `workflow/decisions-made-panel-9.md`.

## When does the drawer stay shut?
- After a button named `..._delete`, `..._remove` or `..._archive`, and from an add drawer (id ending `-0`, `-add`, `-new`, or `pgset-ws-github-link`): the stored record has a drawer of its own. Rule: `pgSettingsKeepDrawer()` in `backend.src.js`, test `tests/settings_dialog_keep_place_test.php`. A new add drawer follows the id pattern.

## How is "saved" said?
- The panel's own toast, `pgToast()` with its `host` option, in `#pg_settings_modal_saved` (`modal.php`), placed by `confirmSave()` just under the head of the open drawer or of the pane, never over a drawer's Save at its foot (on a phone it would cover it). A new save empties the box first. The architect's look (channel message 5007): a toast in an open dialog stands at its top, so under the drawer's title; no second recipe at the drawer's foot.
- Text: the card's own sentence ("The agent was saved."); the endpoint's general one ("The Site Settings have been saved.", always last) only when the card said nothing. 5 s; a text over 80 characters (an address to note) stays until closed.
- A save with warnings, a refused save and an error keep the old way: the notice at the top of the pane, scroll 0, drawer shut.
