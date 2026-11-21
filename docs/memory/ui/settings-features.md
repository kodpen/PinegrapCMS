---
name: settings-features
description: Site Settings › Features: the Modules board of switch rows, what each row says and posts, Commerce and Accounting with Open, the old card ids, the card below it
module: features_modules
---

## How is the Modules board drawn?
- The first card of the pane (`includes/settings/features.php`), id `pgset-features`: the "Modules" group label with its grey count ("9 / 15 on"), over one card of switch rows copied from the Design examples screen's "Switch rows" pattern (`.pg-set-switch-row`).
- Order, names, icons and both lines of each row: `includes/fn/features_modules.php` (`pg_features_module_order()`, `pg_features_module_face()`, `pg_features_module_row()`, `pg_features_modules_board()`, pure but for `lang()` and `h()`).
- Rows: Commerce, Accounting, Forms, Calendars, Workspace, Courses, Tickets, Ads, Auto dialogs, Sharing buttons, Visitor notifications, Maps, Clicks and scrolling, Content approval, Mobile view. A row an install lacks (Workspace column, Courses, Tickets, Visitor notifications, Maps, Clicks and scrolling) is left out; Content approval is drawn for designers and administrators only, as its card.

## What does a row say and post?
- On, the line says what the module adds and that switching it off deletes nothing; off, what switching off hides. The switch carries both lines (`data-pg-on`, `data-pg-off`) and `backend.src.js` swaps them and recounts the board when it is turned. Mobile view's line says the page cache is off while it is on.
- Every switch is `input.pg-switch` under the name and id of the card it came from (`forms`, `calendars`, `workspace_enabled`, `courses_on`, `event_tickets_on`, `ads`, `auto_dialogs`, `social_networking`, `site_push_on`, `place_map_on`, `page_clicks_on`, `content_approval_on`, `mobile`): `features.save.php` did not change. Courses and Tickets post their card markers (`courses_card`, `event_tickets_card`) from their rows.
- "Settings" (`data-pg-set-goto`) opens and scrolls to the part holding the module's settings: the cards Visitor notifications, Maps, Clicks and scrolling, Content Approval, and the sharing setup (`#pg-features-sharing`).
- Commerce and Accounting keep their switch at the head of their own category (the ERP save needs it there); their row shows an On or Off pill and "Open it", drawn only for a reader who may open that category.

## Where do the old card ids lead?
- `pgset-courses` and `pgset-event-tickets` are no cards any more: `pg_settings_legacy_anchors()` sends them to `features` / `pgset-features`. A `#settings/features/pgset-courses` address finds its card through the same map (the dialog's `jump()` looks an id up in the map when the pane has no such element).

## What waits below the board?
- A card "Other settings" (id `pg-features-other`, not `pgset-`, so the search index counts its labels as the board's): Default Badge Label, Site Search Type, Performance Monitoring, Prefetch Menu Links, and the sharing setup (Setup radios, the services or the code) with no popover opened by the switch.
- Test: `tests/settings_features_modules_test.php`.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/plans/features-switchboard-steps.md
