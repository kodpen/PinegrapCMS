---
name: features-switchboard
description: Plan (owner 2026-11-21): Site Settings › Features as one board of module switches and folded rows for the rest; its contradictions fixed, social sharing out of popovers
priority: 8
---

## What is asked?
- Owner (2026-11-21, in the R&D agent's window): find and fix the settings screens' contradictions, gather settings spread over several places, make old screens modern in use and look; e-mail first (`plans/mail-settings-one.md`), then the other categories.
- Features is the next (`plans/settings-tidy-census-order.md`, item 1). Architect (2026-11-21, message 11313): one category a plan, asking the architect only for the parts it truly lacks.

## What contradicts today? (development ab189aea9d)
- (F1) Module switches in three shapes: eight in the Feature Options card (`includes/settings/features.php:24-195`), six as cards of their own (Courses, Tickets, Visitor notifications, Maps, Clicks and scrolling, Content Approval), Commerce and the ERP at their categories' heads (`commerce.php:34`, `accounting.php:39`). Nothing shows at once what is on.
- (F2) The Courses and Tickets cards (`features.php:560,582`) are not in the registry (`registry.php:130-157`): no chip, and the settings search does not find them.
- (F3) Feature Options mixes switches with settings that switch nothing: Default Badge Label (a member's badge in comments and form records), Site Search Type, Prefetch Menu Links.
- (F4) Enable Social Networking opens a popover whose Setup radios open a second one (services) or a third (code): three levels deep, plain radios.
- (F5) Site search in two places: Site Search Type in Feature Options, and the Search Tuning card.
- (F6) Speed in two places: the Page Cache card; Prefetch Menu Links and Performance Monitoring in Feature Options. Page Cache's help says it is off while the mobile view is on (`includes/fn/page_cache.php:553`); Enable Mobile says nothing.
- (F7) Six module switches have no help line (Forms, Calendars, Ads, Mobile, Auto Dialogs, Social Networking): what switching off hides is not said (`ui/dashboard-module-switches.md`).
- (F8) Two switch drawings: `div.switch > input`, and `input.pg-switch` on Courses and Tickets.

## What does the plan settle itself?
- Commerce and Accounting keep their switch at their category's head: the ERP's save checks the invoice series with it and runs its setup after it (`accounting.save.php:62,408`), and both categories exist for it. The board shows their state and opens them: one place per setting (`decisions-made-product-14.md`).
- Default Badge Label stays in Features as its own folded row: no other category holds members' badges.
- Content Approval's row is drawn for designers and administrators only, as its card is (`registry.php:622-626`).

## What is asked of the architect?
- The switch row (FS1): the Design examples screen has no row with a switch at its end. A Connect row is a link and a folded row's head is a button; neither may hold a switch.

## What binds it?
- Field names, ids, columns and what a save stores do not change; card ids and old anchors keep working (`pg_settings_legacy_anchors()`); one Save for the pane; a switched-off feature's settings are never hidden (`ui/settings-card-save-rules.md`).
- `includes/settings/features.php` takes one PR at a time.

Related: docs/memory/plans/features-switchboard-steps.md, docs/memory/plans/features-switchboard-screens.md, docs/memory/plans/settings-tidy-census-order.md
