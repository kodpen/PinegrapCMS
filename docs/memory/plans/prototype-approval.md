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

## What did the architect answer? (2026-11-20, agent channel messages 11145, 11146)
- Five columns on `prototype_links` (`approval`, `publish_rule`, `approver_name`, `approver_email`, `answer_by`), read with the link, and a table `prototype_verdicts`; owner's yes, poll 206 (`workflow/decisions-made-schema-46.md`).
- The publish rule (A5) is a third hold beside the experiment's and the bundle's (`pg_experiment_variant_hold_message()`, `pg_page_bundle_hold()`), read where the server already refuses a held draft; not written per path. The writers it must reach: the Publish sheet's save, the API's publish and schedule routes and their MCP tools, the scheduled publish job (`designer/page-schedule.md`), a release bundle's publish, an experiment applying its variant, content proposals' approval.
- Override: role 0 only, a reason required, written to the Site Log with the link and the page; no new right.
- MCP: a design's verdicts and comments as read-only tools, the guest's words quoted as data; no MCP tool writes a verdict or makes a link.
- The approver's mails ask no consent question: they answer the person's own review, hold no promotion and go only to the address the designer typed.

## What is built?
- Features: `prototype-approval-features.md`. Steps: `prototype-approval-steps.md`. Screens: `prototype-approval-screens.md`. Claim as `[plan:prototype-approval] <step> — <topic>`.

## What is left out?
- A picture on a comment, comments on a live page, a signature drawn by hand, approval of a release bundle as one: each a later yes.
