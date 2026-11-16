---
name: event-tickets-features
description: Plan event-tickets, features T1 to T16: which order lines give tickets, the ticket and its code, the holder's page and mail, check-in and its answers, cancel, places given back, the waiting list
---

## What is a ticket?
- (T1) Tickets come from two kinds of order line: a calendar event reservation, and a product marked "Gives tickets" (so a site that sells its tickets as plain products is served too). One ticket per unit, 100 a line at most, made when the order is completed, as gift cards are.
- (T2) A ticket keeps its order line and unit number, the product, the event and date when the line has one, a random code kept only as its hash, and its state: valid, used (when and by whom) or void. The code is never the order's number.
- (T3) A switch "Tickets" in Site Settings, Features, off: reservations and products sell as today until it is on.

## What does the holder get?
- (T4) The order's own confirmation is written before its lines are completed, so tickets go out in a mail of their own through the mail queue, right after: one signed link to the tickets page. No QR picture is in a mail: a secret QR is never a picture at an address.
- (T5) The tickets page, opened by that link or from My Account: every ticket with its QR drawn inline by `pg_qr_svg()`, the event's name, date and place, the buyer's name, "Add to calendar" and Print.
- (T6) A void ticket's page says so and draws no QR.

## What happens at the door?
- (T7) A screen `event_check_in.php` for whoever may manage that calendar: pick the event's date or the ticket product, then scan with the phone's camera through the browser's own barcode reader, or type the code. No library is added; where the browser has no reader the typed code is the way.
- (T8) A valid ticket is marked used by one conditional UPDATE and the screen shows the name and "ticket 2 of 4". A ticket used before shows when and by whom, and is not marked again. Another event or date, a void ticket and an unknown code each get their own answer.
- (T9) The screen counts checked in against sold for what was picked, and finds a guest by name when the code is missing. A manager can undo a check-in.

## What do cancel and refund do?
- (T10) A cancelled order voids its tickets and a restored one makes them valid again, one call each where a cancelled order's folder access is handled.
- (T11) With tickets on, a cancelled order's places return to the event's remaining spots (owner, agent channel poll 88). With tickets off they do not return, as today.

## What is the waiting list?
- (T12) When an event's date has no place left, the reserve page offers "Tell me when a place frees": an address, kept as back-in-stock requests are (one per address and date, export, erasure, a rate limit).
- (T13) When places free (T11, or staff raising the spots), the first in line get a mail with a link that holds one reservation for 24 hours. A hold is a row that the reserve page and the cart's check count as taken; it is claimed with one conditional write.
- (T14) An unused hold ends by the general job's pass, which takes its turn with one claim, and the place goes to the next in line.

## What frames it?
- (T15) `GET /calendars/events/{id}/tickets` gives the counts and the tickets under the orders scope; `ticket.checked_in` is announced for flows and webhooks.
- (T16) A ticket reaches a person only through its order; the waiting list's addresses are in the personal data export and erasure and go 30 days after the event's date.
