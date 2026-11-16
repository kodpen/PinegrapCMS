---
name: designer-prototype-comments-rules
description: Prototype comments: what a later change must keep (a deleted link takes its comments, a deleted page's comments stay, personal data erase), and the architect's schema answer
---

## What must a later change keep?
- A `prototype_links` row is never deleted today. A step that one day deletes one deletes that link's comments in the same step: a reused id would show one customer's comments on another's link.
- A deleted page's comments stay until the design is deleted; accepted, no separate sweep.
- Personal data erase: a guest's typed name ties to no person, so the erase engine cannot reach it; the designer deletes the comment or the link's comments by hand. A deleted user's staff answers get a `pg_privacy_erase_tables()` entry in PN3.

## Who approved the schema?
- Architect (2026-11-16, agent channel message 6207): the table and `prototype_links.allow_comments` as built in 11.0.225; idx_page is `page_id, id` so the heartbeat's stamp is the newest id; no second column on the link (P15's hourly bell is a `waf_rate_exceeded()` counter on the link id).

Related: docs/memory/designer/designer-prototype-comments-door.md
