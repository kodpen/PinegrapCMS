---
name: bootstrap-6
description: Bootstrap 6 references for panel UI work: llms.txt and llms-full.txt, the four upstream skills in .claude/skills, the dist copy, what moves and what changes at the switch
---

## Where are the Bootstrap 6 docs?
- Index of every docs page: https://getbootstrap.com/llms.txt
- Full docs text in one file (1.3 MB; grep its `# <Component>` headings): https://getbootstrap.com/llms-full.txt
- Both serve the 6.0 docs (checked 2026-10-25); npm `latest` is 5.3.8, `next` is `6.0.0-alpha.1`.
- `assets/lib/bootstrap-6` holds npm `6.0.0-alpha.1` (`document.txt`: source, sha512, CDN SRI). The docs run ahead of it: `.form-adorn-sm`/`-lg` are documented but missing. Check classes and tokens against the dist, not only the docs. Line numbers quoted from an earlier preview copy (header `v6.0.0-alpha1`) no longer match.

## Which skills are in the repo?
- `.claude/skills/bootstrap-v5-v6-migration`, `bootstrap-v6-install`, `bootstrap-color-system`, `bootstrap-component`: unchanged copies of twbs/bootstrap `skills/` at 6ae3f86 (2026-10-24); MIT notice in `.claude/skills/LICENSE-bootstrap.txt`. Update by copying the upstream folders again; never edit them.
- They assume Bootstrap's own Sass and npm setup; the panel has neither. Take from `bootstrap-component` only its markup, token and layer conventions, from `bootstrap-color-system` only "Step 7: Runtime overrides". Panel CSS stays plain CSS.
- Left out: v4-to-v6 migration, npm/vite/webpack/parcel, utility-api, component-js. The skills never apply to site output (`designer/README.md`).

## What moves to Bootstrap 6?
- The panel, and every screen `output_header_secure()` prints, since it prints the panel head: sign-in, MFA, device limit, set password, the OAuth consent, print views, the installer. Visitors see these too.
- Sites stay on 5.3.8 and jQuery UI: designs "with Bootstrap 5", classic themes, site forms, `frontend.src.js`, site renderers, palette and widget output, e-mails. Renderers that print the same markup on both sides (liveform notices, `get_set_password()`, `get_calendar()`, chat.php, `pg_seo_page_toolbar()`) choose classes at run time.
- v6 JS is ESM-only: the bundle (with Floating UI and Vanilla Calendar Pro) loads with `type="module"`; `window.bootstrap` exists only if the panel's bridge sets it, and there are no jQuery plugins.
- Until the switch (`plans/bootstrap-6-panel.md`) panel screens keep Bootstrap 5 markup.

## What changes at the switch that panel rules rely on?
- v6 utilities sit in `@layer utilities` without `!important`, and any unlayered rule beats them; `[hidden]` keeps `!important` in `@layer reboot`. Every panel stylesheet and inline `<style>` block moves into `@layer custom`, and the "d-none, never hidden" rule (`panel-layout-pitfalls.md`) is rewritten.
- Modals and offcanvas become native `<dialog>` in the top layer: z-index no longer lifts body-level overlays (jQuery UI calendar, select2 dropdown, toasts, chat bubble) above an open dialog.
- Breakpoints: `lg` 992 to 1024 px, `xl` 1200 to 1280 px, `xxl` (1400) becomes `2xl` (1536). `.fixed-top` z-index drops from 1030 to 1020.
- v6 names collide with own classes: `.menu` (main nav), `.btn-text` (button label spans), `.submenu`. Step B4 renamed them: label spans are `.pg-btn-label`, the file manager's flyout (`view_folders.php`) is `.pg-submenu`, `#menu` has no `class="menu"`; the account and notification toggles carry `data-bs-display="static"`. `php tools/bs6_rename_collisions.php [--write] [--skip=a,b] [paths]` renames `btn-text` in panel files only.
