---
name: bug-hunt-areas-prototype-comments
description: The bug hunt of prototype comments, the guest's side (architect 2026-11-20, channel message 10096): the five faults fixed, the issues opened, what was read clean
---

## What was hunted?
- Given on 2026-11-20 (channel message 10096): prototype comments, the guest's side (#9915, #10025, #10459, #10486): the door `prototype_comment.php`, `includes/fn/prototype_comments.php`, the share links `includes/fn/prototype_links.php`, the viewer `prototype.php` and `assets/js/pg_proto_comments.js`.
- Held and not touched: `pg_proto_hotspots.js`, `style_designer.src.js`, `style_designer_comments.js`. Known, not reported: where the Comments button and list sit (plan prototype-comments-place).

## What did it fix?
- The PR "Bug hunt — prototype comments, the guest's side", five faults, each with its test in `tests/prototype_comments_guest_test.php`:
- A guest idle past PHP's session lifetime (24 minutes by default) had the comment refused and lost it: the viewer now pings the door every 10 minutes (`designer-prototype-comments-door.md`).
- A click on the page, a pin or a lost thread while writing replaced the box and dropped the typed text: the box now stays (`designer-prototype-comments-viewer.md`).
- The box's fields were under 16px on a phone, so iOS zoomed the viewer in: 16px there.
- An answer to a thread resolved or deleted meanwhile, and a page no longer a draft, said only "Sorry, we could not accept your request.": each now says why.
- A link for anyone gave the Comment button on a draft going live behind a sign-in, which its frame does not draw: no comment mode there.

## Which issues did it open?
- None: no fault needed a decision or lay in a held file.

## What was read clean?
- The door's gates in order (method, origin, form token, link state, the session mark past the password and team gates, "Allow comments", a page the link shows), the one answer for revoked, expired, gone and unknown, the burst counted only for a counted address and after the field check, the 200 open comments a link.
- Plain text (UTF-8, controls and direction overrides out), the place's shape and bounds, an answer tied to an open thread of the same link and page; the bell once an hour a link; `lang()` vars put in by `strtr()` in one pass, so a name like `{var:2}` stays text.
- The viewer: comments printed as JSON with every HEX flag, every name and text drawn with `textContent`, the frame given numbers and places only, `place`/`pin`/`missing` taken only from the frame and for threads it holds.
- "Make a task": the guest's words with ‹ › and no block or fence start; a comment of another design or a deleted page refused. "Delete comments": the link must be of the design named.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/prototype-comments.md
