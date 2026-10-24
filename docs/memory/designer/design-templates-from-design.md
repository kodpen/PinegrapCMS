---
name: design-templates-from-design
description: Templates made from a design (2026.4.8): design_template table, custom-<n> ids, same shape as a template file, the pg_dtc_* placeholder export, assets, deleting, the same-request cache trap.
---

## Where are they kept?
- `includes/fn/design_templates_custom.php`, table `design_template` (2026.4.8; `pg_design_template_custom_ready()`), id `custom-<row id>`. Made from the designs home menu ("turn into a template": `designer/template_from_design` → `pg_design_template_from_style()`), deleted from the template dialog (`designer/template_delete`).
- `pg_design_templates()` merges the shipped files (`'builtin' => true`) and these rows (`builtin` false, listed after the files; only these can be deleted). The array has exactly the shape a template file returns, so `pg_design_template_prepare()` and `pg_design_template_install()` do not tell them apart, and the JSON can be carried to another site as it is.
- Deleting one discards the rows it left behind when opened and never published (`pg_design_template_discard()`); designs made from it keep everything.

## How are its placeholders made?
- The `pg_dtc_*` helpers are the reverse of `_pg_tpl_fill()`, without the database: a page address → `{{page:<key>}}` (alone or followed by `?` / `#`; an address that only starts the same way is left), page ids in widget and form settings and a link's `_bindParams` page of the design → `{{tab:<key>}}` (its params untouched), folders → `{{folder:<key>}}`, contact groups → `{{contact_group:<key>}}`, a value equal to the site name → `{{site_name}}`, the year after © → `{{year}}`. Exact matches only. Every placed component becomes `props.templateWidget` or `props.templateShared`.
- The design's CSS, JS, fonts, head code and body classes travel in the `'assets'` entry: `add_system_style.php` puts them into its hidden fields, install hands them to `save_system_style()`. Asset `pages` become `key:<page key>` (`design-asset-records.md`).
- A new key added to the template array is added to this export too, or templates made from designs lose it.

## What trap does the template list hold?
- `pg_design_templates()` caches its list in a `static`: a template written in the same request is not in it. Read it with `pg_design_template_custom_get('custom-<n>')`.

Related: docs/memory/designer/design-templates-file-format.md
