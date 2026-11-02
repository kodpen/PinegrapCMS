---
name: feature-plans-2026-11
description: The eleven feature plans approved 2026-11-02: index and order, how one coordinator and its five workers hold a plan, the shared rules
---

## What was decided? (owner, 2026-11-02)
- Eleven features from the November gap review become plans, one file pair each (the plan, its steps). The twelfth candidate (design variables and classes) was left out.
- Every plan is held by **one coordinator and its five workers**; two coordinators run, so two plans are in work at a time. A coordinator finishes its plan before taking the next one from the order below.
- Pinegrap's audience is mixed (bloggers, shops, agencies, corporate sites) and global: nothing in these plans is built around one country, and products or invoices are never put first.

## Which plans, in which order?
1. `mail-deliverability.md` (MD1-MD7): bounces, complaints, API transports, domain health.
2. `object-storage.md` (OS1-OS7): S3-compatible backup target, then media.
3. `web-vitals-ai-visibility.md` (WV1-WV6): real-user web vitals, AI crawler and AI referral figures.
4. `designer-form-logic.md` (FL1-FL8): shown-when rules, steps, a payment step in designed forms.
5. `appointment-booking.md` (BK1-BK10): services, staff hours, slots, a booking widget.
6. `page-experiments.md` (PX1-PX8): A/B variants of a page and audience rules on elements.
7. `audiences.md` (AU1-AU6): rule-driven contact groups.
8. `content-importers.md` (IM1-IM7): WordPress WXR, WooCommerce and Shopify CSV.
9. `store-credit.md` (SC1-SC7): spendable points, refunds to credit, auto-applied balance.
10. `activitypub.md` (AP1-AP8): the site as a Fediverse actor. Plan file follows in its own PR.
11. `stock-locations.md` (SL1-SL7): stock per location, the product's total unchanged. Plan file follows in its own PR.

## How does a coordinator hold a plan?
- It claims nothing itself and writes no code (`workflow/coordinator.md`). It gives its workers only step lines that say `free` or whose "After" steps are merged, at most five at once and never two on the same files; the steps file says which run side by side.
- A worker claims with a draft PR `[plan:<plan file name>] <step> — <topic>` (CLAUDE.md, Claim work), builds the step as its line says, marks the line done with its PR number, and comments "Ready for review" on the draft. The coordinator checks the PR against the step line (files, tests, memory, the `tr_add.php` texts, the bundle rebuild) and answers on the PR; after its "Reviewed" the worker marks the PR ready and puts the `ready` label on when CI is green (Working mode 2).
- A question the plan leaves open is opened by the coordinator as an `architect` issue before the first step that needs it (Working mode 10); steps that need no answer start first. A worker never decides it.
- A step is about 1,000 product lines at most (S under 400, M about 1,000, L cut into PRs); each step writes its memory topic file and its tests; a schema change is one sub-step of the open version's migration, rerun twice on a dev DB.
- When the last step lands, the coordinator's last worker moves the lasting decisions to the topic folders, deletes the plan's files and this file's line for it, and runs `php tools/memory_index.php`.

## Where does it start?
- Each plan's "Where does it start?" names its first steps; the coordinator reads the plan, its steps file and the memory folders they name before handing anything out.
