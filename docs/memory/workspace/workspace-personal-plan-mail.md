---
name: workspace-personal-plan-mail
description: Add to my calendar on a mail (PC7): where the button is, what fills the New plan drawer, the tie to the conversation, the invitation column 11.0.205 and its card
---

## Where is "Add to my calendar"?
- Channel screen: in the menu of a mail's own text (message field `mail_calendar`, `ws_personal_plan_mail_offer()`), and as the button of an invitation card. E-mails screen: under each mail of the reading pane, beside Forward (`data-ws-mail-calendar`, `ws_personal_plan_mail_pane()`); a mail with an invitation card carries it on the card only. `ws_mail_pane_columns()` reads `invitation` once the column is there.
- Offered to a team member once the plan tables are there; never on a delivery failure, a phone message, a file or a guest. The channel card's button is taken away by the script for a reader without `mail_calendar`.

## What does it open?
- Panel action `ws_personal_plan_mail` (`ws_personal_plan_mail_draft()`): the mail is read again (`ws_personal_plan_mail_item()`: its message not deleted, its channel or its conversation's channel readable) and the New plan drawer of My calendar opens on the same screen (`PGWsMyCalendar.fromMail()`), its words sent with the fill.
- Fill (`ws_personal_plan_mail_fill()`, pure): from an invitation its title, days, times and place (as the note); else the subject without "Re:", "Fwd:", "YNT:"… (the conversation's when empty) and the mail's day, all day.
- Saving sends `mail_item`; `ws_personal_plan_save()` reads the mail again and stores its conversation in `mail_conversation_id` (new plans only). The owner's drawer then shows "Open the e-mail" (`messages_mail.php?conversation=`).

## How is an invitation kept?
- Step 11.0.205 `ws_mail_items.invitation` TEXT (`ws_mail_invitation_ready()`). At ingest `ws_mail_invitation_keep()` reads the first calendar part (text/calendar or application/ics, named or not, or a `.ics` name) with `ws_ical_vevents()`, first VEVENT only: JSON uid, method, summary, start, end (moments, end exclusive), all_day, location, organizer (name, email); text cut to 255. No attendees, description or alarm. A part that does not read leaves it empty.
- `changed`: a REQUEST whose UID an earlier mail of the same box carried. STATUS:CANCELLED reads as method CANCEL.
- No backfill: mail taken in before the step has no card. The nameless calendar part is still refused as a channel file (`mail-ingest.md`).

## What does the card show and do?
- `ws_mail_invitation_card_html()` under the mail's text in the channel and above it in the pane: Invitation / Changed invitation / Cancelled invitation, title, when, place, "Organiser: …", the button and "No answer is sent to the organiser."
- Owner (2026-11-14, agent channel 3219-3220): a later REQUEST or CANCEL never changes a plan by itself; mail from outside never writes into a private calendar; no answer goes to the organiser.
- Pressing it when the person already has a plan from that conversation (`ws_personal_plan_of_mail()`) opens that plan with the mail's new event filled in; on a cancelled one the button reads "Open my plan" and opens it to be deleted. Nothing changes until they save.

## What about a contact's erasure?
- Nothing scrubs `ws_mail_items` on erasure today (`pg_privacy_erase_tables()` lists no mail table): `from_email` and `body_text` stay, and so does the organiser's address in `invitation`. Reported to the coordinator with PR #9528; not decided here.

Test: `tests/workspace_personal_plan_mail_test.php`.
