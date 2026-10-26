---
name: designer-bootstrap-vocabulary
description: The Bootstrap 5 vocabulary of the designer's smart completion (assets/js/designer_vocab/bootstrap5.json): how it is generated and kept, its keys and what each entry holds
---

## What is it and where does it come from?
- `assets/js/designer_vocab/bootstrap5.json`: every class, `--bs-*` token and JavaScript component of the shipped Bootstrap 5.3.8, for the predictor (`pg_style_predict.js`). One JSON line, ~265 KB (~33 KB gzip); loaded on first focus, never with the designer page.
- Generated, never edited by hand: `php tools/build_designer_vocab.php` reads `assets/lib/bootstrap-5.3.8/css/bootstrap.min.css` and the component sources in `js/bootstrap.bundle.min.js.map` (`sourcesContent`). Pure readers in `includes/fn/designer_vocab.php` (`pg_designer_vocab_*`, not in `functions.php`).
- `tests/designer_vocab_test.php` rebuilds it and compares bytes; a new Bootstrap copy means a rebuild (and the path in the tool and test).

## What are the top-level keys?
- `version` (`"5.3.8"`), `breakpoints` (`{"sm":"576px",…,"xxl":"1400px"}`, from the `.col-<bp>` media queries), `classes`, `tokens`, `js`, `fixed`.
- Absent keys mean "none"; lists are sorted.

## What does a `classes` entry hold?
- Key: the class name (2,025, sorted). Value `{}` or any of:
- `p`: `{property: value}` of the rules whose whole selector is the class alone (`p-3` → `{"padding":"1rem!important"}`); top-level rules win over `@media` ones. Component classes list their `--bs-*` tokens here too.
- `r`: `[breakpoint, name without it]` for a responsive variant (`d-md-flex` → `["md","d-flex"]`, `col-lg-6` → `["lg","col-6"]`). Only when the name exists for all five breakpoints, so `btn-sm` and `modal-lg` (size modifiers) have none. The second item may not be a class itself (`modal-fullscreen-down`). The dedupe key for step 2 is that pair plus the `p` properties.
- `ctx`: classes an ancestor or earlier sibling carries in a rule that styles this one (`nav-link` → `nav-tabs`, `navbar-nav`…; `form-control` → `was-validated`). Classes inside `:not()`/`:has()` are not counted.
- `with`: classes set on the same element in such a rule (states and modifiers: `active`, `show`, `is-valid`).
- A class only ever named as context or state (`was-validated`) is listed too.

## What does a `tokens` entry hold?
- Key: the custom property (`--bs-primary`). `v`: light value (`:root` or the first class rule that sets it); `d`: value under `[data-bs-theme=dark]`; `on`: classes whose own rule sets it (`--bs-btn-bg` → `btn`, `btn-primary`…). Root tokens have no `on`.

## What does a `js` entry hold?
- Key: the data name (`modal`); the twelve components alert … tooltip. `class` (`Modal`, on `window.bootstrap`), `methods` (public, inherited included), `static` (`getInstance`, `getOrCreateInstance`; dropdown adds two), `events` (short: `show` is `show.bs.modal`; only those it triggers), `options` (`{name: [type, default as written]}`, `'top'` keeps its quotes), `attrs` (`{"data-bs-toggle":["modal"], "data-bs-target":[]…}`: values where the source fixes them, every option as `data-bs-<kebab>`).
- Popover inherits Tooltip's methods, events, options and attributes. Attributes Bootstrap writes itself (`data-bs-original-title`) are left out. `data-bs-toggle="tooltip"`/`"popover"` is a docs convention, not in the source, so not listed.

## What is `fixed`?
- `{class: replacement}` for colours that ignore the colour mode or that 5.3 deprecated: `bg-white`→`bg-body`, `bg-light`→`bg-body-tertiary`, `text-dark`/`text-black`→`text-body-emphasis`, `text-muted`/`text-black-50`→`text-body-secondary`. Chosen in `pg_designer_vocab_fixed()`, not read from the CSS; the predictor ranks them last with the replacement (plan default the owner may change).
