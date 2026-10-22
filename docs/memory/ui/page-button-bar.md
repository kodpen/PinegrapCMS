---
name: page-button-bar
description: The #button_bar strip under a screen's heading is the .pg-toolbar component: full-width nav, buttons as direct children, which wrappers are allowed, fields in it, the list filter select.
---

## What is #button_bar?
- The action strip under the heading of about a hundred screens (the stylesheet's comment still says seventy-odd). It is the toolbar component: `backend.src.css` gives `#button_bar` the `.pg-toolbar` rules by its id, so a legacy `<nav id="button_bar" class="navigation">` gets them too; new markup writes `nav#button_bar.pg-toolbar`, full width. Never put it inside a `.row` / `.col-*`, never open a second tool column beside it.
- Its legacy markup (`.btn-group`, `.btn-link`) is mapped by `assets/css/backend.src.css` ("Page action bar" and "Admin screen toolbar"): `.btn-group` gets `display: contents`, every button `flex: 0 0 auto` and the pill shape. Bootstrap's `.btn-group > .btn { flex: 1 1 auto }` had spread two actions across two thousand pixels. A bar with no button in it is hidden.

## What may sit in it?
- Buttons, as direct children of the bar. The only wrapper is the `<form>` an action needs (export, filter, search) or an inline `span`: CSS opens it onto the bar's own line (`inline-flex`). A wrapper of buttons wraps; a form with fields stays on one line.
- The right-hand cluster comes after `.pg-toolbar-grow`.
- A form with several `form-control`s: no `flex-wrap` on the form and `w-auto` on each field; otherwise every 100%-wide field takes a line of its own (`erp/erp-cashflow-report.md`).
- A list filter is a select, not a button group: `pg_filter_select($name, $options, $current, $opts)` (`includes/fn/output.php`) draws a GET form (`pg-toolbar-filter disable_shortcut`) that submits on change, carries along only the `$_GET` values named in `keep`, takes an optional icon label and has a `<noscript>` Apply button.

Related: docs/memory/ui/admin-screen-toolbar.md, docs/memory/ui/admin-screen-toolbar-rules.md
