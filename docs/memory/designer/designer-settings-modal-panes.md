---
name: designer-settings-modal-panes
description: Designer settings modal panes per scope, one row per setting with a sentence (pg_designer_setting()), live address/access/search lines, head code field, content-level view.
---

## Which panes are there?
- Shared by all pages (the style): `tab-style` General (name, pages, framework, template, social networking, collection), `tab-theme` Theme (look, palette, theme file), `tab-head` Head & meta (head code `style_head`, the design's meta tags).
- Only this page: `tab-page` Page (name and address, folder and who may open it, draft, home, site search, keywords), `tab-seo` Search Engines (title, description, search result preview, site map, noindex, nofollow), `tab-page-meta` Meta Tags (only where the columns exist).
- Each pane and pill carries `data-sd-settings-scope="design|page"`. Code about "the page settings" takes every `page` pane, not `#tab-page` alone (`_collabLockPageSettings()` makes each inert with its own note). Open a pane with `_sdOpenSettingsPane(pane, rowId)`; the open page's name shows in every page pane and under the rail caption (`.sd-settings-page-name`, `_sdSettingsPageLabel()`).

## How is a setting written?
- One row: `pg_designer_setting($name, $help, $control, $opts)` (`designer_screen.php`): the name, a sentence on what it does on the site, the control beside it from 620px of PANE width (`@container` on `.sd-settings-body`), under it below that. `switch` keeps a switch on the right at every width, `wide` puts text boxes and previews under the text. `_sdSettingsDescribe()` points each control's `aria-describedby` at its sentence.
- Write the sentence from the code, not from the label: several pages may be home (the root picks one at random), a keyword found exactly is featured and a part of one still finds the page, nofollow only while noindex.
- A row hidden with `style.display` comes back with `''`, never `'block'`: the row is a grid.

## What are the live lines?
- Under the page name the address it makes (`sdDesign.site.url` = `URL_SCHEME . HOSTNAME_SETTING . PATH`, a link once the page is saved under that name) and the home page and draft notes; under the folder who may open it (`data-access` on each option, from `get_access_control_type()`). The search result preview takes the page's title and description or the site's (`TITLE`, `META_DESCRIPTION`, as `get_page_content.php` does) and dims while noindex is on. `_sdSettingsRefresh()` runs from `_pgLoadPageIntoFields()`, on modal show and on their inputs.

## What about the head code?
- `style_head` was a hidden field only, yet every page of a system style prints it into its head (`get_page_content.php`); the canvas and the preview do not. It is a textarea that becomes a code field the first time its pane opens (`PgCodeEditor.attach`); the syntax check waits for text, because the parser marks an empty document as an error.

## What does a content-level operator see?
- Only the page panes: PHP adds `d-none` to the design's and opens `tab-page`, `_sdApplyContentChrome()` does the same. Name, folder and home show disabled with "Only a designer changes it": the save keeps the stored ones (`pg_designer_save_page()`). A rename from the tab is still possible on screen and the save undoes it (open point).

Related: docs/memory/designer/designer-settings-modal-sizing.md
