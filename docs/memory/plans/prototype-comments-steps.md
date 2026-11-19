---
name: prototype-comments-steps
description: Plan prototype-comments, steps PN1 to PN5: the table and the guest's door, the viewer's comment mode, the designer's pins and list, the task, close-out
---

## Which steps are there?
- PN1 (M) The table and the guest's door. Done: #9915.
  - A migration step adds one table (a working name: `prototype_comments`), listed in `get_tables()`, and the link's "Allow comments"; the draft PR's body carries the columns, the indexes and the flag's place for the architect.
  - Existing files: every path that deletes a design (where `pg_proto_links_revoke_design()` is called), `functions.php` (the module).
  - New: `includes/fn/prototype_comments.php` (ready check, the check of a guest's comment, save, a link's and a page's comments, answer, resolve, delete, the newest stamp of a page), the root file `prototype_comment.php`, `tests/prototype_comments_test.php`.
  - Tests, red first: each refusal of the door (another session, no token, another origin, a page the link does not show, the limits), plain text kept, a place out of bounds refused.
  - Features: P7 to P11.

- PN2 (M) after PN1: the viewer's comment mode. Done: #10025.
  - Existing files: `prototype.php` (the frame branch draws with node marks for such a link only, `pg_designer_audit_render()`), `includes/fn/prototype.php` (`pg_proto_frame_inject()`: the pin click; `pg_proto_shell_html()`: the button, the box, the pins and threads), the Share prototype dialog and the API's link routes ("Allow comments").
  - Tests: no node mark without the flag, the frame's message taken only as a place.
  - The viewer reads one page's comments by page and link (`pg_proto_comments_of_link_page()`, idx_page), never the link's whole list on each page: resolved comments are kept, so that list grows (architect, agent channel message 6207).
  - Pictures: the mode off and on, a pin open with a thread, "Not on the page any more", a phone. To the owner; not marked ready before his look.
  - Features: P1 to P6.

- PN3 (M) after PN1: the designer's pins and list. Done: #10042. P11's "Delete this link's comments" button was left for PN5 (it sits in the Share prototype dialog's links list).
  - Existing files: `includes/panel/designer.php` (the presence answer carries the stamp; the part's actions for roles 0 and 1), `assets/js/style_designer.src.js` (the part's registration only).
  - New: the part's own file (`designer/designer-parts-on-demand.md`); the bell line (`ui/bell-titles.md`).
  - Pictures: pins on the canvas, the list with open and resolved, a pin whose node is gone. To the owner.
  - The bell's "at most one an hour per link" (P15) is a `waf_rate_exceeded()` counter with the link id as its subject, limit 1, window 3600 (as `note_lock.php` uses it); no column on the link (architect, agent channel message 6207).
  - A `pg_privacy_erase_tables()` entry for a deleted user's answers (the name replaced, `user_id` set to 0): the screen that first writes a staff answer is here (architect, agent channel message 6207).
  - Features: P12 to P15.

- PN4 (S) after PN3: the task. Done: #10459. "Make a task" through `ws_followup_task_open()` with `$once` false. Tests: the task's fields, no button with the Workspace off. Features: P16.

- PN5 (S) close-out: every P number checked against the code with its PR; `designer/prototype-comments.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- PN2 and PN3 run beside each other once PN1 has landed.
