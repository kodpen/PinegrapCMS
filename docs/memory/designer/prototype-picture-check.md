---
name: prototype-picture-check
description: The prototype canvas page picture (PV7, architect 2026-11-16): the Preview document in a script-free readable frame, its sandbox and CSP, the link marker, the cap on live pictures
---

## Which way does the prototype canvas draw a page?
- The Preview's own document for the tab's tree (`generateHTML(name, classes, tree)` with the widget markers, `preview_widgets` with that tab's page id, `_sdPreviewDocHtml()`) as `srcdoc` of a 1280-wide frame, cut at 1280×1600, scaled. It shows unsaved work, widgets as the Preview draws them (signed out) and the canvas's dark view.
- Its head gets `*,*::before,*::after{animation:none!important;transition:none!important}`: same-origin frames share the editor's main thread, and 41 moving pages halved the frame rate.
- Not `PGLivePicture` on the saved address: saved state only, an opaque frame no rectangle can be read from, drawn for the signed-in operator (the edit toolbar shows). Numbers: `prototype-picture-check-numbers.md`.
- Architect (2026-11-16, agent channel message 5773): this way, the stilling rule, the marker and the caps confirmed.

## Which sandbox does the picture need, and why is it safe?
- One builder writes `sandbox` as exactly `allow-same-origin` (PV8 tests the value), plus `pointer-events:none`, `tabindex="-1"`, `aria-hidden`. The editor reads the picture's DOM; no script of the picture runs, so it reads nothing back.
- No script runs, Chromium refuses a meta refresh, a form posts nothing, no top navigation or popup: checked on the sandbox, nothing reached the server.
- Second lock, in the picture's `<head>`: CSP meta `script-src 'none'; object-src 'none'; frame-src 'none'; media-src 'none'; form-action 'none'`, so a later `allow-scripts` still runs no script. If it breaks something the Preview document needs, the PR names the directive; `script-src` and `form-action` always stay.
- What it loads: the page's stylesheets, fonts and images (the site, Google Fonts, jsDelivr). Embedded frames (maps, videos) and media load nothing; PV8 checks in Chromium and hides a "blocked" box with a style rule that keeps its place.
- `loading="lazy"` does not work without scripts: images under the cut are fetched too (see the numbers file).

## How is a link's element found in the picture?
- A flag beside `_sdPreviewWidgetMarkers`, set and cleared in `try`/`finally` as that one is, makes `_sdEmitNode()` write `data-pg-pic="<node _id>"` on the first tag of a node whose link is its own (href, attrs, btn) and `"<_id>.<n>"` on the n-th `<a` of a text or custom HTML node (`htmlLinks()`'s index). Inside a shared component the value starts `s<shared id>:`: a page frame skips it, the shared frame reads it.
- Never saved or published: a flag left on would write the marker through Save. PV8 tests that after a `generateHTML()` that throws the flag is off and the next build writes no `data-pg-pic`.
- Read after `load`: `querySelectorAll('[data-pg-pic]')`, `getBoundingClientRect()` in 1280 units times the scale. Zero size is hidden, a top past 1600 under the cut: both count in the foot. 83 of 83 links had their marker.

## How many pictures may live at once?
- Made within 200 px of view; at most 6 loading at once, and the `preview_widgets` requests go through the same queue of six.
- At most 48 alive (six rows of eight), about 200 MB at 4 MB a picture; past that the one farthest from view goes back to the sketch. PV8 measures once on an image-heavy store template; above 8 MB a picture it lowers the cap itself and writes the number here.
