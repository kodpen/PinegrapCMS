---
name: image-editor-unsplash
description: From Unsplash in the Artifact Canvas's Import (AC11): the item, the editor's dialog, a picture layer keeping the returned address and credit, its panel line, CORS for the save
module: image_editor
---

## Where is it offered?
- Import's menu (`image-editor-import.md`) gains From Unsplash when the site has its Unsplash key (`UNSPLASH_ACCESS_KEY`) and the panel's dialog layer (`pgUI`) is there: not on a site page in Edit mode, as the rich text editor's Unsplash… (`picker-editor-bridge.md`).
- `pg_image_editor_unsplash_config()` puts `unsplash` {key, scripts, texts} in the canvas config, null without a key. The key is Unsplash's public client id, handed to the browser as the editor's bridge does; never kept in code or memory.
- The dialog is `pgUnsplash.open()` of `pg_unsplash.js`, loaded from `pg_editor_bridge.min.js` the first time (`unsplashScript()`), unless the page has it already. With `photo: true` it answers `pgUnsplash.pure.photo()`: the returned address (`urls.regular`, only on `https://images.unsplash.com/`), size, alt text, name and both referral links.

## What does a picked photo become?
- Architect (2026-11-26): a picture layer that keeps the address the API returned, not a `files` row: the one exception to "an inserted picture is its own files row", because the API guidelines ask for hotlinking.
- `M.unsplashLayer()`: in the middle of the view, at most 60 % of it, `credit` {source `unsplash`, name, profile, page} and `alt`. The download address is called once, by the dialog, when the photo is picked.
- Its panel (the picture type's `panel`, first) shows "Photo by {name} on Unsplash", the name linking to the profile and Unsplash to the photo's page; `M.pictureCredit()` shows only a credit whose links are on `https://unsplash.com`.
- A save's pictures skip it (its src is no `data:` address); the saved PNG is a new composed picture, the site's file as any save.

## How does a save read its pixels?
- `picture()` (`pg_image_editor_layers.js`) loads an address `M.corsPicture()` accepts (only `https://images.unsplash.com/`) with `crossOrigin = 'anonymous'`; Unsplash's host answers CORS, so the save's canvas is not tainted. Other addresses load as before.
- The server's draw of a translated picture leaves every picture layer to a browser already (`core/picture-text-auto-server.md`, poll 143), so an Unsplash layer adds nothing there.

## What could not be checked?
- No browser run: the dialog over the canvas, the CORS load from images.unsplash.com and the save were not seen.

Tests: `tests/image_editor_unsplash_test.php`, `tests/editor_bridge_js_test.php` (`photo()`).
