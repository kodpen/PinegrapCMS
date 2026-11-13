---
name: messages-area-steps-2
description: Steps MA9a-MA9d and MA10a-MA10b of the messages area plan (#8961): campaign profiles turned into flows, incoming e-mail in the area; their order in messages-area-steps.md
---

## Which steps? (order: `messages-area-steps.md`; rules: `messages-area.md`)
- MA9 Profiles into flows (M2b), in four parts: MA9a (S, no product code) — done #9077 (`core/campaign-profile-flow-*.md`; its proposals asked in #9077 under Decision question): every field of a campaign profile against the boxes (trigger, delay and send time, followed profile, purpose and consent, the e-mail), what no box covers, the rule for the moment each profile switches so nothing is lost or sent twice, a dry-run report.
- MA9 goes on: MA9b (M) the converter as pure functions with tests; MA9c (M) the update step that converts, with its report in the panel; MA9d (S) the profile screens and import leave, their addresses lead to the flows.
- MA10 Incoming e-mail, in two parts: MA10a (S, no product code) — done #9077 (`workspace/mail-area-shell.md`, `mail-area-proposals.md`; asked in #9077 under Decision question): what the E-mails list, the conversation, New e-mail and the card take from the Workspace shell and its gate (`workspace/mail-list.md`, `workspace/mail-card.md`), whether mail is read and answered where the Workspace is off, what the card keeps besides its link; proposals to the architect.
- MA10 goes on: MA10b (M) "Incoming e-mail" as the area's first tab with the list and the conversation, the boxes' settings in the settings, the list out of the Workspace's menu, the card's click opening the e-mail in the area, an answer from the area marking the card answered, an HTML body for a written e-mail (`workspace/mail-compose.md`, `workspace/mail-html.md`).
