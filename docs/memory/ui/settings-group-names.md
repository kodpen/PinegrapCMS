---
name: settings-group-names
description: Site Settings groups, user rights Select / Select All and the other two-list Select All switches (Organize Contacts, calendar event) named by their heading
---

## Which Site Settings cells are named groups?
- The pane arrives over Ajax, so a heading must name its controls in the markup itself (`toggle-and-mark-names.md`). These cells are `role="group"` with `aria-labelledby` on the existing `col-12`, their heading a `div.form-label` with an id.
- The headings: `erp_edoc_provider_title` (e-Document Provider radios), `erp_fx_currencies_title` (Currencies allowed on documents), `erp_overdue_notify_title` (Overdue receivable reminders: the bare "E-mail" switch now reads with it), `waf_country_exempt_title` (Always let in; was a `<label>` with no `for`; its heading is now an `h6`, below), `translation_targets_title` (Target languages; same).
- No wrapper was added: the card rows are laid out by `:has()` rules on the cell's children, and those rules match the `.form-label` class, not the element, so a label turned div keeps its look.
- Always let in is the exception since #9046: its heading is the sub-heading `h6.fg-secondary` (same id) and its three switches sit in a nested `.row` inside the `col-12 pb-0` group cell, so each is a row with its line (`settings-card-markup.md`).

## How is each target language block named?
- `div.pg-tr-language` is `role="group"` named by its `<strong id="tr_name_<code>">`, so two languages' "Served", "Remove" and fields are told apart ("Remove" deletes that language's translations).

## How is an SMS text named?
- The event's switch label has `id="sms_msg_<event>_label"`; the textarea is `aria-labelledby` it (`pg_sms_message_settings_fields()`).

## How do the user rights drawers name Select and Select All?
- The row's drawer button is `aria-labelledby="pgperm_open_<id> pgperm_row_title_<id>"`: it reads "Select" then the row title, so the visible word stays first in the name (Label in Name) and no string was added.
- `pg_user_permission_list()` gives a block with a heading `<checker_id>_title`, the Select All label `<checker_id>_label`, and the switch `aria-labelledby` both. A list without a heading keeps its plain label. Ids, names, values, counters and multiselect classes are unchanged.
- Organize Contacts (Add to / Remove from Contact Groups) and the calendar event form (locations, calendars) follow the same `<checker_id>_title` / `<checker_id>_label` pattern in their markup; their `multiselect-checkbox-checker-N` ids stay (`backend.src.js` keys on them).

Test: `tests/settings_group_names_test.php`.
