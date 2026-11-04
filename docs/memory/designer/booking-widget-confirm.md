---
name: booking-widget-confirm
description: Booking widget details step and confirm (#6596): form fields by name, signed-in prefill, auto_confirm status, expired hold rebooked when free, taken path, privacy page link
module: widgets_booking
---

## How is a booking made?
- The `booking_form` part is a plain `<form>`; its fields are found by `name`: `name`, `email` (needed), `phone`, `notes`, `consent` (sent only when the page has the box, then it must be ticked). `pg_booking_widget_details_clean()` checks them on the server and cuts them to their columns.
- The hold answer carries `visitor` (name, e-mail, phone of a signed-in account, `pg_booking_widget_visitor()`); the script fills only empty boxes. The page stays the same for everyone, so the page cache may serve it.
- `confirm` (counted with holds) uses the session's hold id and slot (`booking_hold`, `booking_hold_slot`): `pg_booking_confirm()` with status `confirmed` when the policy's `auto_confirm` is on, else `requested`; a signed-in visitor's user id is linked. The closing words are the site's `done` text, else the widget's for a booking or a request (`pg_booking_widget_done_text()`).
- A hold that ran out is booked anyway when the day still offers its time; otherwise `expired`, and a direct insert refused on the key is `taken`. Both send the visitor back to the day's times, read again.

## Where does the consent box link?
- To the page the design templates install as the privacy notice: `lang('privacy-policy')` or `privacy-policy` (`pg_booking_widget_privacy_url()`); without one the link's span is hidden by `has_privacy_page`, never a dead link.
