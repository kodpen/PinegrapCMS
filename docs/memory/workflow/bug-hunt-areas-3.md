---
name: bug-hunt-areas-3
description: Bug hunt areas given from 2026-11-19 on: fifteen panel screens, the own sheet reader, two Workspace screens at phone width, prototype comments' guest side, picture text translation's server side
---

## Which areas were hunted from 2026-11-19 on?
- The hunts before: `bug-hunt-areas.md` and `bug-hunt-areas-2.md`. The next area is none of the three files.
- 2026-11-19, one screen per hunt, each fixing its finds in its own PR (the PR body lists them): Customers report #10357, Courses screen #10358, Communication Templates #10359, Communication Sends #10360, Meeting link page #10361, Product screen #10368, Order screen #10369, Workspace Data #10370, Edit User screen #10371, Translations screen #10372, Communication Automatic sends #10375, Order Report screen #10381, Create Order screen #10384, Contacts screens #10397, Bookings screens #10399.
- 2026-11-20: the own sheet reader and its importers, compared cell by cell with PHPExcel before its removal (#10537; left open: #10544); Incoming e-mail and Mailboxes at phone width (#10543; left open: #10545); a Workspace channel at phone width (#10554; left open: #10549 to #10552).

## Which two areas were given on 2026-11-20? (channel message 10096, answer to K2's request 1675)
- Prototype comments' guest side (#9915, #10025, #10459, #10486): the guest's door `prototype_comment.php`, `includes/fn/prototype_comments.php`, the share links `includes/fn/prototype_links.php`, the viewer `prototype.php` and `assets/js/pg_proto_comments.js`.
  - Not touched: `assets/js/pg_proto_hotspots.js`, `style_designer.src.js`, `style_designer_comments.js` (held by open steps and by the step that moves the Comments button, PN6). Known, not reported: where the button and the list sit.
- Picture text translation's server side (#10028 to #10448): `includes/fn/picture_translation.php` (states, hooks, the page pass, the prune), the automatic draw's rows and jobs (`translation_job.php`, `includes/fn/picture_server_draw.php`), the file doors (`get_file.php`, `edit_files.php`, `handle_recycle.php`).
  - Not touched: the Translations screen's files (held by S6c #10547), `view_folder_and_files_f.php` (#10542), the Design Canvas scripts. Decided, no fault: `core/picture-text-overview.md`.
- What the two hunts found is added here when they end.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md
