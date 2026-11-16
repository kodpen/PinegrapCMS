---
name: channel-commands-census-actions
description: Census (2026-11-16) of what a person does in a Workspace channel with a button or a menu and no command: the Insert menu, the channel's own items, a message's actions, the person's own settings
---

## Which items of the box's Insert menu have no command?
- "Ask for approval": `ws_approval_create()` after `ws_approval_input()` (a title, the people asked, a rule, a closing time).
- "Status update": `ws_status_post()` (a health of `ws_status_healths()` and a text). "Ask for a check-in": `ws_checkin_start()` (a question). Neither in a discussion. The name /status is taken: it moves a task.
- "Start a flow": `ws_flow_run_start()` for a flow `ws_flows_runnable_in()` offers here, public and private channels only.
- "Table", "Calculation", "Checklist", "Code block", "New canvas", "From elsewhere", "Saved replies" (typed as ";"), "New flow": parts of a message or a screen of their own.
- "Start a poll" has /poll; "Send later" beside Send has /schedule.

## Which of the channel's own items have no command?
- The reader's notifications of this channel, "Tell me about every message", "Only when I am mentioned", "Never": `ws_channel_set_notify()`.
- "Add people" (`ws_channel_members_add`), "Leave the channel" and removing a person (`ws_channel_leave()`), "Join", "Archive the channel" (`ws_channel_archive()`), the name and "What it is about" (`ws_channel_update`), "Make it private", "Move to a group", "Apply a template", "Share with a link", "Export", "Clear the conversation", the channel's pin in the list.
- An @ of somebody who is not in the channel already offers "Invite to the channel" under the sent message (`ws_mention_invite`).
- "Search in this channel" opens the palette for this channel; "Edit the summary" (`ws_channel_set_summary()`); the Memory tab's "New entry" (`ws_memory_save()`); "Sum up what I missed" on the catch-up line (`ws_recap_ai()`).
- On a customer channel "New sales quote" opens `add_erp_quote.php` for the channel's customer; the customer card's "Remind me" opens a follow-up task (`ws_customer_followup_open()`).
- "New e-mail", on a channel a mailbox keeps its mail in, opens the compose drawer (`workspace/mail-compose.md`). In a mailbox's own room nobody posts, so no command runs there.

## Which actions of a message have no command?
- "Start a discussion" (`ws_thread_start()`), "Make a task of it" (`ws_task_create()` with the message), "Mark as a decision" and "Mark as a channel note" (`ws_message_mark()`; /decision and /note write a new message instead), "Pin to the top" (`ws_pin`, one a channel), "Save to my notes" (`ws_note_from_message()`), "Choose messages" then Forward, "Ask for read receipts" (`ws_ack_request`), "Translate" (the browser's), Edit, Delete.

## What is the person's own?
- A reminder of one's own is a personal plan: "New plan" on My calendar, `ws_personal_plan_save()` (a title, a note, a day, a time, minutes before to remind). No command writes one.
- "Quiet hours" (`ws_quiet_hours_save()`, a range of every day kept as JSON in `ws_profiles.quiet_hours`). The sound, read-aloud and application-lines choices are the browser's.
- Leave has /away; a meeting /meeting; the week /plan.

## What do other team chats run by a command that is no feature here?
- A reminder about a message, saving a message for later, marking unread, a status text or emoji of the person (presence is worked out, `ws_presence()`), "do not disturb" for some hours (only the daily quiet hours exist), a call started from the channel (a plan item can carry a meeting link), folding previews for the reader, an action line (/me), a shrug, dice, a countdown.
- "@here" and "@channel" are not there; `@herkes` / `@everyone` is (`workspace/workspace-everyone-mention.md`).
- A direct message is opened from the palette with @name (the panel's chat); the palette also searches.
