---
name: booking-link-page
description: The meeting link's page book_with.php (SL2): checks before reading, hold and confirm with source link, the one 404, the done card; the meeting address in the mails and the .ics
module: booking_links
---

## How does the page book? (SL2)
- `book_with.php?c=<code>` (root, no script, `output_header_secure()` card): services (`&s=`, skipped when one), month (`&m=`) and day (`&d=`) links, times as POST buttons (`action=hold`), then the form (`&details=1`) and `action=confirm`. The address is `PATH . SOFTWARE_DIRECTORY . '/book_with.php'` (`pg_booking_link_url()`).
- Before anything is read: `pg_booking_link_page_headers()` (no-store, noindex, no-referrer), `pg_booking_link_refusal()` (a cross-site POST 403, `pg_booking_throttled()` hold/read 429), then `validate_token_field()` on a POST. A GET from another site is answered: it is a link someone followed.
- No member, or a link that offers no service (all with a deposit), is the one 404.
- A hold only on a start of `pg_booking_link_times()`; `pg_booking_hold()` with source `link`, kept in the widget's session keys (one hold per session, `pg_booking_release_session_hold()`).
- Confirm: `pg_booking_link_held()` (this member, an offered service), details as the widget's with the consent box required (`pg_booking_link_details()`), `pg_booking_confirm()`; a hold that ran out is booked at once while the time is free. Taken or expired: the day's times again with `pg_booking_link_answer_text()`.
- The done card (`&done=1`, this session's booking only): facts, the meeting address, Add to calendar (`booking_manage.php` .ics).

## Where does the meeting address show?
- `pg_booking_message_meeting_url()`: only for `source = 'link'` and not cancelled, read when shown. Mail field `^^booking_meeting_url^^` (its http:// is taken off like the manage link's); .ics `CONFERENCE;VALUE=URI` (RFC 7986), a DESCRIPTION line, and LOCATION when the site has no address; an address with a space or control character is left out.

Test: `tests/book_with_test.php`. Clicked through on a throwaway install (#10073): services, days, times, form, errors, done, a taken time, a wrong code, desktop and phone.

Related: [[booking-link-code]], [[booking-messages]], docs/memory/plans/meeting-link-features.md
