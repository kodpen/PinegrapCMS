---
name: decisions-made-product-22
description: Decisions list, product part 22 (2026-11-01 evening on): the panel's own table driver replacing DataTables; the Visitors overview
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## DataTables leaves the panel; a Pinegrap table driver replaces it (2026-11-03)
- Owner (2026-11-03): DataTables goes soon; the panel's own table driver takes its place and is being designed locally by the owner, who brings its plan. Until it lands everything continues as today: list fixes and server-side paging (`ui/server-paged-lists.md`) go on, nothing waits for the driver. Reason: the lists must keep working while the driver is designed; the driver takes over the same row actions.

## Visitors overview: one screen, a daily summary every day, the channel rule, Search Console joined to the pages (2026-11-01)
- Owner (2026-11-01, `plans/visitors-overview.md`): (1) the Visitors menu opens one overview screen with a period bar and sections; the saved-report builder stays as "Saved reports"; (2) the daily visitor summary is written for every day through yesterday whatever the retention period, the summary switch stays, retention alone decides removal; reason: every breakdown of the last 90 days scanned the raw table; (3) the channel rule is completed (paid, e-mail, AI assistants, organic, social, referral, direct) and the search-engine list brought up to date (the ENUM: `decisions-made-schema-9.md`); (4) the order and shipping report links stay in the Visitors flyout, a person with only the e-commerce reports right reaches them from the Ecommerce menu and sees no visitor figure; (5) the dashboards are not touched by the plan; (6) Search Console not linked: a small card linking to its settings; (7) alerts in the first phase, computed at render time, no table and no job.
