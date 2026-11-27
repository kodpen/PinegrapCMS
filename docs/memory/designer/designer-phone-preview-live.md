---
name: designer-phone-preview-live
description: "Preview on a phone" in a design with no draft pages (#11968): the QR code of the live page with no share link, which page, the window's live state, what it never does
module: prototype_phone
---

## What does a design with no draft pages show?
- Owner (2026-11-26): the QR code of the live page (`workflow/decisions-made-product-115.md`). Before #11968 the window said "The link could not be made" over "This design has no draft pages." (#11966).
- `prototype_phone` answers `{status: success, live: true, url, start_page, qr}` when the design has no draft: `url` is the page's public address (`URL_SCHEME . HOSTNAME_SETTING . PATH` + `encode_url_path(page_name)`), `qr` inline SVG from `pg_qr_svg()`, sent `no-store` as every answer of the row.
- Which page: the page being edited when it is on the site, else the design's first page on the site in tab order (`pg_proto_phone_live_page()` over `pg_proto_design_live_pages()`: visual-design pages outside the Drafts folder and the Recycle Bin).
- A design with no page on the site either keeps the #11966 answer: an error with "This design has no draft pages.", no "Try again".
- A design with at least one draft is unchanged: the share link of `designer-phone-preview-server.md`.

## Why is no share link made?
- A public address holds no secret: no `prototype_links` row is found, made or listed, nothing new in "Share prototype", no countdown, no "End now". The phone gets the site's ordinary page: no thin line, no page list, no redraw after a save; a page for signed-in visitors asks for the site's own sign-in, so no team link is offered.
- The answer is built before any link is looked for (`pg_proto_phone_live_answer()` comes before `pg_proto_phone_pick()` in `pg_proto_phone_panel()`; the test holds that order).

## How does the window draw it?
- `_phView()` gives `live` for an answer with `live`, `url` and `qr` (else `failed`). It draws the code, "Point your phone's camera here", "Opens <page> on the site" and one line "This design has no draft pages, so the code opens the live page as visitors see it."
- A live tab with its own unsaved changes gets the new version's line and button under that line (`designer-new-version-phone-compare.md`); no other save line.
- Foot: "Copy link" (the address), "Open here" below 768 px; `_phFoot(on, live)` leaves out "End now". `_phTick()` does nothing outside `ready`, so nothing counts down.
- The core's `_sdPhoneHost()` sends the id of any saved page, draft or on the site; the server takes a draft link's start only from a draft (`pg_proto_phone_plan()` falls back to the first draft).

Tests: `tests/prototype_phone_test.php`, `tests/designer_phone_window_test.php`.

Related: docs/memory/designer/designer-phone-preview-window.md, docs/memory/designer/designer-phone-preview-server.md
