---
name: decisions-made-schema-46
description: Decisions list, schema part 46 (2026-11-21 on): the tables of the walk-in queue, volunteer shifts, the set-up journey and prototype approval, five columns on prototype_links (poll 206)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-45.md`.

## Do the plans of polls 201, 202, 204 and 205 get their tables? (owner, 2026-11-21, agent channel poll 206, channel message 11148)
- "Evet, dördü de eklensin": ten tables and five columns, each added by its own plan's table step as a migration step in the open version. No existing column changes its meaning.
- Not chosen: "Hayır, eklenmesin" (the plans would have stopped after their first steps, which need no schema).

## Which tables does the walk-in queue get? (architect, 2026-11-21)
- `waiting_places`, `waiting_desks`, `waiting_tickets`. Not `queue_…`: beside `push_queue`, `api_webhook_queue` and `ws_scheduled_queue` that word reads as a job queue.
- A place's services (each with its letter), hours, daily cap, notices and keep period are JSON on the place's row. A desk names the services it serves. A ticket names its place, the service's key, the desk that called it and a booking's id (0 for a walk-in).
- No column is added to the bookings' tables: an arrival is a ticket naming the booking.

## Which tables do volunteer shifts get? (architect, 2026-11-21)
- `volunteer_programmes`, `volunteers`, `volunteer_documents`, `volunteer_shifts`, `volunteer_signups`.
- A programme's roles and coordinators and a shift's roles with their counts are JSON on their rows; a sign-up names the role's key.
- Hours sit on the sign-up row; hours worked outside a shift are a sign-up row with no shift.
- A document row stands without a file: whether one is kept is the owner's (agent channel poll 208).

## What does the set-up journey get? (architect, 2026-11-21)
- One table for the agency summary's lines: the text, the customer's answer, when it was answered, the order.
- No `config` column: that table takes none (`data/config-table-no-varchar.md`). The site's purpose, the go-live time, the hidden steps and the summary link's hash and expiry are one JSON row in `config_text`.
- First-visit hint marks go into `user.tours_seen` as `hint:<screen>` keys, only for screens in the journey's own catalogue; no column.

## What does prototype approval get? (architect, 2026-11-21)
- Five columns on `prototype_links`: `approval`, `publish_rule`, `approver_name`, `approver_email`, `answer_by`. One to one with the link and read with it on every load of the viewer, so no table of their own.
- One table `prototype_verdicts`: the link, the design, the page, the verdict, the name typed, the note, the draft's fingerprint, the node fingerprints, the time.

## Who sets the columns?
- The architect. Each table step lists its columns, types and keys in its PR body and asks the architect before it is marked ready; what is agreed is added to this part.
- Every table that holds a person registers with the five privacy lists and `pg_contact_holders()` in the same step.

Related: docs/memory/workflow/decisions-made.md, docs/memory/data/config-table-no-varchar.md
