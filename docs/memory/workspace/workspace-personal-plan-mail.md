---
name: workspace-personal-plan-mail
description: Add to my calendar on a mail (PC7): the button, the filled New plan drawer, one plan a person, the invitation column 11.0.204 and its card states
---

## Where is "Add to my calendar"?
- Channel: the menu of a mail's own text (`mail_calendar`, `ws_personal_plan_mail_offer()`) and an invitation card's buttons (`mail_invite_tools`, filled per reader into `data-ws-mail-invite-tools`). E-mails pane: under a mail beside Forward, or on its card only (`ws_personal_plan_mail_pane()`).
- A team member once the plan tables are there; never a bounce, a phone message, a file or a guest.

## What does it open?
- Action `ws_personal_plan_mail` (`ws_personal_plan_mail_draft()`, modes '', open, update, remove, tools): the mail is read again (`ws_personal_plan_mail_item()`) and My calendar's drawer opens on the same screen (`PGWsMyCalendar.fromMail()`).
- Fill (`ws_personal_plan_mail_fill()`): an invitation's title, days, times, place as the note; else the subject without "Re:"/"Fwd:" and the mail's day, all day.
- One plan a person for a conversation (`ws_personal_plan_mail_plans()`, `ws_personal_plan_of_mail()`): the save refuses a second ("This e-mail is already in your calendar."); the menu then opens the plan.
- A saved plan shows a quiet line with the conversation's subject opening it (`plan.mail`, `ws_personal_plan_mail_line()`), only to a reader who may read that channel.

## How is an invitation kept?
- Step 11.0.204 `ws_mail_items.invitation` TEXT (`ws_mail_invitation_ready()`). Ingest: `ws_mail_invitation_keep()` reads the first calendar part (`ws_mail_invitation_part()`), first VEVENT: JSON uid, method, summary, start, end, all_day, location, organizer (name, email), text cut to 255; nothing else. Unreadable: column empty.
- `changed`: a REQUEST whose UID an earlier mail of the box carried; STATUS:CANCELLED reads as CANCEL. No backfill.
- A nameless part read into the card is taken off "Not taken in"; it never becomes a channel file.

## What does the card show?
- `ws_mail_invitation_card_html()`: Invitation / Changed invitation / Cancelled invitation, title, when, place, "Organiser: name <address>" (address only when not the sender's), the reader's buttons and "No answer is sent to the organiser." All plain escaped text; nothing is a link.
- When (`ws_mail_invitation_when()`), site time zone: weekday d.m, the year when not this year; all-day or many-day as days without hours.
- Buttons (`ws_personal_plan_mail_state()`): add (no plan); in ("In your calendar", "Open the plan"); update ("Update my plan": a changed one the plan does not follow; opens the plan filled); remove ("Remove from my calendar": cancelled, deletes after the confirmation). Changed or cancelled without a plan: none. After a change every card on the screen reads its buttons again.
- Owner (2026-11-14, channel 3219-3220): mail never changes a plan by itself; no answer to the organiser. Architect (2026-11-14, 3271): the look and these four points.

## What about a contact's erasure?
- Nothing scrubs `ws_mail_items` on erasure (`pg_privacy_erase_tables()` lists no mail table); the organiser's address in `invitation` stays like `from_email`. Architect (2026-11-14, channel 3273): enough for PC7; Workspace records are outside a contact's erasure by its own list (`core/personal-data-erasure.md`, `core/contact-holders.md`); the product question stays with the owner.

Test: `tests/workspace_personal_plan_mail_test.php`.
