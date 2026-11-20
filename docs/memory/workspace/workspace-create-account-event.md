---
name: workspace-create-account-event
description: /create account (/oluştur cari) and /create event (/oluştur etkinlik) in a channel: how each line is read, the card, the drawer opened filled, the writers, rights, the strip
---

## What does /create account do? (CC8, PR #10262)
- `/oluştur cari Acme Ltd, 1234567890, info@acme.com` (also `account`): `ws_command_parse_account()` takes an e-mail anywhere, then the parts between commas: the first the name (`title`), 10 digits or 11 not starting with 0 the tax number, digits with `+ ( ) -` the phone; another part stays in the name.
- `ws_command_account()` hands them to `ws_command_create_card()`: the card of the /create contact model (rows, decision tick, Create, "All fields", Cancel; no "On sale" tick).
- Create alone writes, through `ws_quick_create_save()` and the catalogue's `ws_change_write_erp_account()`, which saves with `erp_account_save()` (add_erp_account.php's save) and refuses a tax number that fails `erp_tax_number_check()` or an active account holds. It opens as a company customer, active; the rest is on the account card.

## What does /create event do?
- `/oluştur etkinlik yarın 14:00 Açılış` (also `event`): `ws_command_parse_event()` reads the day and time first as /remind does (`ws_command_remind_data()`); the rest is the name. A day alone is an all-day event; a time alone is today's.
- It goes on the person's calendar when they may use exactly one (`ws_quick_create_calendars()`, the event screens' rule `ws_change_calendar_access()`). Written by `ws_change_write_calendar_event()` with the event screen's starting values: unpublished, no repeat, no reservations. The command never sends `published`.

## What if the line leaves something out?
- The card is not drawn; the composer opens the drawer's new account or new event filled with what was typed (`preview.missing`). An account needs its name; an event its name, day and calendar (`ws_command_create_needs()` beside `ws_quick_create_missing()`), so a person with several calendars always chooses in the drawer.
- Both kinds are in `ws_quick_create_types()`. The drawer cannot search calendars (no tag kind): `ws_quick_create_choices()` gives the calendar field the person's calendars, drawn as a `select.pg-input` with `lookField()` in `quickCreateSection()`.
- Calendars show by name in the card and the decision lines, not as tags (only kinds of `ws_change_types()` get chips).
- The card writes a start or end as the drawer's field does, in the site's date order and clock (`ws_command_card_datetime()`, "18/11/2026 14:00"); an all-day event its day alone (architect, agent channel message 8917).

## Who is offered it?
- `ws_command_create_may()` asks `ws_change_allowed()`: an account the ERP right, never the read-only accountant, and only with the ERP module on (`ws_change_types()` drops `erp_account` when `ERP_ENABLED` is off); an event the calendars right, not when `CALENDARS` is off. The kind popup and the gate follow it.

## What does the strip show?
- `variants` lines of the `/create` entry: `/oluştur cari <ad>, <vergi numarası>, <e-posta>` and `/oluştur etkinlik <gün> <zaman> <etkinlik adı>` (the day offers the dates), each with its example.

## Tests?
- tests/workspace_command_create_account_event_test.php; the kinds also in workspace_commands_test.php and workspace_quick_create_test.php.
