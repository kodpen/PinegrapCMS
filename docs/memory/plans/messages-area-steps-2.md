---
name: messages-area-steps-2
description: Steps MA9a-MA9f and MA10a-MA10c of the messages area plan (#8961): campaign profiles turned into flows, incoming e-mail in the area; decisions in messages-area-decided-2.md
---

## Which steps? (order: `messages-area-steps.md`; rules: `messages-area.md`; decided: `messages-area-decided-2.md`)
- MA9a (S, no product code) — done #9077 (`core/campaign-profile-flow-*.md`): every field of a campaign profile against the boxes, what no box covers, the switch moment, the dry-run report.
- MA9b (M) The e-mail box sends as a campaign does: an optional sender (From name and address, Reply-to, BCC); a page body with a base tag, absolute links, its `^^fields^^` filled from the run and the recipient's contact, the page's PHP given the run's record as `data`; for marketing the `?t=` codes, the commercial footer and a group's opt-out (`core/campaign-profile-flow-gaps.md`). Over about 1,000 product lines the marketing part is a PR of its own.
- MA9c (M) The Wait box and the events: a time of day; in until mode "If the moment has passed: go on / end the run"; `calendar_event.reserved` with `start_at` and its contact; the events' mail values as start-box outputs.
- MA9d (M) The converter as pure functions with tests, and the dry-run report worked out from the rows, nothing written (`core/campaign-profile-flow-switch.md`, `-dry-run.md`).
- MA9e (M) The update step that converts at the switch moment, its report in the panel; safe to run twice. Shown to the owner on a copy of a running site before it lands.
- MA9f (S) The profile screens and the import list only the profiles left and leave on a site with none; their addresses lead to the flows.
- MA10a (S, no product code) — done #9077 (`workspace/mail-area-shell.md`, `mail-area-proposals.md`).
- MA10b (M, the Workspace on) "Incoming e-mail" as the area's first tab with the list and the conversation; the Mailboxes card in the settings; the list out of the Workspace's menu, its addresses leading to the area; the card as decided, its click opening the e-mail in the area; an answer from the area marking the card; an HTML body for a written e-mail (`workspace/mail-compose.md`, `workspace/mail-html.md`).
- MA10c (M) Mail with the Workspace off (owner, #9203): `ws_mail_on()` and its callers (the tab, the panel actions, the job's passes, the frame, the Mailboxes card, the API's mail routes); a new box kept in the mailbox itself; the notices in the panel's bell (AF4c of `automation-flows-menu-steps-2.md`); a test that every `ws_mail_*` entry asks the gate.
