---
name: back-arrow-targets
description: The back arrow of a panel screen (pg_page_shell 'cancel') goes where its Save goes, send_to first; the screens fixed for it and the ones that keep another target
---

## Where does a screen's back arrow go?
- Where its Save goes: `send_to` through `pg_send_to_url()` first when Save honours it, else the list or screen Save redirects to, with the same filter and id.
- The url goes through `pg_output_go_script()`, so a screen adds no escaping of its own.
- A Site Settings screen (registered in `includes/settings/registry.php`) prints no arrow (`settings-modal-navigation.md`).

## Which screens were wrong, and what did they show?
- Create/Edit Menu Item sent the arrow to `view_menu_items.php` with no id: "Sorry, the page could not be found." (404). Now `?id=<menu>&from=&send_to=`.
- Create/Edit Ad, Login, Dynamic and Designer Region went to bare `view_regions.php`, drawn as All Common Regions. Now `filter=all_<kind>_regions`; Edit Ad and Edit Designer Region take `send_to` first.
- Edit Form List View / Item View went to the submitted-forms list, whose `validate_forms_access()` can refuse a page editor. Now `send_to` or `edit_page.php?id=<page>`.
- Create/Edit Event went to `view_calendars.php` (the list of calendars) instead of the calendar view `calendars.php` (edit: `send_to` first).
- All Referral Sources (Site Settings › Store Lists) went to the Dashboard; it now prints no arrow.
- Pinned by `tests/back_arrow_targets_test.php`.

## Which arrows keep their target on purpose?
- `edit_menu.php`: its arrow matches its breadcrumb parent All Menus.
- The common-region screens: bare `view_regions.php` is All Common Regions.
- `backups.php`, `cloudflare.php`, `mail_queue.php`, `view_audit_log.php`: `history.go(-1)` works as the browser's Back.
