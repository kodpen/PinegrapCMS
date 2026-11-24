---
name: bug-hunt-areas-rights-new-screens
description: Bug hunt of rights by role on the 2026.11.0 screens (volunteers, queue, pick and pack, Hot Contacts, launch journey, share links, prototype settings): two faults fixed in #11374
---

## What was the area?
- Rights by role on the screens added in 2026.11.0: volunteers, walk-in queue, pick and pack, Hot Contacts, the launch journey, Workspace share links, prototype approval's settings. Each screen and action as Administrator, Designer, Manager and User, with the module's right on and off; an action reached by its address; Designer (1) above Manager (2); a menu row shown to a role the screen refuses. Named by the architect, given by coordinator k1 (2026-11-24).
- Left alone: privacy of the new tables, the new API routes and the migration steps (k2's hunts 11-13); files held by open PRs (volunteer hunt #11338, prototype approval PA3 #11364 and PA4 #11359).

## What did it fix? (#11374)
- Hot Contacts' Remind me (`ws_contact_followup`, `ws_contact_followup_open()`) checked only that the contact existed: a User-role reader with the contacts right could tag a follow-up task with any contact by its id, outside their contact groups, and learn which ids exist. It now asks `ws_viewer_contacts_open()` and refuses one outside like a missing one.
- A User-role person whose only access is a queue place's staff list was refused by every role-3 ladder: signed in to the site, `welcome.php` said "Access denied.", no toolbar, and the password mails named no panel login, though the Staff card says they find My desk in the menu. The five ladder files now ask `pg_waiting_user_is_staff()`.

## What was read clean?
- Menus against screens: Calendars › Queue (roles 0-2, `'manager'` screens), My desk (role 3 by `pg_waiting_desk_scope()`), Contacts › Volunteers (roles 0-2 or a coordinator), Orders › Pick waves and Pack (`pg_pick_user_can()`), the packing-only row 29, the Hot Contacts view (role 3 kept to its contact groups).
- The Start here card's screens (`pg_dashboard_v2_starthere_may()`) match each screen's own gate; Designer gets styles and menus, Manager does not.
- The launch journey's actions (`launch_journey_save`, `launch_summary`, Site Settings' purpose fields) check role 0 themselves; the set-up and First week cards are role 0 only.
- Volunteers: coordinators get no Set-up tab and no Open/Pause or Set-up save (`$vp_staff` on each POST); `pg_volunteer_user_sees()` on every tab; get_file.php's document door.
- Share links: make needs posting in the channel, revoke its maker or a channel manager, an app only its own links.
- Prototype: `prototype_link*` only through `pg_designer_is_full()` and `pg_proto_links_panel()` (role <= 1); a team link opens for roles 0-2 (`pg_proto_viewer_role_allowed()`).
- No new module compares roles in a way that puts Manager above Designer.

## What is left as known, not changed?
- A volunteer coordinator with no other right is not on the ladders and has no menu row: `core/volunteer-programmes.md` says the row needs contact access.
- With the Calendars module off the Queue and My desk rows are gone while the screens open by address, as Bookings.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/permission-columns.md
