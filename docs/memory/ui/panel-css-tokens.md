---
name: panel-css-tokens
description: The panel's --pg-* CSS token layer (Bootstrap 6 step B3): names rules read, the alias block on :root and [data-bs-theme], color-mix() instead of -rgb, what is converted
---

## What may a panel rule read?
- Only `--pg-*` tokens, never Bootstrap's `--bs-*` variables directly (`check_bs6.php` counts what is left): Bootstrap 6 renamed or dropped most v5 variables.
- Accent fills: the palette `--pg-primary`, `--pg-secondary`, `--pg-success`, `--pg-danger`, `--pg-warning`, `--pg-info` (Bootstrap's `--bs-<colour>` are bridged from them in the first `:root` block).
- Accent text, surface, line: `--pg-<colour>-fg-emphasis`, `-bg-subtle`, `-border-subtle` (v5 `-text-emphasis`, `-bg-subtle`, `-border-subtle`). The palette's `-ink`, `-subtle`, `-line` are inputs of the bridge, not names for rules.
- Neutrals: `--pg-bg-body`, `--pg-bg-1` (v5 tertiary-bg), `--pg-bg-2` (secondary-bg), `--pg-fg-body`, `--pg-fg-emphasis`, `--pg-fg-2` (secondary-color), `--pg-fg-3` (tertiary-color), `--pg-border-color`, `--pg-border-color-translucent`, `--pg-border-width`, `--pg-border-style`, `--pg-radius-sm` / `--pg-radius` / `-lg` / `-xl` / `-2xl` / `-pill`, `--pg-font-mono`, `--pg-link-color`, `--pg-shadow`, `--pg-shadow-lg`, `--pg-navbar-bg`.
- Missing a name? Add it to the token block, never read `--bs-*` in a rule.

## How is a colour with alpha written?
- `color-mix(in srgb, var(--pg-primary) 12%, transparent)`, not `rgba(var(--bs-primary-rgb), .12)`: v6 has no `-rgb` triples. Mixing with `transparent` gives the same colour at that alpha. A variable alpha: `calc(var(--bs-bg-opacity) * 100%)`.

## Why is the alias block on `:root, [data-bs-theme]`?
- A `var()` in a custom property resolves where declared and is inherited resolved. Declared on `:root` only, a nested `data-bs-theme="dark"` subtree (pg_ai_write.js sets one) would keep the light values. Declaring the aliases on every `[data-bs-theme]` element resolves them again there.
- A rule that sets a Bootstrap variable on a subtree (`.bg-software-navbar .border-end { --bs-border-color }`) does not reach the tokens: set the `--pg-*` token there instead.
- `--bs-focus-ring-color` has no alias: Bootstrap's `.focus-ring-*` classes set it per element.
- An alias never names itself or a `--bs-*` variable the bridge fills from the same alias: that is a cycle and both turn invalid.

## Which stylesheets have the block?
- `assets/css/backend.src.css` (after the dark palette block) for every screen that prints the panel head, inline `<style>` blocks included.
- Pages that load Bootstrap without backend.src.css carry their own short block with only the names they read: `assets/css/workspace_guest.css` (guest page) and the inline styles of the prototype pages (`includes/fn/prototype.php`, `prototype_links.php`). A new name read there goes into that block too.
- Outside this layer: site CSS and `design_themes.php` (its values go into site designs), JavaScript (step B2b), and shared files (`chat.php`, `toolbar.php`, `output.php`, `seo.php`), which are converted by hand per function.
- Converted by hand: the panel chat window and `pg_chat_render_backend_launcher()` in `chat.php` (#2490), the SEO panel in `toolbar.php` (#2448), the page bar CSS of `pg_page_action_bar()` in `output.php`, printed only in `toolbar.php` (#2505). Their v5 reads had fallen back to light or inherited colours.

## What has been converted?
- B3a (#1694): the token block, every v5 `-rgb` use in panel CSS and style blocks (130 to 21), the accent role variables.
- B3b (#1711): the neutral variables in backend.src.css (1,055 `var(--bs-*)` to 80, 38 of them in the token layer).
- B3c (#1723): the other panel stylesheets and the panel files' inline styles.
- B7b (#1841): `style_designer.css` and `page_designer.css`.
- Left on `--bs-*` on purpose: component-scoped variables (`--bs-card-*`, `--bs-modal-*`, `--bs-gutter-x`, opacities, `--bs-dropdown-border-radius`), the form validation colours, the focus ring and the guest page's `--bs-dark`: the switch converts them with their components.
