---
name: workspace-flows-templates
description: Plan (owner request 2026-10-27, draft): a flow template gallery that takes over the scheduled-action templates; cards with a box preview, animated placement on the canvas, a short setup
---

## What is asked?
- Flows get templates like the scheduled actions' ready ones (the form's `saTemplates()`, removed since, the `sa_tpl_*` texts in scheduled.php), shown as a gallery: each card has a row of its box icons, a title, one line, its start (time, by hand, event), box and thread counts, and Use.
- "New flow" offers three starts: empty canvas, describe it to AI, from a template. The editor's box list has "Start from a template" too.
- Use places the template with motion: boxes appear one after another, arrows grow, threads draw, then a setup panel asks only the template's few settings (address, channel, time, person); "Open on canvas" or "Publish now".
- Motion respects prefers-reduced-motion (placed at once). Timings: workspace-flows-visual-spec.md.

## What comes over from scheduled actions?
- Each `sa_tpl_*` becomes a flow template with the same texts, so no new translation for the same sentence (slice 4 of workspace/workspace-flows-scheduled-merge.md: templates become starters).
- Flow-only templates show what a form cannot: tasks that wait for each other, approvals, threads (new customer, quote approval).

## Which steps? (one PR each)
- G1 template format (graph, settings list, category) and the gallery from the scheduled templates. Done (#2161): `workspace/workspace-flows-template-format.md`; welcome, event, forms, orders and chain wait (why there). The AI start of "New flow" waits for F8.

- G2 animated placement and the setup panel. Done (#2173): `workspace/workspace-flows-template-setup.md`.

- G3 flow-only templates. Done (#2196): new customer, quote approval, text review (`workspace/workspace-flows-template-format.md`).

## Where to start?
- All steps done; the plan closes once open question 1 is answered (its decision goes to `workspace/workspace-flows-template-format.md`).

## Which questions are answered?
1. Answered (#2416): yes, in two steps: G3 "Save as template" (a site template row in the built-ins' format; `ws_flow_templates` approved in #3120, `workflow/decisions-made-schema-4.md`), G4 export and import over the API with dry run.
