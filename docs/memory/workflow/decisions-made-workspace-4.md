---
name: decisions-made-workspace-4
description: Decisions list, workspace part 4 (2026-10-30 on): webhook URL digits exception, CRM seams 10 (N-days start, lead_source), "Edit here" on the AI draft card, flow run notices per chain
---

## May a thread end in a webhook box's URL? (#3410)
- Decided (architect, 2026-10-30; plan `workspace-flows-data-passing.md` item 1 against the data-threads and visual-spec rule): #2414-1b stays as a narrow, threaded exception. Only number-typed outputs may be threaded into the path or query part of the URL chip; host and scheme never take a value; the value is URL-encoded and the run refuses the box with its reason when it is not digits. The thread rule stays whole: the URL field joins the value fields for number threads only, so every reference still draws its thread. Every other address or recipient field never lights up and takes no thread. No schema.

## How does a customer with no order in N days produce a follow-up? (#3416, part 2)
- Decided (architect, 2026-10-30, CRM seams step 10): a Flows start "customer has not ordered for N days", N in the start box, scanned within the existing hourly budget; it fires once per customer when the threshold is crossed and not again until a new order resets it (de-duplicated on customer and last order); the flow decides what happens (task, notify, post…). Not a hard-wired hourly task and no site setting: no schema, and the site keeps control through its flows. It follows the queued role-3 and D9 Flows work.

## Is `contacts.lead_source` picked from `referral_sources`? (#3416, part 3)
- Decided (architect, 2026-10-30): yes: a select over `referral_sources` on add and edit contact and validation in the API; old free-text values are kept, shown as they are and stay selectable for that contact until changed. No schema. The visit event's `ws_events.contact_id` (part 1) is in `decisions-made-schema-5.md`.

## What may "Edit here" on the AI draft card change? (#3444)
- Decided (architect, 2026-10-30, plan `workspace-flows-look.md` V7b): open first, then edit in place. On a waiting card "Edit here" runs the same `ws_flow_draft_open()` as "Open on canvas" (the flow draft is created, or the patch made on the flow's draft; the card becomes applied with `flow_id`), then the picked box's label and settings form opens in the card and saves through `ws_flow_save()` with `draft_rev` (409 on a moved draft); on an opened card it edits directly. The button says it opens the draft. The stored proposal (`ws_ai_changes` snapshot) is never edited: the AI's proposal stays the audit record. Shown only where opening would pass (the asker with `ws_can_schedule()`, `edit` on a patch's flow); box try stays the flow owner's (F4e). No schema.

## How many inbox lines do flow runs write? (#3526)
- Decided (architect, 2026-10-30, after the owner's report #3371): one line per chain and a per-flow choice, no schema. A run whose chain has a root run updates the root's unread `flow_finished` line (count and last state; a failure turns it into the failure line that opens the failed run; a run ending after the line was read writes a new one; push only for the root line and the turn to failure); the root id goes in the run's `context` JSON with `start_key` as fallback; only runs of flows with the root's owner are folded; event-started and scheduled runs are not. The start box's config gets "Tell me: every run / only when a run fails / never" (one key in `ws_flow_graph_normalize()`, read by `ws_flow_run_tell_owner()`), default "every run" for every start kind so running sites change nothing until the owner chooses; in a chain the root flow's choice decides. Webhook run events and the runs list are unchanged.

Related: docs/memory/workflow/decisions-made-workspace-3.md, docs/memory/workflow/decisions-made.md
