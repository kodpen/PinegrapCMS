---
name: decisions-made-schema-19
description: Decisions list, schema part 19 (2026-11-14 on): ws_mail_items.invitation, what a mail's calendar part said, kept for the invitation card of a personal plan
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Does a mail keep what its invitation said in a column of ws_mail_items? (owner, 2026-11-14, in the architect's window)
- Asked by step PC7 of `plans/personal-calendar-plans.md` (agent channel message 3218): the invitation card of a mail needs what the mail's calendar part said, and the raw `.eml` is kept only `WS_MAIL_EML_DAYS` (30) days.
- The owner, asked "Davet kartı için e-posta tablosuna "invitation" sütunu eklensin mi?", answered: "Evet, eklensin". Told to the step in channel message 3220.
- Not chosen: no column, the card drawn only while the `.eml` exists. An invitation is mostly for a day ahead: the card of a meeting six weeks away would go before the meeting, and "Add to my calendar" would then put the plan on the mail's day.
- Shape: `ws_mail_items.invitation` TEXT NULL, added by PC7's step of the open version with `install_add_column()`. Filled at ingest from the mail's `text/calendar` part, named or not, read with `ws_ical_vevents()`: the first VEVENT only, as JSON with `uid`, `method`, `summary`, `start`, `end`, `all_day`, `location`, `organizer` (name and address), text fields cut to 255 characters.
- Kept out of the column: the attendee list, the description, alarms. A part that does not parse leaves the column empty and the mail as it is. The nameless calendar part still does not become a channel file.
- Running sites: no backfill; a mail taken in before the step shows no card. The column goes with its mail row, and what scrubs a mail on a contact's erasure covers it (it holds the organiser's address).

## Does a later mail of the same invitation change the plan? (architect, 2026-11-14)
- The architect's, not the owner's words, inside the plan's rule "No answer goes to the organiser": a later REQUEST or CANCEL with the same UID never changes a plan by itself. That mail draws its own card, and the plan changes only when its owner presses the button on it. Mail from outside does not write into a private calendar.
