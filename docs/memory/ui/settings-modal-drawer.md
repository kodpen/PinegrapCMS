---
name: settings-modal-drawer
description: A settings card's drawer: a panel that slides in from the Site Settings dialog's right edge over the pane, never a second dialog; markup, hand-over, Escape, focus, page path (PR #8658).
---

## What is a card's drawer?
- A panel inside the Site Settings `<dialog>`, sliding in from its right edge over the pane: a head (title, Close) and a body that scrolls on its own. Never a second dialog in the top layer. First user: Cron Jobs' setup examples (Updates and maintenance, `includes/settings/maintenance.php`), PR #8658.
- Why: a second dialog over the settings could not scroll, its text ran outside it, and a second layer risks leaving the settings unsaved. The owner's decision is in `workflow/decisions-made-panel-3.md` (2026-11-11).

## How does a card hand its drawer over?
- `$pg_settings_modals[] = pg_settings_drawer($id, $title_html, $body_html, !empty($pg_settings_in_dialog));` (`includes/settings/drawer.php`); what else a card may hand over: `ui/settings-modal-card-extras.md`. `$title_html` and `$body_html` are markup; the id is escaped.
- The trigger is `data-bs-toggle="drawer" data-bs-target="#<id>" aria-controls="<id>"`. In the dialog `pg_settings_own_dialog_triggers()` renames it to `data-pgdrawer="open"`: Bootstrap's data-api would open its drawer in the top layer and hide the settings.
- `$pg_settings_in_dialog` is set by the caller: `true` in `pg_settings_fragment()` (`fragment.php`), `false` in `screen.php`. Both entry points include `drawer.php` (gate `PG_SETTINGS_ENTRY`).

## What does the page path draw?
- `settings_<key>.php` (screen.php) prints Bootstrap's own `<dialog class="drawer drawer-end pg-set-drawer">` with the same body; the data-api opens it there, as there is no outer dialog. Width `--pg-drawer-width: min(42rem, 100vw)`.

## Where does the dialog put it?
- `paint()` (`backend.src.js`) fills `#pg_settings_modal_extras` from `modals` (scripts run there), then moves every `.pg-sm-drawer` into `#pg_settings_modal_drawers`, inside the dialog after the `.pg-sm-main` section and outside the form (`modal.php`), so a drawer may carry a form of its own. `#pg_settings_modal_dscrim` is the scrim under it.
- `clearExtras()` (pane change, dialog close) closes it without giving focus back, releases its editors and fields, and empties the host and the extras box.

## How do Escape, Close and focus work?
- `openCardDrawer()`: shuts the phone list, adds `is-open`, puts `is-drawer-open` on the dialog, makes the list, its scrim and `.pg-sm-main` `inert` (Tab stays in the drawer) and focuses the drawer.
- Escape (the dialog's capture-phase keydown) closes the card drawer first, then the phone list, then the dialog; an Escape from another open dialog in front is left to that dialog. Close (`data-pgdrawer-close`) and a click on the scrim close it too.
- `closeCardDrawer()` gives the focus back to the button that opened it. Nothing in the drawer code touches the pane's values, `baseline` or the unsaved mark.

## How does it look?
- CSS in `backend.src.css`, after the settings modal: `.pg-sm-drawer` is `position: absolute; right: 0`, `width: min(42rem, 100%)`, the whole width below 576px; `.pg-sm-drawer-body` has `min-height: 0; overflow-y: auto`. Visibility is untimed on the way in (focus lands at once), delayed on the way out.
- The owner looked at it on dev (SD1b, #8677) and asked for no change. No card of the Site Settings dialog opens a second dialog any more (SD2); the Workspace cards' dialogs move into drawers with their steps (`plans/settings-workspace-move-steps.md`).
