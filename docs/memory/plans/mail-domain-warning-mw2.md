---
name: mail-domain-warning-mw2
description: Plan: after MW1's warning (#9303), MW2 shows a Workspace answer's delivery failure on its Sends row (owner 2026-11-13, #9267; architect 2026-11-13)
---

## What is done?
- MW1 (#9303): the warning before a send to a domain that does not exist (`workspace/mail-domain-warning.md`); the dev check of conversation 48 (`workspace/mail-bounce-dev-check.md`).

## What is left?
- Architect (2026-11-13), channel 25: "MW2 (S) The mail queue row and the Sends list row of a Workspace answer read Not delivered when a ws_mail_items bounce answers it (in_reply_to), read at list time for the page's rows only. No new table or column; if the row cannot be tied to the item without one, the step stops and asks. Free after MW1 lands."
- Claim as `[plan:mail-domain-warning] MW2 — Not delivered on the queue and Sends rows`. Close-out: the behaviour into `workspace/mail-domain-warning.md` or a file beside it; this plan deleted.
