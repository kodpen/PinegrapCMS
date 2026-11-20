---
name: workspace-flows-records-box
description: A list's new posts mailed by themselves to a contact group (records box, contact group, ready flow); features D1 to D13 checked against the code with their PRs
---

## What is it?
- A list's new records sent once a week as one e-mail to a contact group, built from three general parts, not one "digest" box (`workflow/decisions-made-product-33.md`): the box "Read the records of a list" (`list_records`), the e-mail box's recipient "A contact group" (sent as a campaign), and the gallery flow "Digest of new posts" (`post_digest`) that joins them.
- Owner (2026-11-16, agent channel poll 82): asked as a digest that mails the week's new records to subscribers by itself. Architect (2026-11-16, agent channel message 6553): no table and nothing kept on the flow (`workflow/decisions-made-product-30.md`).

## Where is each part written up?
- The records box, its pick, window, record date, left out and Try: `workspace-flows-records-window.md` (#9978, canvas #10176).
- The contact group as a campaign, the mail's body and Try: `workspace-flows-email-group.md` (#10026, canvas #10176).
- The ready flow and its setup: `workspace-flows-template-post-digest.md` (#10491).

## Which feature is met where?
- D1 a general box reading a list page's feed records, count and records, 50 at most (`WS_FLOW_RECORDS_MAX`): #9978.
- D2 the feed's own pick (`pg_sw_form_feed_pick()`); a page not open to everyone refused at publish and at each run (`ws_flow_records_page_refusal()`): #9978.
- D3 "Only records new since the last run", for a flow that starts at a time; a record's date is the later of `submitted_timestamp` and the list's one "at most now" date field, else the left-out sentence (`ws_flow_records_left_out_note()`): #9978, the field counted once #10095.
- D4 the window from the earlier counted run's `ws_scheduled_runs.started_at` (in) to this run's (out) (`ws_flow_records_window()`): #9978.
- D5 the first window opens at the live version's `published_at`: #9978.
- D6 a start that fires twice sends once: the start's claim and `ws_flow_runs` key `uq_start` (flow_id, start_key `sched:<id>`), taken before any box runs; nothing added.
- D7 recipient `group`: one campaign through `pg_email_campaign_pick_recipients()` and `pg_email_campaign_insert()` (`ws_flow_mail_group_do()`): #10026.
- D8 the box's text and the records block, every text escaped, http(s) pictures and links only (`ws_flow_mail_digest_html()`): #10026.
- D9 no record, no mail: the e-mail box sends nothing at count 0, and the ready flow's Condition ends first: #10026, #10491.
- D10 the gallery flow `post_digest`, setup asks day, time, list page, group, subject: #10491.
- D11 Try: the records box shows its records and left-out titles; the e-mail box mails only the person trying; no window moves: #9978, #10026.
- D12 the group recipient and the template only for roles below 3 (`ws_flow_mail_group_refusal_for()`, `ws_flow_mail_groups()`): #10026, #10491.
- D13 a box saved before keeps its settings (`group_id` 0, `records` ''); nothing runs without such a flow: #9978, #10026.

## What is not met?
- No gap against D1 to D13 was found in the code at the close-out (2026-11-20). Nothing was run on a database: the window, the campaign and the template are checked by unit tests and reading.

## What is left out, each a later yes?
- A record set live after its date has passed where no live moment is kept (not sent, and the box says so); a subscriber's own choice of how often; several lists in one mail; the digest as a visitor notification (`platform/visitor-push-rules.md`); an introduction written by AI.

## Whose look?
- The mail: Owner (2026-11-16, agent channel poll 109): "Evet, böyle çıksın". The two boxes on the canvas: Owner (2026-11-17, agent channel poll 126): "Evet, böyle çıksın".
