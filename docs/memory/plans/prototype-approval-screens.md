---
name: prototype-approval-screens
description: Plan prototype-approval, how its parts sit: the Share prototype dialog's approval fields, the viewer's Review box and round two, the designer's Comments list and the Publish sheet's held row
---

## Which look do they follow?
- No panel screen is added. Every part sits in a place prototype comments already have and keeps its accepted look: the designer's chrome tokens (`designer/designer-chrome-tokens.md`) in the editor, the viewer's bar and box in the viewer. Architect (2026-11-20, agent channel message 9873, for comments): inside the accepted look, no new look.
- A part the editor or the viewer lacks is asked of the architect, never made (`workflow/screen-described-in-plan.md`).

## How does the Share prototype dialog change? (PA2, PA3)
- Under "Allow comments": "Ask for approval" as a checkbox; checked, it shows in order: Approver's name, Approver's e-mail (optional, "Sent the answers and a reminder"), "Answer by" (a date, no later than the link's expiry), "Publish only after approval" (a checkbox). Checking it turns "Allow comments" on.
- The links list: a link asking approval shows "Approved n of m pages" beside "Comments: n", and "Changes requested" when any page has that verdict.

## What does the approver see? (PA2, PA3)
- The viewer's bar: "Review this page" beside the Comment button, only on a link asking approval. It opens a box in the comment box's place and size: the page's name, two choices "Approve this page" and "Request changes", a note (optional for Approve, required for Request changes), "Your name" (the comment box's stored name), Send.
- After a verdict the bar reads "You approved this page on {date}" or "You asked for changes on {date}"; when the draft changed since, "Changed since your last review" with "Show what changed".
- "Show what changed" outlines changed and added elements in the frame, lists removed ones in the bar's one sideways row (as "Not on the page any more"), and keeps the comments' pins and states.
- The bar's page list marks each page: Approved, Changes requested, Not reviewed, Changed since.

## What does the designer see? (PA3, PA4)
- The Prototype view's Comments tab: each page group's head carries the verdicts, one per link asking approval (its hint, the verdict, the date, "older version" when the draft moved); a Request changes note shows as the first thread of that page, without a pin.
- The Publish sheet: a page waiting for approval is a held row, "Waiting for approval" as its label, the tooltip naming the link's hint and the approver; an administrator gets "Publish anyway", which asks a reason.

## Which words?
- "Approve this page", "Request changes", "Answer by", "Waiting for approval", "Changed since your last review"; never "verdict", "fingerprint", "hash" or a column's name.

Related: docs/memory/plans/prototype-approval.md, docs/memory/plans/prototype-approval-steps.md
