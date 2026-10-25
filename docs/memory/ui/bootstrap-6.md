---
name: bootstrap-6
description: Bootstrap 6 references for panel UI work: llms.txt and llms-full.txt, the four upstream skills in .claude/skills, the pinned alpha dist, and what changes at the switch
---

## Where are the Bootstrap 6 docs?
- Index of every docs page with a one-line summary: https://getbootstrap.com/llms.txt
- Full docs text in one file (about 1.3 MB; grep it by its `# <Component>` headings): https://getbootstrap.com/llms-full.txt
- Both serve the 6.0 docs since October 2026, while npm `latest` is still 5.3.8 and `next` is `6.0.0-alpha.1`.
- The docs run ahead of the shipped files: `.table-card`, `.placeholder-pulse` and `.form-adorn-sm`/`-lg` are documented but missing from the alpha.1 dist. Check every class and token against `assets/lib/bootstrap-6/css/bootstrap.css` and `js/bootstrap.js`, not only against the docs.

## Which skills are in the repo?
- `.claude/skills/bootstrap-v5-v6-migration`, `bootstrap-v6-install`, `bootstrap-color-system` and `bootstrap-component`: unchanged copies of `skills/` in twbs/bootstrap at 6ae3f86 (2026-10-24). Update them by copying the upstream folders again; never edit them. Project rules live here and in the plan.
- Left out on purpose: the v4-to-v6 migration, npm/vite/webpack/parcel (no bundler), utility-api and component-js (they need a Sass or TypeScript build; the panel uses the dist files).

## What applies to the panel?
- Only the panel moves. Sites (designs "with Bootstrap 5", classic themes, site forms, `frontend.src.js`, site renderers, palette and widget output, e-mails) stay on Bootstrap 5.3.8 and jQuery UI.
- No Sass build: the dist CSS, customised at runtime with `--bs-*` tokens (skill `bootstrap-color-system`, step 7).
- v6 JS is ESM-only: load the bundle (it includes Floating UI and Vanilla Calendar Pro) with `type="module"`. There is no `window.bootstrap` and no jQuery plugin unless the panel's bridge sets them.
- Until the switch the panel loads 5.3.8 (`includes/fn/output.php`): panel screens keep Bootstrap 5 markup. Plan and steps: `docs/memory/plans/bootstrap-6-panel.md`.

## What changes at the switch that panel rules rely on?
- v6 utilities sit in `@layer utilities` without `!important`, while `[hidden]` keeps `!important` in `@layer reboot` and beats `.d-flex`. The "d-none, never hidden" rule (`panel-layout-pitfalls.md`) is rewritten at the switch, when the panel CSS moves into `@layer custom`.
- Modals and offcanvas become native `<dialog>` in the top layer. z-index no longer lifts body-level overlays (jQuery UI calendar, select2 dropdown, toasts, chat bubble) above an open dialog; they must render inside it.
- Breakpoints: `lg` 992 to 1024 px, `xl` 1200 to 1280 px, `xxl` becomes `2xl`. `.fixed-top` z-index drops from 1030 to 1020.
- v6 class names collide with own panel classes: `.menu` (on the main nav), `.btn-text` (label spans in buttons), `.submenu`.
