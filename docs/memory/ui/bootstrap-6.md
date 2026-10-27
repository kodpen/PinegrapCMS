---
name: bootstrap-6
description: The panel on Bootstrap 6 (switch #1866, tag bs6-switch): docs and skills, the dist copy, what is v6 and what stays 5.3.8, layers and breakpoints, own class names, a branch older than the switch
---

## Where are the Bootstrap 6 docs?
- Index of every docs page: https://getbootstrap.com/llms.txt; full text in one file (1.3 MB; grep its `# <Component>` headings): https://getbootstrap.com/llms-full.txt. Both serve the 6.0 docs.
- `assets/lib/bootstrap-6` holds npm `6.0.0-alpha.1` (`document.txt`: source, sha512, CDN SRI). The docs run ahead of it (`.form-adorn-sm`/`-lg` are documented but missing): check classes and tokens against the dist, not only the docs.

## Which skills are in the repo?
- `.claude/skills/bootstrap-v5-v6-migration`, `bootstrap-v6-install`, `bootstrap-color-system`, `bootstrap-component`: unchanged copies of twbs/bootstrap `skills/` at 6ae3f86 (MIT notice in `.claude/skills/LICENSE-bootstrap.txt`). Update by copying the upstream folders again; never edit them.
- They assume Bootstrap's Sass and npm setup; the panel has neither. Take from `bootstrap-component` its markup, token and layer conventions, from `bootstrap-color-system` only "Step 7: Runtime overrides". Panel CSS stays plain CSS. They never apply to site output.

## What runs Bootstrap 6 and what stays on 5.3.8?
- Bootstrap 6: the panel and every screen `output_header_secure()` prints (sign-in, MFA, device limit, set password, OAuth consent, print views, the installer), and the visual editor's own chrome and dialogs.
- 5.3.8 and jQuery UI: sites (designs, classic themes, site forms, `frontend.src.js`, site renderers, palette and widget output, e-mails) and the editor's canvas. Renderers printing on both sides choose at run time: `get_calendar()` by `$user`, liveform notices through v5 alert rules the panel maps to v6 themes.
- v6 JS is ESM-only: `pg_bs6_bridge.js` imports the bundle, sets `window.bootstrap`, fires `pg:bootstrap-ready`; panel code reaches Bootstrap only through `pgUI` (`pg-ui-adapter.md`). No jQuery plugins.

## What do panel rules rely on?
- Layer order: colors, config, root, reboot, layout, content, forms, components, vendor, custom, helpers, utilities. Panel stylesheets and inline `<style>` blocks sit in `@layer custom`: they beat any Bootstrap component rule whatever its weight, utilities beat them. Utilities carry no `!important`; `[hidden]` does.
- Dialogs and drawers are native `<dialog>` in the top layer: z-index does not lift body-level overlays over them (`pgUI.overlayHost()`).
- Breakpoints: `sm` 576, `md` 768, `lg` 1024, `xl` 1280, `2xl` 1536, written as prefixes (`lg:col-6`). The panel's own media queries use the same widths (`workflow/decisions-made-panel.md`). `.fixed-top` z-index is 1020.
- v6 owns `.menu`, `.btn-text`, `.submenu`: label spans are `.pg-btn-label`, the file manager's flyout `.pg-submenu`.

## What does a branch older than the switch do?
- Merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` once (only its own added lines: a second run grows converted sizes), then `php tools/check_bs6.php --strict`; CI fails it until then (`bootstrap-6-codemod.md`).
- The switch merge is tagged `bs6-switch`. A look difference found after it is fixed forward in a follow-up PR; a revert only during its freeze window.
