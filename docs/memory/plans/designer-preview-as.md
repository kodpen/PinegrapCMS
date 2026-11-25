---
name: designer-preview-as
description: Plan (owner 2026-11-24): the designer's Preview with a chosen record, visitor and case (empty, long, sold out, error) and "Check all cases"; nothing written by it; steps PW1 to PW5
priority: 7
---

## What is asked?
- Owner (2026-11-24): the architect's seven additions all go in, "çok detaylı ve profesyonelce", drawn first (`workflow/decisions-made-product-106.md`). This is the first: a bound page seen with real data before it is on the site.

## What is there today?
- The canvas draws samples (`designer/canvas-sample-overview.md`). The Preview draws widgets on the server as a signed-out visitor; a single-record widget gets the newest submission or the first product (`designer/designer-live-preview.md`). Nobody chooses.

## What is built?
- A "Preview as" bar at the top of the Preview window: three choosers and "Reset".
- Record: on a page with a single-record widget, a search by name over that widget's own records (a form's submissions, a group's products, a calendar's events); the chosen one is drawn.
- Visitor: Signed out (as today), Signed in (the sample account), Member of a group for a page with access rules, First visit or Returning where the page has audience conditions.
- Case: Typical, Empty (a list with no record, an empty cart, a search with no result), Long (the longest title and text among the records), Sold out (catalog), Error (a form sent with a required field missing).
- "Check all cases" draws each case the page has in turn and lists the ones where a text leaves its box, a picture is missing or a widget draws nothing; a row opens that case.
- The choice is kept per page in the browser; nothing is saved with the design.

## What must it never do?
- It stays a background render: no session, order, mail, counter, cache copy or log line of a visit is written (the tests assert each).
- A record is listed and drawn only when the operator may open it on its own panel screen; a real customer's address, e-mail and phone are masked in a signed-in view.
- The search goes through the widget's own readers, never a new statement on its table.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- PW1 (M) The case model, pure: per widget kind the cases it has and what each sets (record, visitor, flags). Done (#11516).
- PW2 (M) after PW1: the server: `preview_widgets` takes a case per widget; the record search with the rights check; the never-writes tests: the record cases (chosen, long, sold out) drawn, the flags and the visitor held for renderers (`designer/designer-preview-as-server.md`). Done (#11546).
- PW2b (M) after PW2: the renderers read the held flags (empty lists, search and cart; long lists; a form sent with a required field missing) and the visitor (signed in as the sample account, member, first visit, returning), inside the same scope.
- PW3 (M) after PW2 and DD5: the bar and its three choosers.
- PW4 (M) after PW3 and PW2b: "Check all cases", measured in the Preview's frame.
- PW5 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect. The drawing: `designer-drawings.md`.
- Claim as `[plan:designer-preview-as] <step> — <topic>`.
