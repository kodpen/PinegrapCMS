---
name: bug-hunt-areas-4
description: Bug hunt areas beside bug-hunt-areas-3: the fifteen panel screens hunted on 2026-11-19 and the two areas given on 2026-11-20 (prototype comments' guest side, picture text translation's server side)
---

## Which screens were hunted on 2026-11-19?
- One screen per hunt, each fixing its finds in its own PR (the PR body lists them): Customers report #10357, Courses screen #10358, Communication Templates #10359, Communication Sends #10360, Meeting link page #10361, Product screen #10368, Order screen #10369, Workspace Data #10370, Edit User screen #10371, Translations screen #10372, Communication Automatic sends #10375, Order Report screen #10381, Create Order screen #10384, Contacts screens #10397, Bookings screens #10399.
- A Workspace channel at phone width was hunted on 2026-11-20 (#10554; left open: #10549 to #10552).

## Which two areas were given on 2026-11-20? (channel message 10096, answer to K2's request 1675)
- Prototype comments' guest side (#9915, #10025, #10459, #10486): the guest's door `prototype_comment.php`, `includes/fn/prototype_comments.php`, the share links `includes/fn/prototype_links.php`, the viewer `prototype.php` and `assets/js/pg_proto_comments.js`.
  - Not touched: `assets/js/pg_proto_hotspots.js`, `style_designer.src.js`, `style_designer_comments.js` (held by open steps and by the step that moves the Comments button, PN6). Known, not reported: where the button and the list sit.
- Picture text translation's server side (#10028 to #10448): `includes/fn/picture_translation.php` (states, hooks, the page pass, the prune), the automatic draw's rows and jobs (`translation_job.php`, `includes/fn/picture_server_draw.php`), the file doors (`get_file.php`, `edit_files.php`, `handle_recycle.php`).
  - Not touched: the Translations screen's files (held by S6c #10547), `view_folder_and_files_f.php` (#10542), the Artifact Canvas scripts. Decided, no fault: `core/picture-text-overview.md`.
- What the two hunts found is written by their own PRs.

## Which five areas were given on the afternoon of 2026-11-20?
- K1, event tickets and the door (channel message 10668): hunted in #10754; left open #10755 (a repeating event with shared places, asked in poll 195) and #10756 (a refunded return voids its tickets: decided, channel message 10785).
- K2 (channel message 10776): product form uploads (`includes/fn/product_form_uploads.php`, their door in `get_file.php`, the upload part of `cart_action.php` and `express_order.php`, the sweep, the order's readers); the click and scroll map (`page_clicks.php`, `includes/fn/page_clicks.php`, `includes/visitors/clicks.php`, the nightly rollup, the designer's layer).
- K1 (channel message 10779): the channel commands' server side (`includes/workspace/commands.php`, `command_registry.php`, `app_commands.php`, `quick_edit.php`, `changes.php`; not `api.php`, not `workspace.js`); the place map (`places.php`, `place_coordinates.php`, `includes/fn/places.php`, `place_map.php`, `place_list.php`, `place_tiles.php`).
- What each hunt found is written by its own PR.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-3.md
