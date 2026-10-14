---
name: design-template-install-at-setup
description: Install wizard step "Site Tasarımı": two-request template install, StyleDesigner.starterTree() API, home/staff keys, starter removal and preview lists.
---

## How does the template step of the install wizard work?
- Step 3 "Site Tasarımı": the starter site as-is, or a template from `pg_design_templates()`. Choosing a template removes the starter site's pages, page styles and records bound only to them; the template is published as the new design and its home page is the site's home page.

## Why are there two requests?
- `pg_design_template_install()` does NOT run in the install request: it relies on constants `init.php` defines from config.
- Install leaves a note (`data/temp/install_design_template.json`, hash of the key); the wizard fires a second request `install/index.php?install_action=design_template`. At the very top of the file this is handed to `includes/install_design_template.php`, where `init.php` loads (`NON_ROOT_INDEX`, `SOFTWARE_LANGUAGE` = initial language).
- Do not add install code in front of that branch.

## What must be remembered about starter widgets?
- Layouts of `'tree' => 'starter'` widgets exist only in JS: the wizard loads `style_designer.js` and calls `StyleDesigner.starterTree()`. This is public API: never removed, signature unchanged.
- Adding a new starter widget to a template needs no extra install work (`pg_design_template_starter_kinds()` reads it itself).

## Which keys and lists must stay in sync?
- Template home page = the page with `key => 'home'` (else the first page); admin landing page = the `key => 'staff'` page. Changing these keys in a template: account for both.
- A new page-bound table added to starter data must also go into the `pg_idt_remove_starter()` list, or a templated install leaves that table's rows orphaned.
- The live preview in the design step renders without a database (`pg_design_template_preview_html()`), only from the trees in the template file. If a DB-needing node is added to a template, the preview page can hang the request: update `pg_design_template_preview_pages()` to exclude that page.
- The preview's "E-postalar" row comes from `pg_design_template_email_pages()`: forms' `notify_page_id` / `confirm_page_id`, comments' `email_page` setting, and widget-setting keys ending in `_email_page_id`. A new email link in a template that follows one of these names shows up in the preview automatically.
