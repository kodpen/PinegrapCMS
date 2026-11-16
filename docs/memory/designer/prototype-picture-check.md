---
name: prototype-picture-check
description: The prototype canvas page picture's technical check (PV7): the Preview document in a script-free readable frame, its sandbox, the link marker, the cap on live pictures
---

## Which way does the prototype canvas draw a page?
- The Preview's own document for the tab's tree: `generateHTML(name, classes, tree)` with the widget markers, `preview_widgets` with that tab's page id, `_sdPreviewDocHtml()`; set as `srcdoc` of a 1280-wide frame, cut at 1280×1600 and scaled to the frame. It shows unsaved work, system widgets as the Preview draws them (a signed-out visitor) and the canvas's dark view.
- The picture document gets one rule, `*,*::before,*::after{animation:none!important;transition:none!important}`: same-origin frames share the editor's main thread, and 41 moving pages halved the canvas frame rate (pan median 33 ms instead of 16.7).
- Not `PGLivePicture` on the saved address: it shows the saved page only (an unsaved tab its old state, a new tab nothing), its opaque frame cannot be read (no rectangle), it draws for the signed-in operator (the site's edit toolbar shows) and a part that needs a script shows its no-script text (the e-mail cloak). It was also slower and heavier: `prototype-picture-check-numbers.md`.

## Which sandbox does the picture need, and why is it safe?
- `sandbox="allow-same-origin"` and no other flag, plus `pointer-events:none`, `tabindex="-1"`, `aria-hidden` (F1). Same origin lets the editor read the picture's DOM; the picture can never read the editor, as no script of it runs.
- No `allow-scripts`: no inline, file or event-attribute script runs, and Chromium refuses a meta refresh. No `allow-forms`: a form posts nothing. No `allow-top-navigation` or `allow-popups`. Checked in Chromium on the sandbox: none reached the server.
- `allow-same-origin` is safe only without `allow-scripts`: the pair lets a page lift its own sandbox. The frame's builder never adds `allow-scripts`; PV8 tests the attribute.
- What it loads: the stylesheets, fonts and images the page names (the site, Google Fonts, jsDelivr), as the canvas and the Preview do. It sends nothing else.

## How is a link's element found in the picture?
- A marker written only while a picture is built: a flag beside `_sdPreviewWidgetMarkers` makes `_sdEmitNode()` add `data-pg-pic="<node _id>"` to the first tag of a node whose link is its own (sources href, attrs, btn), and `data-pg-pic="<_id>.<n>"` to the n-th `<a` of a text or custom HTML node (the index `htmlLinks()` gives). Inside a shared component the value starts `s<shared id>:`, so a page frame skips it (F4) and the shared frame reads it (F13).
- Never saved or published: Save, Publish, the code view and the Preview build with the flag off, as for the widget markers. PV8 tests that `generateHTML()` without it writes no `data-pg-pic`.
- Read after `load`: `contentDocument.querySelectorAll('[data-pg-pic]')`, `getBoundingClientRect()` in 1280 units times the frame's scale. Zero size is hidden (a closed menu) and a top past 1600 is under the cut: both count in the foot (F3).
- Checked: 83 of 83 links the collector finds on the 41 pages had their marker; all 1,017 markers (shared parts once per page) read in 46-65 ms, at most 17 ms a picture.

## What were the numbers?
- `prototype-picture-check-numbers.md`: 41 pages, first picture 0.1-0.3 s, last 1.7-2.0 s at Fit, about 4 MB a picture, 60 fps pan and zoom.

## How many pictures may live at once?
- Made when its frame comes within 200 px of view; at most 6 loading at once, the rest queued.
- At most 48 alive (six rows of eight, F6), about 200 MB; past that the picture farthest from view goes and its frame shows the sketch again. A picture is rebuilt only for a tab whose tree changed ("Read the links again").
