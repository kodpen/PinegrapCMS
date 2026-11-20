---
name: designer-parts-on-demand
description: The designer's parts not needed to open a page: own bundles (pg_designer_parts()), the loader _sdPartLoad(), definePart and the kit for parts cut from the core, the parts (D5)
---

## How does a part of the editor load on first use?
- A part is a bundle of `pg_js_bundles()` named `assets/js/style_designer_<part>.min.js`, listed in `pg_designer_parts()` (`includes/fn/output.php`) and kept out of `style_designer.min.js` (else it runs twice; `tests/designer_parts_test.php`).
- `includes/designer_screen.php` hands the parts the operator may use to the editor as `sdDesign.parts` (`pg_designer_part_urls()`: name => file addresses, the `.min.js` or the sources in `src` mode). No `<script>` tag for a part. A new part goes into that list too, or it is refused: `spacing` was missing until #10658.
- `_sdPartLoad(name)` (section `// ── Parts on demand` in `style_designer.src.js`) appends the files one after the other and answers a promise that settles when the last has run; asked again, the same promise; after a failure the element is removed and the next ask tries again; a part not handed over is refused. `_sdPartLoaded(name)` says whether it has run.

## How does a part cut out of the core reach the editor?
- Its file `assets/js/style_designer_<part>.js` is one call: `StyleDesigner.definePart('<part>', function (K) { 'use strict'; var esc = K.esc; … return { open: …, apply: … }; });`. `_sdDefinePart()` calls the factory once and keeps the entry points; the core's stub runs one with `_sdPartCall(part, method, args)` and `.catch(_sdPartFailed)` (a toast).
- `K` is `_sdPartKit()`, built when the first part runs: the core functions parts use (aliased at the top of the part) and the core state as getters and setters (`K.pages`, `K.activeKey`…), so a part reads the live value. A part needing a new core name adds it to the kit.
- `tests/designer_parts_test.php` fails a part that uses a core name outside the kit or a `K.<name>` the kit lacks.
- The readers of `_sdT()` keys read `pg_designer_js_all_sources()` (core, families, then the parts' sources); `tools/check_lang.php` scans every `style_designer_<name>.js`. A test that cut a moved function from `style_designer.src.js` reads the part file too.

## Which parts are there?
- `ai`: the assistant panel, `assets/js/designer_ai.js` (#8112, D5 part 1). Handed over only when `sdDesign.ai.enabled`. The toolbar button `#sd-ai-btn` and the canvas menu's "Ask the assistant about this" run it (`_sdAiRun()`); after it ran, its own listener answers the button. It also runs 1.5 s after `init()` (at once for a proposal link, `sdDesign.ai.proposal`), so requests still waiting from an earlier visit keep being carried on (its `ai_state` poll).
- `import` (#8131, D5 part 2): `assets/js/style_designer_import.js`, the HTML / ZIP import dialog and the reply opened as tabs (also the paste-a-page dialog's). Handed to every editor. The core keeps `_pgImportOpen()` and `_pgImportApply(d, projectName)` as stubs.
- `code` (#8166, D5 part 3; #8213, part 4): the code view's runtime and its typed-changes engine, `designer-code-view-part.md`.
- `theme` (#8245, D5 part 5): the Themes tab of the assets panel, `designer-theme-part.md`.
- `fonts` (#8268, D5 part 6): the Google Fonts browser, `designer-fonts-part.md`.
- `interactions` (#8281, D5 part 7): the Interactions section's rows and fields, `designer-interactions-part.md`.
- `treesearch` (DP13), `move` (DP16): the Overview's search and "Move…" window: `overview-search-map.md`, `overview-move-without-drag.md`.
- `optsearch` (DP8): `designer-options-search.md`; `pages` (DP20): `designer-pages-window.md`.
- `comments` (#10042): prototype comments, `designer-prototype-comments-list.md`.
- `spacing` (DP7): Style › Spacing's box, `designer-spacing-box.md`.
- Assistant code kept in the core for open: `_sdAiBridge()`, the "Write with AI" marks (`_pgAiMarks*`), `_sdAiAltButton()`.

## What is not measured?
- `open` with `?sd_perf=1` (`designer-performance.md`) needs a browser on a running site (D5 is complete).
