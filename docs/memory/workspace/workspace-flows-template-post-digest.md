---
name: workspace-flows-template-post-digest
description: Flow template post_digest, "Digest of new posts" (post-digest DG4): its five boxes, when it is offered, what its setup asks, the setting types page, group, subject and day
---

## What does it do?
- Gallery template `post_digest` (category Site) in `ws_flow_templates()`: a weekly start (Monday 09:00 from tomorrow), "Read the records of a list" with "Only records new since the last run" on, a Condition `{{node.read.count}}` equal to 0 whose yes arrow goes to End, and on no an e-mail box to a contact group (`recipient` group, `purpose` marketing, `records` read).
- A week with no new record ends before the e-mail box: no mail (D9). The mail itself, the window and Try: `workspace-flows-email-group.md`, `workspace-flows-records-window.md`.

## When is it offered?
- Only with the fill's `records_mail`: `ws_flow_templates_for()` sets it when `ws_flow_mail_groups()` (role below 3, so D12) and `ws_flow_records_pages()` are both not empty.

## What does its setup ask?
- Day of the week, time, list page, contact group, subject (in that order, the boxes' order). The list page's help is the left-out sentence (`ws_flow_records_left_out_note()`, D3).
- Setting types added for it in `ws_flow_template_setting_types()`: `page` and `group` (an id above 0; the page open to everyone and the group's right are checked at publish), `subject` (spaces folded, not empty, at most 250 characters), `day` (1 Monday to 7 Sunday, kept as a list of one in the start's `days`).
- The setup panel (`setupControl()` in workspace_flow.js) draws page and group as selects of `FL.steps.list_pages` / `mail_groups`, day as a select of the week's days.

## Tests?
- `test_workspace_flow_templates_post_digest` and `_post_digest_answers` in `tests/workspace_flow_templates_test.php`. No run on a database.
