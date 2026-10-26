---
name: bootstrap-6-codemod
description: tools/bs6_migrate.php and tools/check_bs6.php for the panel's Bootstrap 6 move: contexts read, what is rewritten or only reported, sizes converted once per line, file scope, the first full run
---

## What do the tools do?
- `tools/bs6_rules.php` holds the rules (tests: `tests/bs6_migrate_test.php`). `php tools/bs6_migrate.php <paths>|--all|--diff=<ref>` reports what it would rewrite; `--write` writes, and refuses a file whose result fails `php -l` or `node --check`. `--switch` makes the switch's scripted hand fixes (`tools/bs6_switch.php`, tests `tests/bs6_switch_test.php`; list in `plans/bootstrap-6-switch.md`). `php tools/check_bs6.php` lists what is still Bootstrap 5 in panel files; `--strict` exits 1 (CI from the switch, step B8).
- Contexts read: `class="…"` attributes (escaped quotes too, not `$class = …`), PHP array keys ending in class or classes (`'class' => 'form-select'`), JS `class:`/`className:` keys, `.className =`, jQuery `addClass`/`removeClass`/`toggleClass`/`hasClass`, `classList` methods. Code inside a list (`' . $x . '`, `${x}`, ternaries) is never edited; a v5 class quoted in it is reported with its target.

## What is rewritten, what only reported?
- Rewritten (the migration skill's step 2): breakpoint infixes to prefixes (`col-md-6` to `md:col-6`, `xxl` to `2xl`), text colours to `fg-*`, `btn-<c>`/`btn-outline-<c>` to `btn-solid`/`btn-outline` plus `theme-<c>`, `alert-<c>` and badge colours to `theme-<c>`, `text-bg-<c>` to `bg-<c> fg-contrast-<c>`, `form-select` to `form-control`, subtle colours, link helpers, `clearfix`, `lh-base`, `mh-`/`mw-`; `has-validation` is dropped.
- Sizes keep their look: spacer keys 2/3/4/5 to 3/5/7/12 (gutters and gaps too), `fs-1`..`fs-6` to `fs-4xl`..`fs-md`, radius 1/2/3/4 to 3/4/5/9, plain `rounded` to `rounded-4`.
- Reported: markup that changes shape (modal, offcanvas, dropdown, form-check and switch, btn-check, accordion, form-range), light/dark variants, classes 6.0.0-alpha.1 lacks (`text-reset`, `display-*`, `lead`, `d-print-*`, `bg-body-*`, block negative margins, `rounded-5`), classes built in code (`'btn-' . $variant`), and a class API call whose class would split (`classList.toggle('btn-warning')`: classList takes one class, and removing half of a pair breaks other variants).
- A test checks every target class against `assets/lib/bootstrap-6/css/bootstrap.css`.

## Why is a line converted only once?
- `p-3` exists in both versions with another size: converting a converted line grows it again (`p-5` to `p-12`). `--all` runs once on switch day; a branch older than the switch converts its own added lines with `--diff=origin/development`.

## Which files does it touch?
- `bs6_scope_kind()`: only `panel` files. `site` (site renderers, `frontend.src.js`, widget output, design templates, site page templates, cron jobs) stays on 5.3.8. `shared` files (`output.php`, `liveform.class.php`, `forms.php`, `ecommerce.php`, `content.php`, `auth.php`, `calendar.php`, `seo.php`, …) are converted by hand per function; of the `editor` files only `style_designer.js` is converted, outside its `/* @pg-site-output */` code (`bootstrap-6-editor-markers.md`). `--force` overrides only on purpose.

## What did the first full run find (2026-10-25)?
- 371 panel files and 12,657 classes rewritten, every result parsing. Reported in panel files: about 2,600 checkbox/switch classes, 510 modal, 310 dropdown, 85 offcanvas, 260 link colour helpers, 110 light/dark variants, 220 classes inside code, 240 CSS selectors on renamed classes, 130 `--bs-*-rgb` uses, 97 Bootstrap JS calls, 85 events of renamed components.
