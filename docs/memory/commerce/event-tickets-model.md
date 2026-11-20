---
name: event-tickets-model
description: Event tickets (TK1): lines that give them, one event_tickets row per unit, the code stored only hashed, void on cancel and back on restore, the tickets mail, the switch
module: event_tickets
---

## Which order lines give tickets?
- `pg_event_tickets_line_gives()` (`includes/fn/event_tickets.php`): a calendar event's reservation line (`calendar_event_id` set, the event's `reservations = 1`, calendars on), or a product with `products.gives_tickets = 1`. A line that is both gets one set.
- One ticket per unit, 100 a line at most (`PG_EVENT_TICKETS_LINE_CAP`, as gift cards), made at completion: `pg_order_complete_lines()` calls `pg_event_tickets_complete_line()` right after the gift cards' call. A counter sale (`local_order.php`) makes them too, without the mail.
- `pg_order_lines_sql()` reads `products.gives_tickets` only once the column is there (`pg_schema_has()`).

## When are no tickets made?
- With the switch "Tickets" (Site Settings, Features, card `pgset-event-tickets`) off: a config_text row `event_tickets`, '1' on, missing off; every site and a new install start off.
- Without the table, or when the tickets key is missing or does not open: `pg_event_tickets_on()` asks all three.

## What does a ticket row keep?
- Table `event_tickets` (step 11.0.247): order, order line and unit (unique together, written with `INSERT IGNORE`, so a line is never ticketed twice), product, event and its `recurrence_number` (0 for a product's ticket), `code_salt`, `code_hash` (unique), `state` valid / used / void, `used_at`, `used_by`, `voided_at`, `created_at`.
- Architect (2026-11-20, agent channel message 10086): the columns, the five indexes, `products.gives_tickets` (INSTANT where the server allows it) and the step approved (labelled 11.0.246 then; 11.0.247 since CC10 landed with 11.0.246); the table is `event_tickets`, plural like `order_items` and `gift_cards`.

## How is the code made and found?
- `pg_event_ticket_code()`: HMAC-SHA256 of line, unit and salt under the tickets key, 60 bits as twelve Crockford base-32 characters `XXXX-XXXX-XXXX`. Never the order's number; drawn again from the row for the holder's page.
- The key: `event-tickets-key.md`.
- Only `pg_event_ticket_code_hash()` is stored: sha256 of the normalized code (upper case, no spaces or dashes, O read as 0, I and L as 1). The door finds a ticket by that hash.

## What do cancel and restore do?
- `process_order_cancellation()` calls `pg_event_tickets_order_cancelled()` after the folder access take-back: one `UPDATE … SET state = 'void' WHERE order_id AND state <> 'void'`.
- `pg_order_restore_cancelled()` calls `pg_event_tickets_order_restored()` after the folder access: void rows go back to `used` when `used_at > 0`, else `valid`.
- Both run whatever the switch says, so a cancelled order's tickets never stay valid.

## What mail does the buyer get?
- After the lines, `pg_order_complete()` calls `pg_event_tickets_order_mail()` when tickets were made: subject "Your tickets for order #…", one signed link `tickets.php?o=<order id>&t=<token>` (`pg_event_tickets_link_token()`, HMAC of the order under the tickets key), through the queue (`'queue' => true`). No QR picture in a mail.
- `tickets.php` itself is TK2.

Test: `tests/event_tickets_test.php` (the step run twice against stubs). Not run on a dev database.

Related: docs/memory/commerce/event-tickets-key.md, docs/memory/plans/event-tickets.md, docs/memory/commerce/calendar-reserve-occurrence.md, docs/memory/core/qr-code-generator.md
