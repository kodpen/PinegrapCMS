---
name: decisions-made-schema-19
description: Decisions list, schema part 19 (2026-11-14 on): ws_mail_items.invitation for a plan's invitation card; ws_mail_items.draft_app_id, who wrote a draft; ws_polls.agent_question
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

## Does a mail draft keep which application wrote it? (owner, 2026-11-14, agent channel poll 3369)
- Yes: `ws_mail_items.draft_app_id` INT UNSIGNED NOT NULL DEFAULT 0, the application that created the draft, 0 for a draft written in the panel. Written on create only; added by step MT5 of `plans/mail-chat-tools.md` with `install_add_column()`.
- The owner chose "Evet, sütun eklensin; asistanın taslağı etiketle görünsün" over "Hayır, sütun eklenmesin; taslakta kimin yazdığı görünmesin" (the outcome: channel message 3372).
- Why asked: `ws_mail_draft_insert()` stores the person for every caller, so the Drafts view (MT3b, #9543) cannot tell an assistant's draft from the person's own. With the column the row carries the application's name as a pill; #9543 lands without it.
- Running sites: no backfill; a draft made before the step reads as the person's own.

## Does a poll keep that it is an agent question? (owner, 2026-11-14, in the architect's window)
- Yes. Owner: "oylama yine dursun, ajan sorusu sütunu ekleyelim ajan sorusu olarak seçiliyse(yine bunu sadece ajan ve asistanlar sorduğunda otomatik işaretlenir yapalım) oylamanın görünüşü değişsin."
- Shape, the architect's: `ws_polls.agent_question` TINYINT UNSIGNED NOT NULL DEFAULT 0, set by the server when an application agent or an assistant makes the poll, never from the request; added by step AQ4 of `plans/poll-agent-questions.md` with `install_add_column()`. No backfill: an earlier poll of an agent stays a poll.
- The other columns the plan needs (an option's description, picture and links, "Other", who is asked) are asked after its census.
