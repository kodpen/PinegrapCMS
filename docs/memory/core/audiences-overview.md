---
name: audiences-overview
description: Contact groups filled by rules as a whole (owner 2026-11-02): why they exist, the decisions that bind them, and which file holds each part (rules, recompute, editor, doors, token, joined triggers)
module: audiences
---

## What is a group filled by rules?
- A contact group with `kind = 'dynamic'` and a rule set (`contact_groups.rules`): orders, visits, consent, membership, form records, subscriptions, returns, contact fields and other groups decide who is in. It is used wherever a group is used today: campaigns, SMS campaigns, Flows, webhooks, campaign profiles, the page audience conditions, the API and MCP, exports.
- Why (owner, 2026-11-02): "ordered in the last 90 days", "membership ends this month" or "left a cart" existed only as a fixed contact-list filter that no send, flow or page could use; every reader of a group becomes useful once a group can fill itself.

## Which decisions bind it?
- A rule only selects: every marketing send still checks consent and İYS per contact (`platform/sms-and-iys-rules.md`).
- Rules are a catalogue compiled by a pure builder with placeholders; there is no free SQL ([[audiences-rules]]).
- A dynamic group is not edited by hand: the tick boxes, imports, forms, product sales and the API refuse or skip it ([[audiences-doors]]).
- The recompute touches only `source = 'rule'` rows; hand, form and product rows stay, and only the switch review removes or converts them where the user confirms it (#5033, #6319, `workflow/decisions-made-product-25.md`). A merge keeps each row's source.
- Visit conditions read the order a visit made inside an indexed time window, never a per-contact scan of raw rows (`visitors/high-volume-counter-rules.md`); the experiment bucket is not kept per contact and is not a condition.
- Every change, by a door or a recompute, goes through `pg_announce_contact_group_members()`; the "joined a group" triggers fire once per joined contact from there (`workflow/decisions-made-product-27.md`).
- Erasure deletes the contact's rows; a dynamic membership is derived and is not restored.

## Where is each part?
- Columns, catalogue, SQL builder: [[audiences-rules]] (11.0.155, #6158).
- Recompute in the general job, Refresh now: [[audiences-recompute]] (#6181, #6307).
- Rule editor, count preview, switch review: `ui/contact-group-rules.md` (#6223, #6418).
- Doors that refuse a dynamic group, readers, merge: [[audiences-doors]] (#6249, #6307, #6418).
- Page condition `__audience_group_<id>`: `designer/audience-conditions.md` (#6598).
- Flow event `contact_group.joined`: `workspace/contact-group-joined-event.md` (#6651).
- Campaign-profile action `contact_group_joined`: `ui/email-campaign-profile-shared-form.md` (#6659, `workflow/decisions-made-schema-10.md`).

Related: docs/memory/core/contact-holders.md
