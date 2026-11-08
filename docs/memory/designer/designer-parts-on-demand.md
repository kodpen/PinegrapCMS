---
name: designer-parts-on-demand
description: The visual designer's parts not needed to open a page: their own bundles (pg_designer_parts()), sdDesign.parts, the loader _sdPartLoad(), the assistant part (D5)
---

## How does a part of the editor load on first use?
- A part is a bundle of `pg_js_bundles()` named `assets/js/style_designer_<part>.min.js`, listed in `pg_designer_parts()` (`includes/fn/output.php`) and kept out of `style_designer.min.js` (a source in both would run twice; `tests/designer_parts_test.php`).
- `includes/designer_screen.php` hands the parts the operator may use to the editor as `sdDesign.parts` (`pg_designer_part_urls()`: name => file addresses, the `.min.js` or the sources in `src` mode). No `<script>` tag for a part.
- `_sdPartLoad(name)` (section `// ── Parts on demand` in `style_designer.src.js`) appends the files one after the other and answers a promise that settles when the last has run; asked again, the same promise; after a failure the element is removed and the next ask tries again; a part not handed over is refused. `_sdPartLoaded(name)` says whether it has run.

## Which parts are there?
- `ai`: the assistant panel, `assets/js/designer_ai.js` (#PR, D5 part 1). Handed over only when `sdDesign.ai.enabled`. The toolbar button `#sd-ai-btn` and the canvas menu's "Ask the assistant about this" run it (`_sdAiRun()`); after it ran, its own listener answers the button. It also runs 1.5 s after `init()` (at once for a proposal link, `sdDesign.ai.proposal`), so requests still waiting from an earlier visit keep being carried on (its `ai_state` poll) as when it was a plain script tag.
- The assistant code that stays in the core is small and needed at open: `_sdAiBridge()` (the part's view of the editor), the "Write with AI" marks of the page fields (`_pgAiMarks*`) and the alt-text button (`_sdAiAltButton()`).

## What comes next?
- The rest of D5 (`plans/designer-performance-and-publish.md`): code view, theme panel, Google fonts browser, interactions, import cut out of the core into `style_designer_<part>.js`, one PR each, loaded through `_sdPartLoad()`. A part cut from the core reaches the editor's closure state only through what the core hands it, as `designer_ai.js` does through `StyleDesigner.aiBridge()`.
- `open` with `?sd_perf=1` (`designer-performance.md`) still needs a browser on a running site: not measured before or after this part.
