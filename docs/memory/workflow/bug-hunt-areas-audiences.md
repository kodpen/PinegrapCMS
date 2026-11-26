---
name: bug-hunt-areas-audiences
description: Bug hunt of contact groups filled by rules (#6158 to #6418), named by the architect (2026-11-26, request 3055): what was read, the four faults fixed in #11789, what was found clean and what was asked
module: audiences
---

## What was the area, and what was read?
- Architect, agent channel 2026-11-26 (request 3055): contact groups filled by rules. Not `page_audience.php`.
- Read: `includes/fn/audiences.php` (catalogue, cleaning, builder, recompute, switch review, editor helpers, pickers), `includes/fn/audience_doors.php`, `includes/panel/audiences.php`, the rules part of `edit_contact_group.php`, the API refresh and members handlers, `customers.php` create.
- Also: the merge block of `includes/fn/contacts.php`, `pg_fs_confirm()`, `custom_form.php`'s group loop, the flow contact group box, `includes/import/write_contact.php`, the erasure table list, and the `audiences_*` tests.

## What was fixed? (#11789)
- Two passes of one group at once (job tick and Refresh now or the API refresh) wrote each new rule row twice and announced each join twice: now a per-group lock (`core/audiences-group-loop.md`).
- A rule naming its own group, directly or through another dynamic group, flipped every pass and fired the joined triggers for everyone: refused at the save, a failed run in the recompute (same file).
- Cancelled orders counted in the order conditions: now complete and exported orders only.
- The Shopify customers import wrote hand rows into a dynamic group named as a tag: now skipped (`core/audiences-doors.md`).

## What was found clean?
- Every value reaches SQL as a placeholder; a contact field is a catalogue name; picker search is bound; `NOT IN` sub-queries read NOT NULL or `<> 0` columns, so no NULL empties them.
- Day windows count back from one `$now` per pass; membership dates use the site-zone day and calendar days.
- Recompute: only rule rows written; hand, form and product rows never touched; kind re-read before each write; rule rows of deleted contacts go on the page that covers them; a change of rules restarts the pass; a merged contact keeps each row's source; erasure deletes the contact and its rows.
- Doors: contact card, bulk Organize, imports, form groups, double opt-in, storefront joins, flow box, API and MCP all skip or refuse a dynamic group; a join is announced once and not for a contact already in.
- Count preview: role below 3, token checked, nothing written; Refresh now: contacts right, group access, token.

## What was asked, not built?
- A rule naming a deleted group: "not in" it then selects every contact (and fires joined for all). Refuse the delete while a rule names it, or treat it as a failed run?
- The contact, account and Shopify imports do not announce joins of hand groups, so "joined a group" flows and profiles do not start for an import. Should they (a large import would start thousands)?

## Which issues were opened?
- None.
