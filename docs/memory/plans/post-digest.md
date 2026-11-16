---
name: post-digest
description: Plan (owner 2026-11-16, agent channel poll 82): new posts of a list sent by themselves as an e-mail to a contact group, built from a general flow box and the campaign path; steps DG1 to DG5
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a digest that mails the week's new records to subscribers by itself.

## What is there today, and what is reused?
- Scheduled work is a flow with the start "At a time" (`workflow/decisions-made-product-41.md`); the start's row keeps `last_run_at` (`ws_scheduled_execute()`). Boxes are registered in `ws_flow_kinds()` and `ws_step_kinds()`.
- The flows' e-mail box mails typed addresses or the run's person through the mail queue, never a contact group. A campaign is made by `pg_email_campaign_pick_recipients()` and `pg_email_campaign_insert()` (`includes/fn/email_campaign_create.php`); the campaign job holds back bounced addresses and asks İYS.
- A list page's RSS feed already picks "the records of this list": `pg_sw_form_feed_parts()` applies the list's and its detail page's filters and reads the form's feed fields. It gives XML, not rows.
- No event says that a record was made or changed in the panel, and "live" is a template's convention, not a property of a record (#3746, `designer/collections-decisions.md`).

## What binds it?
- `workflow/decisions-made-product-33.md` and `workspace/workspace-flows-adding.md`: a box is general, never made for one example; one need is a ready flow.
- `workflow/decisions-made-product-30.md` (#6845): a flow keeps no variables between runs.
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.

## What is built?
- The numbered features: `post-digest-features.md` (D1 to D13). Steps: `post-digest-steps.md`. Claim as `[plan:post-digest] <step> — <topic>`.
- In short: not one "digest" box but three general parts: a box that reads a list's records, a contact group as a recipient of the e-mail box (sent as a campaign), and a ready flow that joins them.

## What does the architect decide?
- The mark as a table of flow and record (the plan) against the start's `last_run_at`, and how it stands beside #6845. The table's columns, in DG1's draft PR.

## What does the owner decide?
- The table (asked with the set's other tables). The look of the digest mail and of the two boxes.

## What is left out?
- A subscriber's own choice of how often; several lists in one mail; the digest as a visitor notification (`plans/visitor-push.md`); an introduction written by AI. Each is a later yes.

## Whose look?
- The owner's: the Workspace is a screen he keeps, and the mail is what subscribers get. Pictures from DG2 (the mail) and DG3 (the boxes).
