---
name: designer-parts-on-demand
description: The designer's parts not needed to open a page: one file each in includes/designer_parts/, their conditions, the loader _sdPartLoad(), definePart and the sorted kit for parts cut from the core
module: output
---

## Where is a part described?
- One file per part, `includes/designer_parts/<name>.php` (name `[a-z0-9]+`), header block and gate constant, returning `array('sources' => array(...), 'when' => '<condition>')`. The sources are its files in load order; its bundle is `assets/js/style_designer_<name>.min.js`. A comment in the file names its memory file.
- `pg_designer_part_files()` (`includes/fn/output.php`) reads the folder once per request, names sorted. `pg_designer_parts()` (name => bundle) and the part bundles of `pg_js_bundles()` come from it; no part is listed by hand.
- Owner (2026-11-25): adding a part is one new file, no shared list edited (steps adding parts kept clashing in the same lines; #PR).

## Which conditions may a part have?
- `when` is one of `pg_designer_part_conditions()`: `always`, `designer` (full designer access), `ai` (`sdDesign.ai.enabled`), `comments` (designer and comments ready), `phone`, `publishcheck` (designer, draft table ready, not a component), `clicks` (`sdDesign.clicks` set), `off` (never handed; `mirror` until its switch).
- `includes/designer_screen.php` computes those conditions once and hands `pg_designer_parts_for($conditions)` as `sdDesign.parts` (`pg_designer_part_urls()`: name => file addresses, the `.min.js` or the sources in `src` mode). An unknown `when` never holds. Only `libraryfile` is `designer`.
- A part whose button any editor may press is `always`: the core decides when to load it. `focus`, `overrides` and `sketchread` were missing from the old hand list and showed the part-failed toast; they are `always` now.

## How does a part load on first use?
- No `<script>` tag for a part; one not handed is refused. Parts are kept out of `style_designer.min.js`.
- `_sdPartLoad(name)` (section `// ── Parts on demand` in `style_designer.src.js`) appends the files one after the other and answers a promise that settles when the last has run; asked again, the same promise; after a failure the element is removed and the next ask tries again. `_sdPartLoaded(name)` says whether it has run.

## How does a part cut out of the core reach the editor?
- Its file `assets/js/style_designer_<part>.js` is one call: `StyleDesigner.definePart('<part>', function (K) { 'use strict'; var esc = K.esc; … return { open: …, apply: … }; });`. `_sdDefinePart()` calls the factory once; the core's stub runs one with `_sdPartCall(part, method, args)` and `.catch(_sdPartFailed)` (a toast).
- `K` is `_sdPartKit()`: the core functions parts use and the core state as getters and setters (`K.pages`, `K.activeKey`…). A part needing a new core name adds it to the kit. The kit's keys (the object literal and its `Object.defineProperties`) are in byte order, one per line with a trailing comma, so two steps rarely touch the same line.
- The readers of `_sdT()` keys read `pg_designer_js_all_sources()` (core, families, then the parts' sources); `tools/check_lang.php` scans every `style_designer_<name>.js`.

## Which notes are kept here?
- `ai` (#8112, `assets/js/designer_ai.js`) also runs 1.5 s after `init()` (at once for a proposal link), so waiting requests go on. Assistant code kept in the core: `_sdAiBridge()`, the "Write with AI" marks (`_pgAiMarks*`), `_sdAiAltButton()`.
- `import` (#8131): the HTML / ZIP import and the paste-a-page reply opened as tabs; the core keeps `_pgImportOpen()` and `_pgImportApply()` as stubs.

## What do the tests check?
- `tests/designer_parts_test.php`: each file's header, gate, memory file, known `when` and existing sources; the bundles equal the files; `pg_designer_parts_for()` per condition; the screen's condition rules; the kit order; a part using a core name outside the kit or a `K.<name>` the kit lacks.
- Not measured: `open` with `?sd_perf=1` (`designer-performance.md`) needs a browser on a running site.
