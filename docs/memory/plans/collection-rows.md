---
name: collection-rows
description: Plan (owner 2026-11-16, agent channel message 5667): a "Rows" field of a collection, a record's own rows added, removed and ordered on its form; steps RW1 to RW7
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. The proposal is agent channel message 5663.
- A record gets rows of its own: a recipe's steps, an event's sessions, a product page's questions. The editor adds, removes and orders them on the record's form; the designer draws them on the record page.

## What is there today?
- A collection is a form (`custom_form_pages.collection`); a field's kind is number, image, reference, product, page or slug (`designer/collection-fields.md`). No kind holds rows.
- The way round: a second collection whose records point at the parent with a reference field, a number field for the order, and on the record page a form list filtered "is equal to the shown record" and sorted by that number (`designer/collection-lists.md`). The editor types each row in the other form and picks the parent by hand; the panel's record screen shows no rows.
- A reference keeps codes in `form_data.data`, several joined in a text box. `pg_coll_reference_ok()` accepts only the newest 500 complete records of the target.
- Deleting a record (`pg_submitted_form_delete()`) leaves the records that point at it pointing at nothing. A record's History (`record_revisions`) holds its own values only.

## What is built?
- The numbered features: `collection-rows-features.md` (R1 to R18). Steps: `collection-rows-steps.md`. Claim as `[plan:collection-rows] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: no new storage. A row is a record of a child collection; the parent's rows field keeps the rows' codes in order, so the order and the membership sit in one value that the parent's History already records, and the child needs no link field or order field.
- This differs from message 5663, which put a link and an order field on the child: a number field of the child that the editor numbers by itself could be a field the site uses for something else.

## What happens to a record's rows when it is deleted? (owner, 2026-11-16, agent channel poll 66)
- Owner (2026-11-16, agent channel poll 66, message 5752): "Satırlar da birlikte silinsin". The other choice was that the rows stay as records nobody lists. The confirmations name how many rows go (R11).

## What does the architect decide?
- The codes on the parent (the plan) against a link and an order field on the child (message 5663). The limit of 100 rows.

## What is left out?
- Rows inside rows; a row's file upload on the parent's form (it opens the row's own screen, R10); a row moved to another record; the rows' text in the parent's site search entry. Each is a later yes.

## Whose look?
- The rows editor is a new control on the record screens: the owner's, close-up pairs from RW2 and RW5. The Kind choice and the canvas sample are the architect's.
