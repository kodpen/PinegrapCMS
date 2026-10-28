---
name: design-templates-file-format
description: Writing a design template file (includes/design_templates/<id>.php): version, keys, placeholders, requires, starter widgets, shared parts, folders, and what opening a template writes before Publish.
---

## What is a template file?
- `includes/design_templates/<id>.php` returns an array (`name`, `version`, `framework`, `order`, `icon`, `description`, `pages`, `widgets`, `shared`, `folders`…); every text through `lang()`. `pg_design_templates()` lists the files, then the templates made from a design (`design-templates-from-design.md`).
- Raise `version` (x.y.z) whenever the pages change: a design records the edition it started from (`style.style_template_version`).
- A structural node carries its role in `_label` (shown beside the tag in the layer tree), not in `customName`, which hides that label in the Overview.

## Which placeholders does `_pg_tpl_fill()` replace?
- `{{page:<key>}}` the page's address; `{{tab:<key>}}` a page named in a widget or form setting, or a link's `_bindParams.href.page_id` ([[record-value-page-links]]) (`tab:<tab key>` until Publish turns it into the page id); `{{folder:<key>}}` a folder id (a value that is only the placeholder becomes a number, e.g. `_cf.upload_folder_id`); `{{site_name}}`; `{{site_email}}` (`EMAIL_ADDRESS`); `{{year}}`. Catalog and contact group placeholders: `design-templates-catalog-and-look.md`.
- A page places a widget with `props.templateWidget` and a shared part with `props.templateShared`; `_pg_tpl_link_widgets()` turns both into `shared_ref` nodes.

## What do the other keys do?
- `'requires' => 'ecommerce'` on a page or widget leaves it out on a site without the shop; on the template itself it hides the whole template.
- `'tree' => 'starter'`: the widget opens with the editor's starter layout (`StyleDesigner.starterTree()`); its row first holds a placeholder (`shared-component-editor-cache.md`). Any kind the editor knows, `comments` too (give it `'featured' => 'filter'` for the fixed block's behaviour: [[comments-widget-conversion]]). A starter widget keeps its page out of the installer preview, so the forum ships explicit trees ([[design-template-forum-replies]]).
- A page's `'comments'` array turns comments on (`pg_design_template_page_comments()`): label, allow_new (on unless given), rating, auto_publish, show_date, login, email_page (`{{tab:<key>}}`), email_subject, notify_email (`{{site_email}}`), attachments, select_name, message, closed_message. A design's export writes the same keys (`pg_dtc_page_comments()`); watchers managed by the sender is not one.
- A page's `'form'` may set `'schema_author'` (`contact`, `organization`, `field:<name>`): the BlogPosting author, written once into the form Publish makes (`pg_cf_template_schema_author()`); news and magazine use `field:byline`. `'schema_type'`: [[record-page-schema-type]].
- `'shared'`: a part several pages repeat (top menu, footer, call-to-action band), one row each so an edit is made once. Opening writes it with `_pg_tpl_shared_row()`, marked `category = 'template:<template>/<key>'` (a plain shared component has no `system_region_config` to carry the origin).
- In a menu `smartActive` marks the current page's link at request time; never write a page-specific `active`.
- `'folders'`: made or found on opening (`_pg_tpl_folders()`); an empty top folder of the same name from an earlier opening is reused, one with content gets a numbered sibling.
- Widgets and shared parts may sit inside each other's trees: prepare also opens nested shared parts, and once every row exists a second pass runs their trees through `_pg_tpl_link_widgets()` and rewrites the rows that changed.

## What exists before Publish?
- Opening (`pg_design_template_prepare()`) writes the widget and shared rows, folders, catalog and contact groups at once, because the canvas draws from rows; the pages stay unsaved tabs until Publish. Leaving without publishing discards the rows: `design-templates-rows.md`. Pages every template carries: `design-templates-standard-pages.md`.
