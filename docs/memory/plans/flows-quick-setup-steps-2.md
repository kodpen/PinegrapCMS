---
name: flows-quick-setup-steps-2
description: Steps QS1b, QS6, QS7 of the flows-quick-setup plan, the architect's decisions on QS0's open questions (#9082): "Only when" on a record field, the drawn steps with no box, the rail, the approval recipe
---

## What is decided after QS0? (architect, 2026-11-13, #9082)
- "Only when" on a field of the start's record stays as built: one Read a record box before the condition boxes, shared by every such condition of the start. The owner's drawing asks an order's total, which the event does not carry. In the Summary it is drawn with the "Only when" line, not as a step the person chose.
- The rail stays as built: the web address start under By hand, ERP sales quotes under Cart and quotes, store credit and user accounts under Customers, bounces under Content and campaigns, `join`, `mention` and `row` under Workspace.
- Of the four drawn steps with no box, three become the steps below. Each is a general box added by `workspace/workspace-flows-adding.md`; its drawn step stays hidden until it lands (`workspace/workspace-flows-quick-setup-map.md`).
- Give an offer code waits: offers give no single-use code per person today, and a table or column for one is the owner's. It comes back as a question with the offers' facts; the drawn step stays hidden.
- The recipe "Ask for approval on a big order" is the drawn one since QS1b: `approve_order` (approval, change the order's status, e-mail).

## Which steps?
- QS1b (S) done #9223. The change box on the run's record: `change.id` takes one value such as `{{trigger.record_id}}`, as Read a record's `id` does; no new kind. With it the drawn "Change the order's status" and the drawn approval recipe.
- QS6 (M) Send an SMS, a new step kind: to the mobile of the record's person (as the e-mail box's `record_person`) or a typed number; a text with values; info or marketing, marketing under the consent rule the SMS campaigns keep; sent through `pg_sms_send()` (`includes/fn/sms.php`); output `sent`. Offered where the site can send an SMS.
- QS7 (S) Add to or take out of a contact group, a new step kind: the run's contact or a value's, the group chosen. Adding never sets a contact's marketing consent. A flow started by `contact_group.joined` on a group does not start again from its own add to that group (a test).

## In which order?
- QS1b before QS3. QS6 and QS7 any time after QS1, each alone; they do not hold QS2 to QS5. Claim as `[plan:flows-quick-setup] <step> — <topic>`.
