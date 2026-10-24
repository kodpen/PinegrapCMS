---
name: custom-form-url-prefill
description: A designed form control filled from a named page-address parameter (props._cf.url_param, ?post=…): where it is stored, names and value rules, order of sources, option matching, what never takes it
---

## What does it do, and where is it stored?
- A control of a designed form starts with the value of `?<name>=…` in the page address, e.g. a Report form's hidden `post` field filled from `/report?post=<address>`. Form Field panel row "Fill from the address" (`sd-cf-urlparam`, owned forms only).
- Stored as `props._cf.url_param` on the field's lead: the first control with that name in document order, the one `_pg_cf_controls()` reads every `_cf` key from. The editor writes it through `_cfFieldLeadOf()`, not `_cfLeadOf()` (which gives the clicked box of a same-named group outside a `<fieldset>`). Absent = off. Never written to `form_fields`: no column, no migration.
- Symptom it addresses: a template link could prefill only through `?value_<id>`, a row id unknown until publish.

## Which names and values are accepted?
- Names: `pg_sw_url_param_name()` / editor twin `_swUrlParamName()`, the list-filter rule (`form-list-url-filters.md`): `[a-z][a-z0-9_]{0,31}`, no `utm_`/`pg_` prefix, not in `pg_sw_url_param_reserved()` (`q`, `name`, `email`, `page`, `id` …). A refused name gives nothing; the panel warns in amber.
- Values: `pg_cf_url_prefill_value()`: a string only, trimmed, valid UTF-8 without `\p{Cc}\p{Cf}\p{Zl}\p{Zp}`, cut to 1000 characters or the row's `maxlength`. A text area keeps `\n` and tabs (`\r\n` → `\n`). A value holding `^^` is refused (the renderer's token pass would cut it).

## Where in the order of sources does it come?
- `pg_cf_field_initial_value()`: liveform → `?value_<id>` → `_cf.url_param` → contact record → folder name → default. `?value_<id>` is unchanged (no cap, no control-character check).
- The address is read in `_render_system_widget_custom_form()` from `pg_sw_filter_request()`, before the bindings rename controls to row ids: never in an e-mail, the SEO pass, the editor Preview, the search indexer or the canvas. A query string bypasses the page cache.

## How are option values matched?
- `pg_cf_match_option()`: exact, then case-insensitive (`mb_strtolower`), returns the option's own spelling; an empty option never matches.
- Radios and check-box groups: against their own boxes in `pg_cf_url_prefill_values()`; no match, no value.
- Pick list: inside `pg_cf_apply_field_bindings()`, after the options are rebuilt from `form_field_options`, against `pg_cf_select_option_values()`; no match falls through to the next source.
- Turkish `I`/`ı` do not fold together: a link should carry the option value as the site has it.

## What never takes it?
- Office-use-only fields (control or row), for staff in edit mode too: a crafted link must not preset a staff-only status. The panel disables the row.
- File uploads, passwords (never written), lone check boxes (fewer than two distinct values: a consent box is never ticked from a link). Twins: `pg_cf_url_prefill_values()` and `_cfUrlParamApplies()`; the widget panel lists `?name=` only where the server honours it.
- A hidden input does take it, and it is visitor input (panel hint).

## How does a template set it?
- `array('url_param' => 'post')` in the control's `_cf` (the template's `$input`/`$select` helpers). "Template from design" keeps unknown `_cf` keys.
- Lost when the editor rebuilds a widget from rows ("Copy for this page", "existing form" source): rows do not hold it. The server still honours a key written into either form source.

## How is it tested?
- `tests/custom_form_url_prefill_test.php`: value rule, matching, skips, order, a DB-free render pass, the `_cf` record, the renderer and panel sources, and the JS twins under Node.

Related: docs/memory/designer/custom-form-render.md, docs/memory/designer/form-list-url-filters.md
