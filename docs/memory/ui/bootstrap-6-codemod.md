---
name: bootstrap-6-codemod
description: tools/bs6_migrate.php, the switch fixes and tools/check_bs6.php: contexts read, what is rewritten or reported, sizes converted once, scope, how the switch keeps the v5 look
---

## What do the tools do?
- Rules: `tools/bs6_rules.php` (tests `bs6_migrate_test.php`). `php tools/bs6_migrate.php <paths>|--all|--diff=<ref>` reports, `--write` writes (not on a lint failure); `--switch` runs the switch's fixes (`bs6_switch*.php`, `bs6_switch_texts.txt`). `php tools/check_bs6.php` lists what is still v5 (a class the v6 dist defines is not counted on a switched tree); `--strict` (CI, pre-push) exits 1 on a panel finding not on its reviewed list.
- Contexts: `class="…"`, PHP keys ending in class(es), JS `class:`/`className`, jQuery class methods, `classList`, the panel scripts' helpers (`el('div', 'mb-3')`, `button(…)`, `icon(…)`), strings holding only classes, own `snake_case` ones and one leading space allowed (not in `style_designer.js`, not after `id=`/`name=`). A lone `text-bg-<c>` string becomes `theme-<c>`.

## What is rewritten, what reported?
- Rewritten: breakpoints, `fg-*`, `btn-solid`/`btn-outline` + `theme-*`, badge and alert themes, `form-select` to `form-control`, subtle colours, light/dark and `body-*` to the nearest neutral (`bg-light` `bg-1`, `border-light` `border-subtle`), sizes (spacers 2/3/4/5 to 3/5/7/12, `fs-*`, radius). No v6 class: `pg-fg-emphasis`, `pg-display-N`, `pg-lead`, `pg-mt-n2` (block negative margins), `pg-table-light` (shell rules draw them; `.pg-table-light` reads panel tokens since #2566, `dark-theme-panel-css.md`).
- The switch converts structure: modal to `<dialog class="dialog">` (boxes dropped, a form box `d-contents`, one printed open `.pg-open-sheet` + `<dialog open>`), offcanvas to `<dialog class="drawer">`, dropdown to `.menu` (`dropdown-menu-end` to the toggle's `data-bs-placement` + `pg-menu-end`, `pg-menu-wrap`, `pg-menu-caret`), data attributes, component variables, checks (also helper-built), v5 link helpers and print utilities to own `pg-link-*`/`pg-print-*`, own media queries to 1024/1280/1536.
- `bs6_switch_fields.php`: a panel liveform select/checkbox/radio naming no Bootstrap class gets `form-control`, `check`/`radio`, or `no-bs` in a `.switch` (else liveform adds v5's); `check_bs6.php` reports new ones.

## Which files and functions?
- `bs6_scope_kind()`: `panel` files are converted (`includes/fn/output.php` too); `site` stays on 5.3.8; in `shared` files only `bs6_shared_panel_functions()` and the blocks of `bs6_shared_panel_blocks()` (`get_calendar()`'s `if ($user) {`), the rest is site output. Editor: `style_designer.js` outside `/* @pg-site-output */` and `designer_screen.php`; the switch converts only the editor's own dialogs.
- `bs6_site_functions()`: panel-file functions site renderers call stay v5; `bs6_site_output_test.php` finds new ones.

## How does the switch keep today's look?
- `bs6_switch_tokens.php`: a `--pg-*` token from a v5 variable takes the v6 one; a rule reading a v5 variable reads the token. An unlayered `:root, [data-bs-theme]` block ends backend.src.css with v6's colour, neutral and size variables from the palette and v5 (a layered override loses to v6's unlayered root).
- `rgba(var(--bs-<colour>-rgb), N)` becomes `color-mix()` on `--pg-<colour>`; the panel's own triples are `--pg-*-rgb`.
- Shell rules atop the custom layer keep v5's metrics: buttons, cards, badges, menus, nav, headings, labels, checks, `input.btn-check`, breadcrumb, accordion, top-aligned dialogs (`pg-dialog-centered` for centred ones), a select's arrow room in `input-group-sm/-lg`. Icon fonts sit in the vendor layer.
- Traps: `@layer custom` beats any Bootstrap component rule; v6's `.drawer` variables are unlayered, so panel CSS sets `--pg-drawer-width` and an unlayered rule at the end of backend.src.css hands it to `--bs-drawer-width`.
- A class built from a v5 stem (`'text-' . $tone`) is rewritten when the code shows every value (`bs6_switch_stems.php`); a lone class string in code takes the whole v6 list.
