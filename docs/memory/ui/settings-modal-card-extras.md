---
name: settings-modal-card-extras
description: What a Site Settings card hands over beside its markup: its drawer or a hidden Connect form, never a dialog; the extras box and the CSS left from the old dialog path
---

## What may a card put in `$pg_settings_modals[]`?
- Its drawer (`pg_settings_drawer()`, `ui/settings-modal-drawer.md`) or a hidden Connect form: `<form method="post" action="connect.php" id="pgset_…" class="disable_shortcut d-none">`, which a button in the card submits by its `form` attribute, since forms cannot be nested.
- The Connect forms: Merchant Center (`pgset_merchant_connect`, `commerce_merchant.php`), Google Analytics (`pgset_ga_connect`, `privacy_analytics.php`), the backup cloud (`pgset_backup_connect_<type>`, `maintenance.php`).
- Never a dialog: a second layer over the settings could leave them unsaved (`workflow/decisions-made-panel-3.md`). Anything a card shows beside its fields opens in its drawer. `tests/settings_modal_drawer_test.php` reads every card file and fails on `data-bs-toggle="dialog"`, a `<dialog>`, or another kind of entry.

## Why do the Connect forms stay outside the drawer?
- They show nothing and open nothing: a hidden form is not a second dialog, and a button submits it wherever it is. So SD2 left them in the extras box.

## Where do they go?
- In the dialog: `paint()` (`backend.src.js`) puts `modals` into `#pg_settings_modal_extras`, a box after the settings `<dialog>` and outside its form (`modal.php`); a drawer is then moved into `#pg_settings_modal_drawers`. `clearExtras()` (pane change, dialog close) closes and empties the drawer host and empties the box.
- On the page: `screen.php` prints them after the settings form.

## What happened to the second-dialog path?
- Gone in SD2: `pg_settings_own_dialog_triggers()` (`fragment.php`) renames only `data-bs-toggle="drawer"` to `data-pgdrawer="open"`; the `data-pgdialog="modal"` click handler and the dialog dispose in `clearExtras()` were removed, as no card used them (Cron Jobs had moved to the drawer in #8658).
- Why the drawer trigger is still renamed: Bootstrap 6's data-api binds on the document in the capture phase, so inside an open dialog it would open its drawer in the top layer and hide the settings; no listener can get ahead of it.
- Left over: the frosted `#pg_settings_modal_extras .dialog` rules in `backend.src.css` (three selectors after the settings modal) match nothing now; they go once that file is free.

Related: docs/memory/ui/settings-modal-drawer.md, docs/memory/ui/settings-card-save-rules.md
