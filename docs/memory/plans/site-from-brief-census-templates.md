---
name: site-from-brief-census-templates
description: Site from a brief census: how a design template installs (pages, header and footer, theme, sample content), which of its writes a brief flow could reuse, and the one that would touch the live site
---

## How does a template install?
- `pg_design_template_install()` (`includes/fn/designer.php:9760`) after `pg_design_template_prepare()` (`designer.php:9273`). From the panel ("Use this template", `add_system_style.php:53`) only prepare runs: widget and shared rows, folders, catalog and contact groups are written at once, the pages stay unsaved tabs until the first save (`designer/design-templates-file-format.md`).
- Pages: each validated with a dry `pg_designer_save_page()` (`designer.php:9875`), then saved into the new design (`designer.php:9927`); tab references and forms resolved after (`pg_designer_resolve_tab_refs()`, `pg_cf_reconcile_page_form()`).
- Menu: no menu table; the top menu is a shared component in the template's `shared` (one row, `category = 'template:<id>/<key>'`, `_pg_tpl_shared_row()`), links by `{{page:<key>}}`, `smartActive` for the current page.
- Theme: the look and palette (`opts.look`, `opts.palette`), the template's CSS, fonts and User Styles in the design's assets (`pg_design_template_asset_sentinels()`).
- Sample content: catalog groups and products (shop only), folders, contact groups, collection forms with their records' pages; pictures copied from `includes/design_templates/<id>/` (`designer/design-templates-catalog-and-look.md`).
- Home: `opts.home` makes the `home` page the site's home (`pg_page_home_claim()`, `designer.php:9973`); the installer passes it (`includes/install_design_template.php:301`).

## Which writes could a brief flow reuse?
- `pg_designer_save_page()` (`designer.php:3721`) for each page, with `page_draft` set: a new page then goes to the Drafts folder (`designer/page-drafts.md`).
- A shared header and footer: the Sketch builder already makes them from the site's name and pages when the design has none (`opts.site`, `designer/sketch-builder.md`), up to six links.
- The theme: `pg_design_variables_from_answers()` and `pg_design_variables_start()` (`includes/fn/design_variables.php:758`, `:786`) turn a brand colour, second colour, look, type feel and room into the design's variables, as the New design window's five questions do (`designer/design-variables-questions.md`). The Canva start fills the same questions from measured colours (`designer/canva-start.md`).
- Folders: `_pg_tpl_folders()`; collections: "New collection with pages" (`designer/collection-pages-made.md`), which already makes its two pages as drafts.

## Which write must the flow not reuse?
- `pg_designer_save_page()` without `page_draft` keeps the server's default, on the site; template install sends none, so every installed page is live at once, and `opts.home` takes the home page. That suits a fresh installer site, never an existing one (the plan's rule: the live site is never changed).
- So the flow writes its pages as drafts, never claims the home page, and makes no catalog products or records (they are live data, not drafts of a design).
- Publish, the Publish sheet and its holds put drafts on the site, as for any design (`designer/page-drafts.md`).

## What would a template not give?
- A template is written by hand in a PHP file, one per business kind; design template plans are paused (`workflow/coordinator-no-plans.md`). A brief flow does not pick or write a template: it builds pages from the sketch builder's blocks.
