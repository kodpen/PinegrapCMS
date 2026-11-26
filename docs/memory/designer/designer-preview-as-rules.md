---
name: designer-preview-as-rules
description: What binds "Preview as" (Record, Visitor, Case, "Check all cases" in the designer's Preview): why it exists, what it may never do, which step built which part (PW1 to PW4)
---

## Why does it exist?
- Owner (2026-11-24): one of the seven designer features of `workflow/decisions-made-product-106.md`, "çok detaylı ve profesyonelce", drawn first. A bound page is seen with real data, a chosen visitor and its hard cases before it is on the site.
- Before it the Preview drew widgets as a signed-out visitor with the newest submission or the first product (`designer-live-preview.md`); nobody chose.

## What does it offer?
- A "Preview as" bar at the top of the Preview window: "Record" (a search over the single-record widget's own records), "Visitor" (Signed out, Signed in as the sample account, First visit, Returning), "Case" (Typical, Empty, Long, Sold out, Error) and "Reset" (`preview-as-bar.md`).
- "Check all cases": draws each case the page has and lists where a text leaves its box, a picture is missing or a widget draws nothing; a row opens that case (`preview-as-check.md`).
- The choice is kept per page in the browser; nothing is saved with the design.

## What must it never do?
- Write anything: it stays a background render, with no session, order, mail, counter, cache copy or log line of a visit; the tests assert each (`designer-preview-as-server.md`).
- List or draw a record the operator may not open on its own panel screen; a real customer's address, e-mail and phone are masked in a signed-in view (`designer-preview-as-viewer.md`).
- Search a table by a new statement: the record search goes through the widget's own readers and filters.
- Redefine a `USER_` constant or make a user row for the sample account (`designer-preview-as-visitor.md`).
- Add a table or a column: none was added.

## Which step built which part?
- PW1 (#11516) the pure case model (`designer-preview-as-cases.md`); PW2 (#11546) the server, record picks, search and scope (`designer-preview-as-server.md`); PW2b (#11632) the renderers' case readers (`designer-preview-as-readers.md`); PW2c (#11728) signed in, signed out and member, PW2d (#11746) first visit and returning (`designer-preview-as-viewer.md`); PW3 (#11650) the bar; PW4 (#11761) "Check all cases".
- The drawing: `designer-preview-as-drawing.md`.

## What is not built?
- The "Member of a group" rows: the editor does not know a page's access rules yet, so the bar sends `access_rules` false. The server and renderers already draw a member (`designer-preview-as-viewer.md`); only the bar's rows are missing.
