---
name: decisions-made-product-24
description: Decisions list, product part 24 (2026-11-01 on): the Visitors overview screen, a daily summary every day, the channel rule, Search Console joined to the pages, the social template's likes and follows
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Visitors overview: one screen, a daily summary every day, the channel rule, Search Console joined to the pages (2026-11-01)
- Owner (2026-11-01, `plans/visitors-overview.md`): (1) the Visitors menu opens one overview screen with a period bar and sections; the saved-report builder stays as "Saved reports"; (2) the daily visitor summary is written for every day through yesterday whatever the retention period, the summary switch stays, retention alone decides removal; reason: every breakdown of the last 90 days scanned the raw table; (3) the channel rule is completed (paid, e-mail, AI assistants, organic, social, referral, direct) and the search-engine list brought up to date (the ENUM: `decisions-made-schema-9.md`); (4) the order and shipping report links stay in the Visitors flyout, a person with only the e-commerce reports right reaches them from the Ecommerce menu and sees no visitor figure; (5) the dashboards are not touched by the plan; (6) Search Console not linked: a small card linking to its settings; (7) alerts in the first phase, computed at render time, no table and no job.

## Social Community template: likes, following members, member-to-member messages (#6296, owner, 2026-11-03)
- Owner (2026-11-03, `plans/design-template-social.md`, PR #6297): (1) likes on a record are not built now; the template ships 1.0.0 without them; a separate general plan after 2026.11.0, designed together with the ActivityPub counts, brings them as a general feature (its table and schema step are asked in that plan); template 1.1.0 then uses them. (2) A member following another member: not now, the same later plan (a follow table, a follow button on the profile, a viewer filter on lists); "Saved" stays the watcher filter. (3) Private messages between members: not built; the conversation stays member to site. Reason: all three are product behaviour and schema; the template ships without waiting; likes and follows are designed once as general features every template can use; private member messages bring a moderation load (blocking, reports, notifications). Step L of the plan waits for that plan; steps 1-8 go on.
