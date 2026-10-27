---
name: bootstrap-6-codemod
description: tools/bs6_migrate.php, the switch fixes and tools/check_bs6.php: contexts read, what is rewritten or reported, sizes converted once, scope, how the switch keeps the v5 look
---

## What do the tools do?
- Rules: `tools/bs6_rules.php` (tests `tests/bs6_migrate_test.php`). `php tools/bs6_migrate.php <paths>|--all|--diff=<ref>` reports, `--write` writes (refused on a `php -l`/`node --check` failure); `--switch` runs the switch's fixes (`bs6_switch*.php`, text fixes in `bs6_switch_texts.txt`). `php tools/check_bs6.php` lists what is still v5 (on a switched tree a class the v6 dist defines is not counted); `--strict` exits 1.
- Contexts: `class="…"`, PHP keys ending in class(es), JS `class:`/`className`, jQuery class methods, `classList`, the panel scripts' helpers (`el('div', 'mb-3')`, `make`, `button('btn …')`, `icon('bi-x', 'me-2')`), string literals holding only classes (not in `style_designer.js`; not after `id=`, `name=`… or in `getElementById()`). A lone `text-bg-<c>` string becomes `theme-<c>`.

## What is rewritten, what reported?
- Rewritten: breakpoint prefixes, `fg-*`, `btn-solid`/`btn-outline` + `theme-*`, badge and alert themes, `form-select` to `form-control`, subtle colours, light/dark and `body-*` to the nearest neutral (`bg-light`/`bg-body-tertiary` `bg-1`, `text-reset` `fg-reset`), sizes (spacers 2/3/4/5 to 3/5/7/12, `fs-*`, radius). No v6 class: `pg-fg-emphasis`, `pg-display-N`, `pg-lead`, `pg-mt-n2` (block negative margins), `pg-table-light` (shell rules draw them).
- The switch converts structure: modal to `<dialog class="dialog">` (boxes dropped, sizes renamed, a form box gets `d-contents`, one printed open is `.pg-open-sheet` + `<dialog open>`), offcanvas to `<dialog class="drawer">`, dropdown to `.menu` (`dropdown-menu-end` to `data-bs-placement` on the toggle plus `pg-menu-end`, wrapper `pg-menu-wrap`, caret `pg-menu-caret`), data attributes, component variables, checks (also helper-built), v5 link helpers and print utilities to own `pg-link-*`/`pg-print-*`, own media queries to 1024/1280/1536.
- Reported: what needs a hand (`check_bs6.php --verbose`), a class API call whose class would split.

## Why is a line converted only once?
- `p-3` exists in both versions with another size: a second run grows it. `--all` runs once on switch day; an older branch converts its own added lines with `--diff=origin/development`.

## Which files and functions?
- `bs6_scope_kind()`: `panel` files are converted (`includes/fn/output.php` too); `site` stays on 5.3.8; `shared` files (`forms.php`, `liveform.class.php`, …) by hand. Editor: `style_designer.js` outside `/* @pg-site-output */` and `designer_screen.php`; the switch converts only the editor's own dialogs (canvas code names v5 classes).
- `bs6_site_functions()`: functions of panel files that site renderers call stay v5; `tests/bs6_site_output_test.php` finds new ones.

## How does the switch keep today's look?
- `bs6_switch_tokens.php`: a `--pg-*` token declared from a v5 variable takes the v6 one; a rule reading a v5 variable reads the token. An unlayered `:root, [data-bs-theme]` block ends backend.src.css with v6's colour, neutral and size variables from the palette and v5 (v6's root variables are unlayered: a layered override loses).
- `rgba(var(--bs-<colour>-rgb), N)` becomes `color-mix()` on `--pg-<colour>`; the panel's own triples are `--pg-*-rgb`.
- Shell rules atop the custom layer keep v5's metrics: buttons, cards, badges, menus, nav, headings, labels, checks, `input.btn-check`, breadcrumb, accordion, top-aligned dialogs (`pg-dialog-centered` for centred ones). Icon fonts sit in the vendor layer.
- Traps: a rule in `@layer custom` beats any Bootstrap component rule, whatever the specificity; v6's `.drawer` variables are unlayered, so panel CSS sets `--pg-drawer-width` (the bridge reads it).
- Classes built from a v5 stem (`'text-' . $tone`) are rewritten when the code shows every value (`bs6_switch_stems.php`); a lone class string in code, or a choice joined to one, takes the whole v6 list.
