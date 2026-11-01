---
name: decisions-made-product-18
description: Decisions list, product part 18 (2026-11-01 afternoon on): search and audits (one screen, defaults, editor findings); the editor's presentation and silent video; redacted Workspace replace
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Search and audits: one screen, defaults, the editor's accessibility findings and measurements (2026-11-01)
- Owner (2026-11-01, PR #4693, `plans/search-and-audits.md`): one panel screen "Search and audits" for the index, structure, accessibility and backup engines (Pages tools menu, main menu, Site Settings › SEO), the popup window gone; the three jobs on for a new install and unchanged on a running site until switched on; the editor's audit panel lists the draft's accessibility findings beside SEO; contrast and target size measured in the editor are kept with the report, marked as measured there, replaced on each save.

## Image editor animation: presentation only in the editor, silent video (2026-11-01)
- Owner (2026-11-01, #4714, #4716): a document's scenes are presented in the editor's full-screen Present mode and exported as PDF, video and GIF; nothing is added to the live site. Saved videos carry no sound; a music track may follow as its own step if asked.

## Workspace: who sees the original after a redacted picture replace (2026-11-01)
- Owner (2026-11-01, #4783): after a redacted replace of a picture posted in a channel, the earlier (unredacted) version is listed in the message's history only for the person who made the edit; everyone else reading the channel sees the redacted file alone. The file stays in the file manager under its folder's access, so the reversibility chosen for Files (#4203) holds. Reason: a redaction that every reader could undo from the history list protected nobody. Shape: `decisions-made-schema-7.md`.

## Visitors overview: one screen on the rollups, a daily summary every day, the channel rule, Search Console joined to the pages (2026-11-01)
- Owner (2026-11-01 evening, `plans/visitors-overview.md`): (1) the Visitors menu opens one overview screen with a period bar and sections (alerts, traffic, conversions, sources, content, Google search, pages, places); the saved-report builder stays as "Saved reports"; (2) the daily visitor summary is written for every day through yesterday whatever the retention period, the summary switch stays, retention alone decides removal — reason: every breakdown of the last 90 days was a scan of the raw table; (3) the channel rule is completed (paid, e-mail, AI assistants, organic, social, referral, direct) and the search-engine list brought up to date (the ENUM: `decisions-made-schema-8.md`); (4) the order and shipping report links stay in the Visitors flyout, a person with only the e-commerce reports right reaches them from the Ecommerce menu and sees no visitor figure; (5) the dashboards are not touched by the plan, card reuse is decided when the new dashboard's design is finished; (6) with Search Console not linked the Google section is a small card linking to its settings; (7) alerts are in the first phase, computed at render time from the same figures, no table and no job.
