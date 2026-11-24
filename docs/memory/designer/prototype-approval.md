---
name: prototype-approval
description: Prototype approval start file: a share link asking Approve this page or Request changes, sealed to the draft; which file covers each part, A1 to A8 checked with their PRs
module: prototype_verdicts
---

## What is it?
- A share link of a design's prototype may ask for approval: the customer gives each page "Approve this page" or "Request changes", sealed to the draft they saw. It sits on prototype comments (`prototype-comments.md`) and adds no panel screen.
- Owner (2026-11-20): only what prototype sharing lacked, reviewed by the architect first (`workflow/decisions-made-product-93.md`); the columns and table: poll 206 (`workflow/decisions-made-schema-46.md`).
- Built in eight steps: pure rules #11288, store #11298, link and viewer #11329, publish hold #11359, round two #11364, mails #11377, events and API #11398, close-out (this file).

## Which file covers which part?
- Fingerprints, what changed, a verdict's state, "Answer by", mail timing (pure): `prototype-approval-fingerprints.md`.
- The five `prototype_links` columns, `prototype_verdicts`, deletion, privacy erase and export: `prototype-approval-store.md`.
- The Share prototype dialog's and the API's approval fields, "Review this page", the verdict door, receipt, bell: `prototype-approval-review.md`.
- "Show what changed", the viewer's page list marks, verdicts in the Comments list and the dialog's count: `prototype-approval-round-two.md`.
- "Publish only after approval", every writer reading the hold, role 0's "Publish anyway": `prototype-approval-hold.md`, the sheet's row `designer-publish-sheet-held-row.md`.
- The answer mail and the "Answer by" reminder: `prototype-approval-mails.md`. Events, read-only API routes and MCP tools: `prototype-approval-events.md`.

## Were A1 to A8 checked against the code?
- Yes, at close-out. A1 #11298, #11329 (`pg_proto_link_plan_approval()`; one approver a link, several approvers several links). A2 #11288, #11298, #11329 (`pg_proto_verdict_take()`, sealed with name, link, time and `pg_proto_draft_fingerprint()`).
- A3 #11288, #11329, #11364 (`pg_proto_verdict_state()` `older`, read only). A4 #11288, #11364 (`pg_proto_viewer_changes()`). A5 #11359 (`pg_proto_approval_holds()`, `pg_proto_approval_override_reason()` role 0 only).
- A6 #11329 (receipt), #11377 (answer mail, reminder). A7 #11329 (bell `prototype_verdict`), #11364 (Comments list, "Approved n of m pages"). A8 #11398 (three events; `prototype-verdicts` and `prototype-comments` read routes; `design.prototype_links.*` stay `'mcp' => false`).
- A3's "for an older version" reads "older version" in the designer's list and "Changed since your last review" in the viewer, as the plan's words asked.

## What binds a later change?
- A guest's name and note stay data: plain as a comment, quoted in tasks and events (`pg_proto_event_words()`).
- The approver's e-mail only for this review's mails, never sent to the viewer, an event, the API or a list; erased with the person.
- A mail carries the link's sealed address, never its password; no consent question (the mails answer the person's own review).
- The publish hold is read where a draft goes live, never per path; a new writer of a draft going live joins `tests/prototype_approval_hold_test.php`'s list.

## What was left out?
- A picture on a comment, comments on a live page, a signature drawn by hand, approval of a release bundle as one: each waits for a later yes of the owner.
- Not run on a site: delivered webhooks, flow runs and MCP calls were not watched (`prototype-approval-events.md`).

Related: docs/memory/designer/prototype-comments.md, docs/memory/designer/prototype-approval-review.md, docs/memory/designer/prototype-approval-hold.md
