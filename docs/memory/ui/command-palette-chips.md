---
name: command-palette-chips
description: Which Ctrl+K search chips a viewer gets: the same role, right and module rule as the main-menu entry that opens the same screen
---
## Which chips does a viewer get?
- The chips follow the main-menu gates: each one is offered under the rule of the `output_menu()` entry (`fn/output.php`) that opens the same screen, module switches included. `pg_panel_search_chip_gates()` (pure) takes the role, the rights and the query-backed rights read once for role 3 (`no_acl_check()`, `validate_contacts_access($user, true)`, `validate_calendars_access($user, true)`, an ad region) and returns one flag per group.
- Before, the chips were gated by hand (`role <= 2`, `role <= 1`, or nothing): a Manager got Menus (designers only), a Designer an empty Dynamic Regions list (administrators only), role 3 Pages, Files, Comments, Auto Dialogs, Visitors without the right, all "Access denied"; role 3 with manage_emails, calendars, manage_forms, ad regions or only the order-reports right never got those chips. Pinned by `tests/panel_search_chips_test.php`.
