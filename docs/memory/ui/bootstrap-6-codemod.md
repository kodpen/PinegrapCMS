---
name: bootstrap-6-codemod
description: tools/bs6_migrate.php, the switch fixes and tools/check_bs6.php: contexts read, what is rewritten or reported, sizes converted once, scope, how the switch keeps the v5 look
---

## What do the tools do?
- `tools/bs6_rules.php` holds the rules (tests `tests/bs6_migrate_test.php`). `php tools/bs6_migrate.php <paths>|--all|--diff=<ref>` reports, `--write` writes (refused when `php -l`/`node --check` fails). `--switch` runs the switch's scripted fixes (`bs6_switch.php`, `bs6_switch_shell.php`, text fixes in `bs6_switch_texts.txt`). `php tools/check_bs6.php` lists what is still Bootstrap 5; on a switched tree (panel head on v6) a class the v6 dist defines is not counted; `--strict` exits 1.
- Contexts: `class="…"`, PHP keys ending in class(es), JS `class:`/`className`, jQuery class methods, `classList`, the panel scripts' helpers (`el('div', 'mb-3')`, `h.el`, `make`, `button('btn …')`, `icon('bi-x', 'me-2')`), and plain string literals holding only classes (status maps, ternaries; not in `style_designer.js`). Classes quoted in code or printed between PHP blocks are rewritten in place; a lone `text-bg-<c>` string becomes `theme-<c>` (badge maps).

## What is rewritten, what reported?
- Rewritten: breakpoint prefixes, `fg-*`, `btn-solid`/`btn-outline` + `theme-*`, badge and alert themes, `form-select` to `form-control`, subtle colours, light/dark to the nearest neutral (`text-dark` `fg-black`, `bg-light` `bg-1`, light badge `badge-subtle theme-secondary`), sizes (spacers 2/3/4/5 to 3/5/7/12, `fs-*`, radius).
- The switch converts structure: modal to `<dialog class="dialog">` (boxes dropped, sizes renamed, a form box gets `d-contents`, one printed open becomes `.pg-open-sheet` with `<dialog open>`), offcanvas to `<dialog class="drawer">`, dropdown to `.menu` (`dropdown-menu-end` to `data-bs-placement` on the toggle plus `pg-menu-end`, wrapper `pg-menu-wrap`, caret `pg-menu-caret`), data attributes, component variables, checks (also helper-built), v5 link helpers and print utilities to own `pg-link-*`/`pg-print-*`, own media queries to 1024/1280/1536.
- Reported: what needs a hand (`check_bs6.php --verbose`), and a class API call whose class would split.

## Why is a line converted only once?
- `p-3` exists in both versions with another size: a second run grows it again. `--all` runs once on switch day; a branch older than the switch converts its own added lines with `--diff=origin/development`.

## Which files and functions?
- `bs6_scope_kind()`: `panel` files are converted, `includes/fn/output.php` among them (all panel markup); `site` stays on 5.3.8; `shared` files (`forms.php`, `liveform.class.php`, …) by hand. Editor: `style_designer.js` outside `/* @pg-site-output */` and `designer_screen.php`; the switch converts only the editor's own dialogs (canvas code names v5 classes as data).
- `bs6_site_functions()`: functions of panel files that site renderers call (returns list, passkeys account section, …) stay v5; `tests/bs6_site_output_test.php` finds new ones.

## How does the switch keep today's look?
- `bs6_switch_tokens.php`: a `--pg-*` token declared from a v5 variable takes the v6 one; a rule still reading a v5 variable reads the token. An unlayered `:root, [data-bs-theme]` block at the end of backend.src.css sets v6's colour, neutral and size variables from the palette and v5: v6 declares its root variables unlayered, so a layered override loses.
- Shell rules at the top of the custom layer keep v5's metrics: buttons, cards (block body, own margins), badges, nav, headings, labels, checks, `input.btn-check`, breadcrumb divider, accordion, top-aligned dialogs (`pg-dialog-centered` for v5's centred ones). Icon font sheets sit in the vendor layer.
- Trap: a rule in `@layer custom` beats any Bootstrap component rule, whatever the specificity.
- Classes built from a v5 stem (`'text-' . $tone`) are rewritten when the code shows every value (`bs6_switch_stems.php`); a lone class string in PHP code, or in a choice joined to a class string, takes the whole v6 list.
