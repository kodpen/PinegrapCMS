---
name: prototype-approval-features
description: Plan prototype-approval, features A1 to A8: the link asking approval, the sealed verdict, older version, round two, publish rule, mails, the designer's side, events and API
---

## Which features are there?
- (A1) A link may ask for approval: the approver's name, an e-mail (optional), an "Answer by" date no later than the expiry. Several approvers are several links.
- (A2) In the viewer, per page: "Approve this page" or "Request changes" with a note. A verdict is sealed with the name typed, the link, the time and the draft's fingerprint (the `tree_hash` shape of `page_revisions` over the draft's tree and settings: revisions record publishes only, never drafts).
- (A3) A verdict whose fingerprint differs from the draft now reads "for an older version"; worked out on read, never written.
- (A4) Round two: at a verdict the draft's node fingerprints are kept; later the viewer can outline the nodes changed or added since this link's last verdict and list removed ones, beside the comments' states.
- (A5) Publish rule (optional per link): a draft shown by such a link goes live only after an approval of its current fingerprint; the Publish sheet holds it as an experiment or a bundle holds a draft.
- (A6) Mails to the approver's e-mail: the designer answered (at most one an hour per link, the answers counted), the "Answer by" day before with pages undecided; a receipt of each verdict.
- (A7) The designer's side: the verdicts per page in the Comments list and per link in the Share prototype dialog; a bell line to the link's maker for each verdict.
- (A8) Events `prototype.approved` and `prototype.changes_requested` (and `prototype.commented` for a guest comment), their catalogues and payloads; read-only API routes for a design's verdicts and comments; the MCP question below.

Related: docs/memory/plans/prototype-approval.md, docs/memory/plans/prototype-approval-steps.md
