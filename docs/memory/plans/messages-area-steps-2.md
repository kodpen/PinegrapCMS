---
name: messages-area-steps-2
description: Steps MA9a-MA9f and MA10a-MA10c of the messages area plan (#8961): campaign profiles turned into flows, incoming e-mail in the area; order and rules in messages-area-steps.md
---

## Which steps? (order: `messages-area-steps.md`; rules: `messages-area.md`)
- MA9 Profiles into flows (M2b), in six parts: MA9a (S, no product code) — done #NNNN, its choices made there (`core/campaign-profile-flow-*.md`): every field of a profile against the boxes, what no box covers, the switch moment, the dry-run report.
- MA9 goes on: MA9b (M) the e-mail box sends as a campaign does (sender, page body with fields and data, marketing codes and footer, a group's opt-out); MA9c (M) the Wait box's time of day and "if passed", the events' mail values, `calendar_event.reserved`'s start and person (`core/campaign-profile-flow-gaps.md`).
- MA9 ends: MA9d (M) the converter as pure functions with tests, the dry-run report; MA9e (M) the update step that converts, with its report in the panel; MA9f (S) the profile screens and import list only the profiles left and leave on a site with none, their addresses leading to the flows.
- MA10 Incoming e-mail, in three parts: MA10a (S, no product code) — done #NNNN, its choices made there (`workspace/mail-area-shell.md`, `workspace/mail-area-decisions.md`): what the list, the conversation, New e-mail and the card take from the Workspace, mail with the Workspace off, what the card keeps.
- MA10 goes on: MA10b (M) "Incoming e-mail" as the area's first tab with the list and the conversation, the boxes' settings in the settings, the list out of the Workspace's menu, the card's click opening the e-mail in the area, an answer from the area marking the card answered, an HTML body for a written e-mail (`workspace/mail-compose.md`, `workspace/mail-html.md`), the card as `workspace/mail-area-decisions.md` keeps it.
- MA10 ends: MA10c (M) incoming e-mail with the Workspace off: its own gate, the job's passes, boxes kept in the mailbox itself, the bell.
