---
name: decisions-made-product-22
description: Decisions list, product part 22 (2026-11-03 on): the panel's own table driver replacing DataTables
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## DataTables leaves the panel; a Pinegrap table driver replaces it (2026-11-03)
- Owner (2026-11-03): DataTables goes soon; the panel's own table driver takes its place and is being designed locally by the owner, who brings its plan. Until it lands everything continues as today: list fixes and server-side paging (`ui/server-paged-lists.md`) go on, nothing waits for the driver. Reason: the lists must keep working while the driver is designed; the driver takes over the same row actions.
