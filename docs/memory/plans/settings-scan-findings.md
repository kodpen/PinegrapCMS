---
name: settings-scan-findings
description: Faults the 2026-11-21 scan of the Site Settings panes left on SEO, Languages, Channels, Team and Maintenance; a step deletes its lines, the last one the file
---

## What is this list?
- The research agent opened 19 panes (40 views) at 1440 and 390 px on 2026-11-21 (agent channel message 11676). The rules the faults broke: `ui/screen-composition-nesting.md`.
- The shared style (`ui/settings-card-shared-style.md`) and the planned panes' lines (General, Communication, Accounts and sign-in, E-mail) are done. A step that fixes a line here deletes it in its own PR; the last line's PR deletes the file.

## Which faults are left?
- Languages and Translation: a double left edge, lower-case "ready".
- SEO: the white colour box unseen, spacing.
- Channels: the filled "New template" button, mixed labels.
- Team: the member table overflows a phone, label weights.
- Updates and maintenance: the 10% bubble always open, a missing line, the mini map on a one-line command; its plan closed without them (`ui/settings-maintenance.md`).

## Which panes still need a plan?
- SEO, Languages and Translation, Fediverse, Integrations, Channels, Team: folded-row plans by the research agent. Ecommerce has its plan (`settings-ecommerce-tidy.md`); Accounting and Updates and maintenance are done (`ui/settings-accounting.md`, `ui/settings-maintenance.md`).

Related: docs/memory/ui/screen-composition-nesting.md, docs/memory/plans/settings-tidy-census-order.md
