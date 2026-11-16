---
name: channel-commands-census-records
description: Census (2026-11-16) of the site records a channel command could write: the change kinds, what the drawer and /create cover, the writers with no command, what the Workspace cannot write
---

## How does the Workspace write a site record?
- Only through `includes/workspace/changes.php`: `ws_change_types()` names each kind, its right, its actions and its fields; `ws_change_check()` then `ws_change_write()` (`workspace/workspace-record-changes.md`).
- Three doors use it: a change card somebody applies (an application's or an assistant's proposal, `ws_change_apply()`), the record drawer's Fields (`ws_quick_edit_save()`), and the commands /price, /stock and /create.

## Which kinds can be created, and from where today?
- The catalogue creates a product, a product group, a variant set, a contact (`first_name`, `last_name`, `company`, `email` as its required list), an ERP account, a sales quote with its lines (and its invoice), a site calendar event, a task note, a note, a canvas file.
- /create and the drawer's new mode reach two of them: `ws_quick_create_types()` is product and product group. The rest come only as a proposal's card.
- A workflow table's row is no change kind: `ws_wtable_row_create()` writes it (the API, a flow's step, an import, a channel page).

## Which fields can be changed, and from where today?
- The drawer's Fields exist for `ws_quick_edit_types()`: product, product group, order, contact, ERP account, offer, calendar event.
- An order: `status` (through `pg_order_status_move()`), `notes` (the whole field, no line added to it), `cancellation_reason`, `po_number`, two custom fields, `tracking_numbers` (the customer is not mailed from here). An offer: `status`, `description`, `start_date`, `end_date`. A contact 35 fields, an ERP account 22, a calendar event its name, place, times and published.
- Commands reach a product's price and its stock, nothing else.
- A page (title, description, search and sitemap flags), a file, a submitted form (complete, its answers), a user (administrators only) and a task change only by a card or their own screen.

## What can the Workspace not write?
- A booking: read on the calendar, "Changed on the agenda, never from the Workspace" (`refs.php`); the free times are the booking engine's (`pg_booking_slots()`).
- A new offer or coupon (rules are built on the offer screen), a gift card or store credit, a new page, a new submitted form, an order.
- A sales quote after it is made: changing, sending and signing stay on the quote's screen (`workspace/workspace-sales-quote-change.md`).

## What reads a record for one person only?
- `ws_ref_search()` finds, `ws_refs_resolve()` and the chip draw, `ws_customer_summary()` and `ws_customer_history()` sum a customer up; a command's `ephemeral` answer is the writer's alone (/plan uses it). No command looks a record up.
- Architect (2026-10-30, #3801): record-card commands (/sipariş…) were refused, "the `#` picker and the ref drawer show records".

## What does a write from a chat line keep to?
- The record is tagged with `#` from the picker, never looked up by a typed name; nothing is written before a card shows the old value beside the new; only Save writes, with the text the card was drawn from and the value it showed (`from`); "Keep it in this channel as a decision" is on (`workspace/workspace-record-commands.md`).
- The rights are the record screen's: `ws_change_allowed()`.
