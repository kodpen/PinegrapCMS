---
name: image-editor-openverse
description: Free photos (Openverse) in the Artifact Canvas's Import (AC13): keyless CC0 and public domain search, pixels via its proxy, a picture layer saved as a file with its credit
module: image_editor
---

## Where is it offered?
- Import's menu (`image-editor-import.md`) always has Free photos (Openverse): Openverse needs no key, so a site without an Unsplash key has a photo search too, on a panel screen and on a site page in Edit mode.
- It opens a box over the editor (`this.confirmBox`, as From the site without the picker): a search field, the note "Only photos free of copyright are shown: CC0 and public domain.", tiles with the creator's name under each, and More photos while a next page is there. The search waits 500 ms after typing.

## What is asked, and under which licence?
- The browser asks `https://api.openverse.org/v1/images/` itself (CORS `*`), keyless: `license=cc0,pdm`, `category=photograph`, `page_size=20` (the keyless maximum) (`M.openverseListUrl()`). A keyless caller gets 100 calls a minute and 1,000 a day per IP; a 429 shows "Openverse is busy. Try again later."
- Licence: only CC0 and the Public Domain Mark. `M.openverseRecord()` drops any other record, and one whose id is no UUID; it keeps creator, title, licence and original page, links only over https.
- Tile and pixel addresses are built from the id on Openverse's own thumbnail proxy (`/v1/images/{id}/thumb/`, `?full_size=true` for the photo), never taken from the record, so only that host is fetched.

## What does a picked photo become?
- Unlike Unsplash (`image-editor-unsplash.md`), an ordinary picture layer: the full-size pixels are fetched (the plain thumbnail when that fails), read by `readPicture()` of `pg_image_editor_layers.js` (at most 2000 px, PNG or JPEG, as `addPicture()`), so its src is a `data:` address and a save keeps it as the site's own `files` row.
- `M.openverseLayer()`: middle of the view, at most 60 % of it, `fileName` and `alt` the title, `credit` {source `openverse`, title, creator, creatorUrl, license, licenseUrl, page}.
- Its panel (the picture type's `panel`, first) shows "Photo by {creator} via Openverse · {licence} · Original page", each linked where the record gave an https address (`M.openverseCredit()`). CC0 and PDM ask for no credit; it is kept as Openverse asks, and so a person can check the source.

## What does the panel's content policy need?
- `waf_csp_policy()`'s connect-src lists `https://api.openverse.org` (`waf/waf-csp-builtin-and-reporter.md`); tiles load under img-src `https:`.

## What could not be checked?
- No browser run: the box, the CORS answer of the full-size proxy and the save were not seen; Openverse refused this sandbox's shared IP (daily limit).

Tests: `tests/image_editor_openverse_test.php`.
