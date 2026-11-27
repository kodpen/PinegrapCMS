---
name: designer-phone-preview-rules
description: "Preview on a phone": why it exists, the rules every change keeps (QR code a secret, bounded asking, Share prototype rights), which file holds which part
---

## Why is there a phone preview?
- Owner (2026-11-24): the draft is seen on the real phone, with its own browser, fonts and thumb, while it is being designed (`workflow/decisions-made-product-106.md`). The canvas bar narrowing the canvas to a phone's width is not a phone.
- Built in three steps: the server #11541, the window with the QR code #11578, the phone's view #11582.

## What is it, in short?
- "Preview on a phone", second row of the Preview menu, opens a window with a QR code of a share link for anyone, living eight hours, starting on the page being edited. "Copy link" and "End now"; the link is listed in "Share prototype" with the chip "Phone preview".
- The phone draws the page at its own width under one thin line (page name, "Updated just now", the page list) and redraws after each save in the designer, keeping the place scrolled to.
- A page for signed-in visitors is not drawn by a link for anyone: the window offers a team link, opened after a sign-in on the phone.
- A design with no draft pages gets the QR code of its live page, with no share link behind it (#11968, `designer-phone-preview-live.md`).

## What must every change keep?
- The QR code holds a secret: inline SVG only (`pg_qr_svg()`), the answer sent `no-store`, the token in no log, no image address and no copy in storage, as the share links and the pairing code keep theirs (`api/api-device-pairing.md`).
- The asking is cheap and bounded: one read by the link's id, only while the phone's tab is seen, slower after ten minutes without a change, stopped after an hour; it counts no use of the link and stays under the firewall's limits (`waf/` is read before changing it).
- Rights are those of "Share prototype": only the roles that make a share link see the row and make a phone link.
- Every share-link rule stays: revoked, expired and unknown links answer alike; the folder rule for drafts; the frame's signing (`designer-prototype-share-links.md`, `designer-prototype-share-link-frame.md`).
- No table or column: a `prototype_links` row is used as it is, the phone link known by its eight-hour span. A change that needs a column asks the architect first.
- No button in the page bar, the canvas bar or a panel's head: only the Preview menu row and the command palette (`designer-light-and-simple.md`).

## Which file holds what?
- Server, the link, the changed-since address and its pace: `designer-phone-preview-server.md`.
- A design with no draft pages, the live page's code: `designer-phone-preview-live.md`.
- The window as built: `designer-phone-preview-window.md`; as drawn: `designer-phone-preview-drawing-window.md`.
- The phone's view as built: `designer-phone-preview-phone.md`; as drawn: `designer-phone-preview-drawing-phone.md`.
- The drawing sheet and its boards: `designer-phone-preview-drawing.md`.
