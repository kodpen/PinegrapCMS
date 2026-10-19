---
name: workspace-quick-edit
description: A record's fields edited by a person from the record drawer with no assistant (quick_edit.php): kinds offered, the two actions, checks reused from changes.php, stale check, history row, log, decision
---

## What is the quick edit?
- `includes/workspace/quick_edit.php` (PR #524): a person changes a record's fields right away from the record drawer (`openRecord()` in workspace.js) or the chip menu, without proposing to an assistant. It reuses the catalogue, rights, typing, per-kind rules and writers of `includes/workspace/changes.php`, so a field is editable here exactly when an assistant could propose it, and is written the same way (announcements, marketplace queue, tag cloud included).
- Kinds: `ws_quick_edit_types()` — product, product_group, order, contact, erp_account, offer, calendar_event: the kinds with a screen of their own outside the workspace. A product's stock (`stock` kind, field `quantity`) is shown as one more field of the product and written by the stock writer, which turns tracking on. Not here: a channel's summary, tasks, notes (their own screens), a user's role (the user screen), a page's or a file's words (the file manager).

## Which actions does the screen call?
- `ws_record_fields` {type, id} → `ws_quick_edit_fields()`: name, tag, label, `fields` (each: name, kind, label, limit, values, labels, column, value in the field's shape, shown as the card prints it) and `stock` (the quantity field with `tracked`) when the viewer may set it. Refused with the reason when the person may not change the kind (`ws_change_allowed()`), the record is gone, or the kind's rules refuse it (a product in the Recycle Bin).
- `ws_record_fields_save` {type, id, fields {name: value}, from {name: value the drawer was given}, channel_id, decision} → `ws_quick_edit_save()`. Both are in the panel allowlist (`includes/panel/actions.php`) and need workspace membership like every `ws_*` action.

## How is a save checked and written?
- `ws_changes_input()` types and bounds the values, runs `ws_change_check()` and drops fields that already hold the value, so a save that changes nothing is refused with "Nothing in it differs…". Then every `from` the drawer sent back is laid against the record as it is now with `ws_quick_edit_moved()` (compared in the field's own shape, so "1250" and 1250 are the same price); a difference answers `stale: true` and nothing is written. A field the drawer does not send `from` for is not checked.
- The writers run with `ws_change_source(['kind' => 'person', 'name' => …])` and the quiet log flag: the sentences left on a record (an order's cancellation reason, a deleted offer's note) name the person; the activity log gets one line, "{kind} ({name}) was changed in the Workspace: Label: “from” → “to”, …", under the session's username.
- Each written row is kept in `ws_ai_changes` as `status = 'applied'` with `request_id = 0` and `message_id = 0` (the marker of a person's edit; assistant rows always have a request, scheduled changes keep no row), so the record's history reads the same whoever changed it. The card code never sees these rows because they hang on no message.
- With `decision` on and a channel the person may post in, a locked `decision` message "{tag} was changed. Label: “from” → “to” · …" is sent in that channel, like an applied proposal's.

## How does the drawer draw it?
- docs/memory/workspace/workspace-quick-edit-drawer.md (the "Fields" section of `openRecord()`, the chip menu item, `saValueInput()`).

## What else saves through it?
- The `/price` and `/stock` channel commands (docs/memory/workspace/workspace-record-commands.md). No new API route: `/products/{id}`, `/products/{id}/inventory` and the record routes cover a client's needs (owner default, 2026-10-19).
