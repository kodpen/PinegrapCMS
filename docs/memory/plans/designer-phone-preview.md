---
name: designer-phone-preview
description: Plan (owner 2026-11-24): "Preview on a phone" in the designer: a QR code of a short-lived share link, the phone redrawing after each save; steps PP1 to PP4
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: the draft seen on the real phone, with its own browser, fonts and thumb, while it is being designed.

## What is there today?
- The canvas bar narrows the canvas to a phone's width, which is not a phone. A share link opens a design's prototype for a person without a sign-in (`designer/designer-prototype-share-links.md`), made by hand in "Share prototype"; a QR code is drawn by `pg_qr_svg()` (`core/qr-code-generator.md`).

## What is built?
- "Preview on a phone" in the Preview menu beside Publish (`designer/designer-preview-menu.md`): a small window with the QR code, "Point your phone's camera here", the time the link has left, "Copy link" and "End now".
- The link is a share link for anyone, living eight hours, starting on the page being edited; made on the first press, shown again while it lives, listed in "Share prototype" as the phone preview, ended by "End now" or with the design.
- On the phone: the page at the phone's own width with one thin line on top (the page's name, "Updated just now", the page list). It asks a light address whether the draft changed and redraws after a save, keeping the place scrolled to.
- A page for signed-in visitors is not drawn by a link for anyone: the window says so and offers a team link, opened after a sign-in on the phone.

## What must it keep?
- The QR code holds a secret: inline SVG only, the answer never cached, the token in no log, as the share links and the pairing code keep theirs (`api/api-device-pairing.md`).
- The asking is cheap and bounded: one read by the link's id, only while the phone's tab is seen, slowing down after ten minutes without a change and stopping after an hour; it counts no use of the link and stays under the firewall's limits (`waf/` is read first).
- Rights are those of "Share prototype": the roles that make a share link.
- Every rule of a share link stays: revoked, expired and unknown answer alike; the folder rule for drafts; the frame's signing.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- PP1 (M) Server: the phone link made, found again and ended; the changed-since address with its bounds; tests for the token kept out of logs and answers.
- PP2 (M) after PP1 and DD11: the window with the QR code, opened from the Preview menu.
- PP3 (M) after PP1 and DD11: the phone's view: the thin line, redraw after a save, the stop rules.
- PP4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column is expected (a `prototype_links` row is used as it is); a step that needs one stops and asks the architect.
- The drawing: `designer-drawings.md`. Claim as `[plan:designer-phone-preview] <step> — <topic>`.
