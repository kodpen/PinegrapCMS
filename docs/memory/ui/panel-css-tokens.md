---
name: panel-css-tokens
description: The panel's --pg-* CSS token layer (Bootstrap 6 step B3): names rules read, the alias block on :root and [data-bs-theme], color-mix() instead of -rgb, what is converted
---

## What may a panel rule read?
- Only `--pg-*` tokens, never Bootstrap's `--bs-*` variables directly (`check_bs6.php` counts what is left). Bootstrap 6 renames or drops most v5 variables; the switch then changes one block, not every rule.
- Accent fills: the palette `--pg-primary`, `--pg-secondary`, `--pg-success`, `--pg-danger`, `--pg-warning`, `--pg-info` (Bootstrap's `--bs-<colour>` are bridged from them in the first `:root` block).
- Accent text, surface, line: `--pg-<colour>-fg-emphasis`, `-bg-subtle`, `-border-subtle` (v5 `-text-emphasis`, `-bg-subtle`, `-border-subtle`). The palette's `-ink`, `-subtle`, `-line` are inputs of the bridge, not names for rules.
- Neutrals: `--pg-bg-body`, `--pg-bg-1` (v5 tertiary-bg), `--pg-bg-2` (secondary-bg), `--pg-fg-body`, `--pg-fg-emphasis`, `--pg-fg-2` (secondary-color), `--pg-fg-3` (tertiary-color), `--pg-border-color`, `--pg-border-color-translucent`, `--pg-border-width`, `--pg-border-style`, `--pg-radius-sm` / `--pg-radius` / `-lg` / `-xl` / `-2xl` / `-pill`, `--pg-font-mono`, `--pg-link-color`, `--pg-shadow`, `--pg-shadow-lg`, `--pg-navbar-bg`.
- Missing a name? Add it to the token block, never read `--bs-*` in a rule.

## How is a colour with alpha written?
- `color-mix(in srgb, var(--pg-primary) 12%, transparent)`, not `rgba(var(--bs-primary-rgb), .12)`: v6 has no `-rgb` triples. Mixing with `transparent` gives the same colour at that alpha. A variable alpha: `calc(var(--bs-bg-opacity) * 100%)`.

## Why is the alias block on `:root, [data-bs-theme]`?
- A `var()` inside a custom property resolves where it is declared and is inherited already resolved. Declared on `:root` only, a nested `data-bs-theme="dark"` subtree (pg_ai_write.js sets one) would keep the light values. Declaring the aliases on every `[data-bs-theme]` element resolves them again there.
- A rule that sets a Bootstrap variable on a subtree (`.bg-software-navbar .border-end { --bs-border-color }`) does not reach the tokens: set the `--pg-*` token there instead.
- `--bs-focus-ring-color` has no alias: Bootstrap's `.focus-ring-*` classes set it per element.
- An alias never names itself or a `--bs-*` variable the bridge fills from the same alias: that is a cycle and both turn invalid.

## Which stylesheets have the block?
- `assets/css/backend.src.css` (after the dark palette block) for every screen that prints the panel head, inline `<style>` blocks included.
- `assets/css/workspace_guest.css` has its own short block: the guest page does not load backend.src.css.
- Site CSS, `style_designer.css` and `page_designer.css` (editor, step B7) and JavaScript (step B2b) are outside this layer.

## What is converted so far?
- B3a (#PR): the token block; every v5 `-rgb` use in panel CSS and the installer's, api_settings, marketplace_settings and view_folders style blocks (`check_bs6.php` RGB count 130 to 21; the rest are the token layer's own two lines, the editor, JavaScript and `design_themes.php`, whose values go into site designs); the accent role variables in backend.src.css and workspace_guest.css.
- Still reading `--bs-*`: the neutral variables in backend.src.css (about 1,000 uses), the other panel stylesheets and inline style blocks: steps B3b and B3c in `plans/bootstrap-6-panel-steps.md`.
