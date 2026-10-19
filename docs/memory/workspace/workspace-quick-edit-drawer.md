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
