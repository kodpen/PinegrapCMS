---
name: features-switchboard-steps
description: Plan features-switchboard, steps FS1 to FS3: the board of module switches, the rest of Features as folded rows, close-out
---

## Which steps are there?
- FS1 (M) done (#11003), first, after DX2b (`plans/design-examples-patterns-mail.md`), copying its "Switch rows" pattern: the "Modules" card of switch rows at the pane's top. The switches of Forms, Calendars, Workspace, Ads, Auto Dialogs, Mobile and Social Networking, and of the cards Courses, Tickets, Visitor notifications, Maps, Clicks and scrolling and Content Approval, move into it with their names and ids; Commerce and Accounting as rows with their state and Open. Fixes: F1, F2, F7, F8.
- In FS1 each row's line says what the module adds and that switching off deletes nothing; Mobile's line says it turns the page cache off. Every switch is `pg-switch`. The board takes the `pgset-features` id; until FS2, Default Badge Label, Site Search Type, Prefetch Menu Links and Performance Monitoring wait below it in a card of their own. The registry gets the board's section and keywords; `pgset-courses` and `pgset-event-tickets` open the board (`pg_settings_legacy_anchors()`).
- Tests of FS1: every switch posts under its old name, the save handlers unchanged; each old card id opens the board; a Manager gets no Content Approval row.

- FS2 (M) done (#11043): the rest of the pane as folded rows (`pg_settings_fold()`), each with its state line: "Site search" (Site Search Type as `pg-seg`, Synonyms, Promoted Results), "Sharing buttons" (Setup as `pg-seg`, the services or the code under it, no popover), "Visitor notifications", "Maps", "Clicks and scrolling", "Content approval", "Speed" (Page Cache, Prefetch Menu Links, Performance Monitoring), "Images", "Page revisions", "Signature time stamp", "Badge". Fixes: F3, F4, F5, F6.
- In FS2 each card keeps its id inside its row; Site Search Type leaves Feature Options for "Site search", Prefetch Menu Links and Performance Monitoring for "Speed", Default Badge Label for "Badge".
- Tests of FS2: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` pass with the ids kept; a row an anchor names arrives open.

- FS3 (S) close-out: `ui/settings-features.md` written (the board, the rows, what stayed); `ui/look-settings-maintenance-security.md` and `ui/dashboard-module-switches.md` corrected where they name Feature Options; item 1 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted.

## Which steps run side by side?
- None: FS1, FS2 and FS3 enter `includes/settings/features.php` and `registry.php` one after another.

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px before ready and writes "Screen check" in the PR.

Related: docs/memory/plans/features-switchboard.md, docs/memory/plans/features-switchboard-screens.md
