---
name: event-tickets-page
description: Event tickets (TK2): the holder's page tickets.php behind the signed link, each ticket's QR and code, void ones without a QR, print, .ics; the my account tickets section, flag and account.tickets list
module: event_tickets_page, widgets_event_tickets
---

## How does the tickets page find the order?
- `tickets.php?o=<order id>&t=<token>` (root, no account): `pg_event_tickets_page_find()` checks the token first (`pg_event_tickets_link_token_valid()`, HMAC under the tickets key), then reads the order only when it holds a ticket. A wrong token, a deleted order and a table or key that is missing are the same 404 card.
- Rate limited per address: 120 opens in five minutes (`pg_event_tickets_page_throttled()`, the firewall's counters). `no-store`, `noindex`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`. Opening it changes nothing.
- Drawn with `output_header_secure()` like `booking_manage.php`, on look A's recipes (`pg-card`, `pg-pill`, `pg-button`), light and dark from the panel's theme.

## What does each ticket show?
- `pg_event_ticket_view()` (pure) and `pg_event_ticket_card_html()`: state pill (Valid, Used, Voided; "Void" is the verb in Turkish), "Ticket 2 of 4", the event's name, its date and place (`get_calendar_event()` for the ticket's occurrence; the date by `pg_event_ticket_when()` in the site's date format, numbers only, as the calendar's own range writes English day and month names) or the product's name, the name, and the code written out beside its QR.
- The code is drawn again from the row (`pg_event_ticket_code()`); the QR (`pg_qr_svg()`, inline SVG only) holds the code. When the redrawn code's hash is not the row's `code_hash`, the card says "This ticket cannot be shown here" and draws no QR.
- A void ticket draws no QR and no code, is faded, and says its order was cancelled. A used one keeps its QR and says when it was checked in.
- The name: the guest's from the unit's product form (`pg_event_ticket_guest_name()`: first and last name fields mapped to the contact, else a field whose variable name holds `name`, `guest`, `attendee`, `ad_soyad` or `isim`), else the buyer's billing name.
- "Add to calendar" per event ticket that is not void: `&ics=<event>-<occurrence>`, only for an occurrence the order holds a ticket for; one VEVENT through `pg_calendar_event_ics()` with no repeat rule and a UID per occurrence.
- Print: `window.print()`; the buttons carry `pg-print-none` and a ticket is never split over two pages.

## What does My Account show?
- Section `tickets` and flag `has_tickets` in `_pg_my_account_extras()` (shop sites, signed in): the member's orders holding tickets, newest first, 50 at most (`pg_event_tickets_account_rows()`), each linking to its signed page. Built whatever the switch says, once the table and the key exist, so tickets sold before the switch went off still open.
- List `account.tickets` (key `account_id`): `__tickets_order_no`, `__tickets_order_date`, `__tickets_events`, `__tickets_count`, `__tickets_count_label`, `__tickets_valid`, `__tickets_state_label`, `__tickets_tone` (success or secondary), `__tickets_url`. Filed by `pg_sw_list_account_ticket_rows()`.
- Designer twins: `_sdAccountTicketsHtml()` (the section's canvas sample), `_sdAccountTicketsList()` (a new list's starter, the badge's colour a condition on the tone). The section is in no starter; "Convert to design elements" does not offer it yet.
- The account part is site output (Bootstrap 5.3.8) in `includes/fn/widgets_event_tickets.php`; the page's in `includes/fn/event_tickets_page.php`. Both are loaded where used, not by `functions.php`.

Test: `tests/event_tickets_page_test.php`. Pictures from a sandbox site went to the owner; not run on a production database.

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/event-tickets-key.md, docs/memory/core/qr-code-generator.md, docs/memory/commerce/booking-manage.md
