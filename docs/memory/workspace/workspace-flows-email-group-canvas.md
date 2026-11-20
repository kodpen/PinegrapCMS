---
name: workspace-flows-email-group-canvas
description: Flow e-mail box to a contact group on the canvas: the "A contact group" fields, and no BCC (field not drawn, writes refuse group + BCC, run time sends without it)
---

## What does the canvas draw? (#10176)
- `emailFields()` offers "A contact group" while `FL.steps.mail_groups` (`ws_flow_mail_groups()`: every group, role below 3) is not empty or the box writes to one; `emailGroupFields()`: group, why, "Records drawn after the text" (the flow's `list_records` boxes or none). The box's line: `mailGroupMeta()`.

## Does a group e-mail take a BCC?
- Architect (2026-11-20, agent channel message 10236): no. the campaign goes to each contact apart, so a BCC would get one copy per contact. The canvas draws no BCC field for "A contact group" and empties a kept BCC when the box is opened or switched to a group.
- Every write refuses group + BCC with the reason, never drops it: `ws_flow_mail_group_bcc_problem()` in the publish check's problems, `ws_flow_mail_group_bcc_refusal()` in `ws_flow_save_check()` (canvas, API, Pinegrap AI's draft). Run time does not: a box published with one before sends without it; the campaign and Try carry no BCC (#10605).
