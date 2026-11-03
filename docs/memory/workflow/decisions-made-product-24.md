---
name: decisions-made-product-24
description: Decisions list, product part 24 (2026-11-01 on): the Visitors overview screen, the channel rule, Search Console joined to the pages; the image editor's new tools and video editing
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Visitors overview: one screen, a daily summary every day, the channel rule, Search Console joined to the pages (2026-11-01)
- Owner (2026-11-01, `plans/visitors-overview.md`): (1) the Visitors menu opens one overview screen with a period bar and sections; the saved-report builder stays as "Saved reports"; (2) the daily visitor summary is written for every day through yesterday whatever the retention period, the summary switch stays, retention alone decides removal; reason: every breakdown of the last 90 days scanned the raw table; (3) the channel rule is completed (paid, e-mail, AI assistants, organic, social, referral, direct) and the search-engine list brought up to date (the ENUM: `decisions-made-schema-9.md`); (4) the order and shipping report links stay in the Visitors flyout, a person with only the e-commerce reports right reaches them from the Ecommerce menu and sees no visitor figure; (5) the dashboards are not touched by the plan; (6) Search Console not linked: a small card linking to its settings; (7) alerts in the first phase, computed at render time, no table and no job.

## Image editor: new tools and video editing inside it (2026-11-03)
- Owner (2026-11-03, `plans/image-editor-steps-3.md`, `-steps-4.md`, `image-editor-video.md`): (1) a placed sticker, icon or emoji can be changed in place; (2) text converts to outlines; (3) scissors for paths, and three shaping tools: warp, shape builder, free deformation; (4) area and subject selection and background removal with an own algorithm first, a downloadable model maybe later as an option; (5) video editing inside the image editor (trim, split, effects, sound added or detached, pictures and animations over video), widening 2026-10-31 (no video) and #4716 (silent videos): sound is allowed; (6) order: the small tools first, then selection and background, the animation steps beside them, video last; (7) a separate commercial package of the editor is set aside: development stays in PinegrapCMS. Reason: Pintura's range plus vector, SVG and video in the CMS's own editor.
