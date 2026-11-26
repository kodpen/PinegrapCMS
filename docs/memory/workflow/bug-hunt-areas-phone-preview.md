---
name: bug-hunt-areas-phone-preview
description: Bug hunt in "Preview on a phone" (2026-11-26): failing asks that never stopped and a found link opening on the lock note, fixed; what was read clean and left
---

## Which area was it?
- "Preview on a phone" (PP1-PP3, #11541, #11578, #11582), read as a door: a link for anyone handed to a phone, and an address that answers with no session.
- Files: `includes/fn/prototype_phone.php`, `includes/fn/prototype_phone_view.php` with its inline script, the watch and phone branches of `prototype.php`, the designer sub-actions `prototype_phone` and `prototype_phone_end`, `pg_qr_svg()`, `includes/designer_parts/phone.php` with `assets/js/style_designer_phone.js`.

## What was fixed?
- The phone kept asking every 10 seconds for as long as the tab was seen when the asks failed (no network, a 500, the firewall's 429): the pace and the stop came only from a good answer. The script now keeps them itself on a failure: 30 s once ten minutes have passed since the drawing, "Paused" with "Update" an hour after it (`cfg.window`, `pg_proto_phone_watch_window()`). Test: `tests/prototype_phone_view_test.php`.
- A found phone link was checked only through the page being edited, never its own start page: a link made on a page later moved behind a sign-in, binned or deleted was handed out again, and the phone opened on the lock note or the first draft. It is now revoked and the press goes on as if none was found (`pg_proto_phone_found_keeps()`; architect, 2026-11-26, request 3040, `designer/designer-phone-preview-server.md`). Test: `tests/prototype_phone_test.php`.

## What was read and found clean?
- The link: 8-hour span set from one second (`pg_proto_phone_plan()`, `created_at` passed to `pg_proto_link_issue()`); an ordinary link's span is its duration minus the moments between plan and insert, so never 8 hours; bundle, password, comment and approval links are never phone links; no path edits `expires_at`.
- Found again and ended only for the person who made it (`created_by`), one per design and audience; the design's delete paths (`delete_styles.php`, the recycle bin, the designer, template install, the Bootstrap trial) revoke it.
- Makers: roles 0-1 in `pg_proto_phone_panel()`, the sub-actions outside `$content_ok`; a page whose publish folder is not public or guest answers `team_only` for anyone and makes nothing; a team link sends the signed-out to sign-in.
- The watch: HMAC under its own prefix over link, page and start; `b=` checked against the session cookie on every ask; unknown, revoked, expired, not-a-phone link, a page of another design, unbound: one 404; junk `w`, `p`, `t`, `since`, `b` parsed as none; reads one row, writes nothing, counts no use.
- The secret: the answer `no-store` set before `respond()`; the QR is inline SVG; `pg_qr_matrix()` swallows its error without logging the text; Site Log names `#id (hint…)`; sessionStorage keeps only the scroll, the page name and flags.
- The phone's view: a locked page through the list draws the "signed-in visitors" note and the frame gates it again; names escaped by `h()`, the earlier name put back by `textContent`; the config JSON hex-escaped.

## What was seen and left?
- A found link with only minutes left is handed out as it is; the window counts down and offers "Make a new link".
- Two presses at the same moment (two tabs) can make two phone links; each lives its 8 hours.
- Not reached by the tests: the watch over HTTP, the session cookie on a real phone, the firewall's global limit with many phones behind one address.
