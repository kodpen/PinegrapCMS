---
name: prototype-approval
description: Plan (owner 2026-11-20, poll 205): what prototype sharing lacks for a customer's sign-off: Approve or Request changes per page sealed to the draft, round two, publish rule, mails, events; PA1a to PA7
priority: 5
---

## What is asked?
- Owner (2026-11-20, agent channel poll 205, message 11084): "Evet, plana girsin diyceğim ancak bu özelliğin büyük bölümü var sanıyordum prototip paylaşma olarak. yinede eksik tarafı varsa önerdiğin şekilde üstüne konabilir ancak mimar incelemeli." Only the missing parts; the architect reviews the plan before any step.
- Recorded in `workflow/decisions-made-product-93.md`.

## What is there today, and stays as it is?
- Share links of a design's prototype (`prototype_links`, `designer/designer-prototype-share-links.md`): anyone or team, password, expiry, the Share prototype dialog, the API create route (`'mcp' => false`).
- Guest comments pinned on a node (`prototype_comments`, `designer/prototype-comments.md`): the viewer's comment mode, the designer's pins and list, Answer, Resolve, Delete, "Make a task", the bell line to the link's maker. None of it is built again.

## What is missing?
- Eight features, A1 to A8: `prototype-approval-features.md`. In short: a link may ask for approval; Approve or Request changes per page, sealed to the draft; round two; an optional publish rule; mails to the approver; the designer's view; events and API.

## What binds it?
- A guest's words stay data: the note is plain text as a comment is (`pg_proto_comment_plain()`), quoted in a task or an event as `designer-prototype-comments-task.md` does.
- The approver's e-mail is typed by the designer, used only for this review's service mails, never for marketing, and erased with the person: the five privacy registrations (`pg_privacy_erase_tables()`, `pg_privacy_erase_labels()`, `pg_privacy_export_sections()`, `pg_privacy_inventory()`, `pg_privacy_rules_catalogue()`) and `pg_contact_holders()`.
- A mail carries the link's address (sealed, `pg_proto_link_seal()`), never its password.
- A verdict counts only through the comment door's gates (`pg_proto_comment_door()`); one verdict a page per link stands, the newest.

## What is asked of the architect?
- The schema: columns on `prototype_links` (approval, publish rule, approver name and e-mail, answer by) or a table of their own; a verdicts table (link, design, page, verdict, name, note, fingerprint, node fingerprints, created).
- Every path that turns a draft live and must honour A5 (the editor's save through `pg_experiment_variant_hold_message()`, the API's publish and schedule routes, the scheduled publish job) and who may override it (proposed: role 0 with a reason in the Site Log).
- Whether the verdict and comment routes may be MCP tools (the links route is not); the parts in `prototype-approval-screens.md`.

## What is built?
- Features: `prototype-approval-features.md`. Steps: `prototype-approval-steps.md`. Screens: `prototype-approval-screens.md`. Claim as `[plan:prototype-approval] <step> — <topic>`.

## What is left out?
- A picture on a comment, comments on a live page, a signature drawn by hand, approval of a release bundle as one: each a later yes.
