---
name: personal-calendar-census-mail
description: PC1 census (2026-11-14): pg_mail_parse() keeps a mail's calendar part as an attachment entry; the Workspace mail ingest drops it today; what PC7 can reuse
---

## Does `pg_mail_parse()` keep a mail's calendar part?
- Yes. Only inline `text/plain` and `text/html` become bodies (`pg_mail_parse_leaf()`); a `text/calendar` part (an invitation's `method=REQUEST` alternative, usually without a name) is returned in `attachments` with `content_type` `text/calendar`, `filename` `''`, `inline` false, and its bytes in `content` when `with_content` is set, as the ingest sets it.
- An `invite.ics` file part is an attachment with its name, type `application/ics` or `text/calendar`.

## What does the Workspace ingest do with it?
- `ws_mail_attachments_sort()` (workspace/mail.php): a part without a name that is not inline is not taken and is named among the refused by its type ("text/calendar"); the named `invite.ics` comes into the channel as a file like any attachment the channel allows.
- Nothing reads the event. The raw mail is kept as `.eml` for `WS_MAIL_EML_DAYS` (30) days, then only the mail and its messages stay.

## What can PC7 reuse?
- `ws_ical_vevents()` and `ws_personal_ical_events()` (personal_ical.php) already read VEVENTs, zones, RRULE, EXDATE and RECURRENCE-ID, skipping nested VALARM blocks.
- `pg_mail_parse()` needs no change. The ingest must read the part before the `.eml` goes: a column on `ws_mail_items` or a card only while the `.eml` exists (`personal-calendar-census-tables.md`), asked with PC7.
- Proposed: METHOD CANCEL and a later REQUEST with the same UID change the plan made from it only when its owner presses the button again; no answer goes to the organiser (plan rule).
