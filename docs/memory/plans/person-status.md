---
name: person-status
description: Plan (owner 2026-11-28): a person's own status (kind, note, end time) shown beside their name and told to agents in each brief, in ws_profiles.status; steps PS1 to PS4
priority: 6
---

## What is asked?
- Owner (2026-11-28, agent channel message 15451): a status a person sets (available, busy, out…), which the channels' agents read and act on: whom to call, how long to wait, why no answer came. Decision and column: `workflow/decisions-made-schema-57.md`.

## What is there today?
- The dot on a face (online, away, offline) is the panel's heartbeat (`ws_presence()`), nothing the person wrote.
- Quiet hours, the mail "I am away" range and leave days exist; the person card shows them (`workspace/person-card-facts.md`) and an agent may read it, but no brief tells a person's state.

## What did the architect settle? (not the owner's words)
- A status is a kind, an optional note and an optional end. Kinds: Available, Busy, In a meeting, Out, Sleeping, On leave. Note: one line, 80 characters. End: 30 minutes, 1 hour, 4 hours, today, a chosen time, or none; each kind offers its own default. An ended status reads as none, with no job.
- Only the person sets it, from the panel, a command, their team devices session or their own connection; never an application's key, never a manager for somebody else.
- It informs and holds no push: quiet hours stay the only thing that does. The dot stays the heartbeat.
- Team members see it wherever they see the person; a guest and the guest page never.
- In a brief the note is a person's text: one line inside a data block, under the standing caution (`workspace/workspace-outside-caution.md`). Last seen stays a channel manager's (`person-card-facts.md`).
- `ws_status_ready()` probes the column; before it nothing is drawn or told.
- Screens in words: `person-status-screens.md`.

## Which steps? (S under 400 product lines, M about 1,000; each waits for the one before it)
- PS1 (S) Schema and rule: the step (rerun twice); `includes/fn/person_status.php`, pure: the kinds, the input check, the decode that drops an ended status, a kind's default end, the words "until 18:00" in the person's zone; `ws_status_get()` and `ws_status_save()` (own only); panel action `ws_status_save`; `GET` and `POST /workspace/status` with an MCP tool for the person's own callers (`workspace/workspace-api-callers.md`); `ws_person_brief()` gains `status`. Tests.
- PS2 (M) Screens: the Status dialog, its entries, the mark beside names, the card's line, the composer's line, as the screens file says.
- PS3 (M) Agents: a "People" block in `ws_agent_brief()` and `ws_ai_briefing()` (`ws_brief_people_lines()`, pure, at most 6 lines): the asker and the people the message names, each with status and its end, local time, quiet hours left, away today, presence. `status` on the person card and member objects (REST, MCP, OpenAPI, `check_api_schema.php`). The instructions gain the lines below; `instructions_version` changes once. The line under an open agent question.
- PS4 (S) Command and close-out: `/mystatus · /durumum` (kind, end, note; alone it shows the status to its writer; "clear") with its live line; set, end and an agent's brief tried on dev; `workspace/person-status.md` written, `person-card-facts.md`, `workspace-api-person-card.md` and `agent-instructions.md` corrected; this file and the screens file deleted.

## Which lines do the instructions gain?
- Before you name a person, read their line under "People". Someone out, sleeping, on leave or in quiet hours is asked once: say when an answer can be expected and do not ask again before then.
- When a note says where the person reads (a channel), ask there.
- When an answer did not come, say why from the status, and go on with work that does not hang on it.

## What is left out? (each a later yes of the owner)
- A status that holds notifications; a history; a status taken from the calendar; an agent's own status; kinds a site adds.
