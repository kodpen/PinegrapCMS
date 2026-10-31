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
- Create/Edit Field (both Edit branches) dropped `form_type` and `send_to`: on an Express Order shipping form the arrow opened the billing fields, and the fields list's own arrow then went to All My Pages. Now built like Save: `pg_field_form_express_query()` and `send_to`.
- Visit Details went to All Visitor Reports; now to its breadcrumb parent `view_visitor_report.php?id=<visitor_report_id>` (an int, not the `h()`-escaped crumb url).
- Edit Calendar Properties went to `view_calendars.php`; now `calendars.php?calendar_id=<id>` like its Save.
- Pinned by `tests/back_arrow_targets_test.php`.

## Where does the back arrow go when the screen got send_to?
- To `send_to` (`pg_send_to_url(<list>)`), like Edit User and Edit Page: Edit/Create/Import Contact, Create/Import User, Edit Ad (#3358) and Edit Comment.
- Symptom fixed: a contact opened from All Subscribers went back to All My Contacts (`view_contacts.php` and `view_users.php` read their view only from the address); a contact opened from Edit User, or an ad or comment from a site page's Edit chip (a comment also from the dashboard card), went back to the panel list.
- The Contacts/Users breadcrumb uses `pg_send_to_list_url(<list>)` (`includes/fn/core.php`): `send_to` only when its path is that same list, else the bare list (Edit Contact is also opened from Edit User and Edit Gift Card). Labelled `Contacts` / `Users`.
- The Edit Ad and Edit Comment breadcrumbs stay on their list, so no list link opens a site page.
- A refused Edit Comment save redirects with `send_to` in the address, so the arrow keeps it.
- Pinned by `tests/back_arrow_send_to_test.php`.
- Create/Edit Short Link opened from the 404 Log get `send_to` = the log with its `who`/`q`; arrow, refused save and Save/Delete keep it, the notice goes on the log's liveform (`pg_short_link_return_form()`). Removing an address posts to the filtered log too. Before, each action lost the search. Pinned by `tests/not_found_log_test.php`.

## Which arrows keep their target on purpose?
- `edit_menu.php`: its arrow matches its breadcrumb parent All Menus.
- The common-region screens: bare `view_regions.php` is All Common Regions.
- `backups.php`, `cloudflare.php`, `mail_queue.php`, `view_audit_log.php`: `history.go(-1)` works as the browser's Back.
