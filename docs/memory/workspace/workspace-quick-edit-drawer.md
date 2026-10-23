---
name: workspace-quick-edit-drawer
description: The "Fields" section of the record drawer (openRecord, quickEditSection in workspace.js): glance line, short list, inputs from saValueInput(), what a save sends, stale line, decision tick
---

## Where is it drawn?
- `openRecord()` in workspace.js (PR #526) loads `ws_record_fields` beside `ws_record_refs` and draws a "Fields" section first: a glance line (the first short-list values) and an "Edit fields" toggle; the chip menu's "Edit fields" opens it out (`openRecord(type, id, label, {edit: true})`, offered for the kinds in `BOOT.quick_edit`). The short list (`ws_quick_edit_short_list()`, server side) comes first, the rest behind "More fields"; a field without a column (an order's reason) shows only while `status` is `cancelled`. Inputs come from `saValueInput()`, which also knows decimal, lines, keywords, datetime and e-mail, puts the catalogue's limits on the input, and draws a `long` field of at most 255 characters as one line. A save sends only the fields whose value changed (`quickEditChanged()` compares in each kind's own way), with `from` for them; on `stale` the status line offers "Load it again". On the channel screen a "Keep it in this channel as a decision" tick is on by default.

## How is an input built?
- `saValueInput(meta, value)` is shared with the scheduled action form: enum → select with the catalogue's labels, bool → yes/no select, lines → textarea one per line, long → textarea (one line when the limit is 255 or less), decimal/int/money → number (money typed in units, read in minor units), date → date, datetime → datetime-local (the record's "YYYY-MM-DD HH:MM:SS" shown as "YYYY-MM-DDTHH:MM", sent back with the T the server accepts), email → email, keywords → text with a comma hint. `limit` sets `maxLength`, `min`, `max`. `input.read()` gives the value in the shape the server types.

## What does a save send?
- `fields`: only the changed ones (`quickEditChanged()`: lines and keywords compared as trimmed lists, decimals as numbers, datetimes to the minute, texts with line endings folded); `from`: the originals of those fields; `channel_id` and `decision` on the channel screen. The answer's `lines` are what the toast and the decision say; after a save the drawer reloads itself (`reopen`).
- Errors: `stale` → the status line with "Load it again"; a refused value → the server's sentence on the status line; `code: rights` on load → no section at all; `rule` or `gone` → the section with the reason and no form.

## How is an empty date handled?
- Symptom (issue #776, PR #1264): a contact without a membership end has `expiration_date = '0000-00-00'` (the contact screen's "no end"). A date input cannot show the zero date and empties itself, so the drawer sent `''` as a change, `ws_change_value()` refused both values, and no field of such a contact could be saved.
- Now `ws_change_current_value()` gives the zero date (or NULL) as `''`, and `ws_change_value()` takes `''` and `0000-00-00` for any `date` field as `''`. In the drawer, `quickEditChanged()` and `saValueInput()` treat the zero date as empty; the apply check takes a stored `from` of `0000-00-00` as `''`.
- Each writer stores `''` its own way:
  - contact: `ws_change_date_column()` writes `0000-00-00`; only `contacts.expiration_date` means "no date" by the zero date.
  - offer: never the zero date. Checkout applies an offer only while `CURRENT_DATE() <= offers.end_date`, so a zero end would end it at once and the offer screen would call it expired. An emptied `end_date` is written as `PG_OFFER_OPEN_END_DATE` (`2099-12-31`, edit_offer_f.php) by `ws_change_offer_date_column()`; an emptied `start_date` is refused by `ws_change_check()` with "An offer needs a start date." (the offer screen needs one).
  - task: NULL through `ws_task_update()`.
