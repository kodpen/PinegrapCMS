---
name: email-designs
description: E-mail designs (MA5c #9465): the private folder, props.pgEmail, the 600 px canvas and Phone view, mail blocks as presentation tables, their inline look map and canvas twin
module: email_designs
---

## What is an e-mail design?
- A page of a visual design whose root carries `props.pgEmail` (`pg_email_design_tree_is()`, `includes/fn/email_designs.php`). It is made with "New e-mail design" on New campaign (role <= 1) or `add_system_style.php?start=email`: one unsaved tab with the starter tree (frame, heading, text, button), the folder set, no search, no sitemap, noindex, nofollow, never a draft.
- Its page and tab name is the words "E-mail design" as an address with the first free number (`pg_email_design_free_page_name()`), never the editor's "New Page" (architect, channel 2941). The editor: `email-designs-editor.md`.
- Its folder: the private "E-mail designs" folder, made on first use; the id is `config_text` `email_designs_folder_id` (`pg_email_designs_folder_id($create)`). A folder deleted or in the bin is replaced by a new one.

## What is a mail block?
- A semantic node with `props._mail` naming its kind: frame, cell, group, heading, text, button_row, button, picture, divider, space, columns, column, header, logo, product, price, footer, footer_text, footer_link. The tree keeps classes and props only.
- The palette's parts and what each holds: `email-designs-parts.md`.
- The frame (`table.pg-mail-frame`, 600, centred) and Two columns (`td.pg-mail-col`, `width="50%"` as an attribute, not inline, so a phone rule overrides it without `!important`, which family CSS may not use) are `role="presentation"` tables with width attributes, as Outlook's Word engine needs; the mail pass's head style makes them fit a phone. The pass itself writes no tables.
- The family's own CSS has the same phone rules, so the canvas's Phone view stacks the columns; the starter tree built in PHP carries no block marker, so its frame gets that canvas rule only once a Frame block is on the page.
- Its inline look comes from `pg_mail_block_styles()` when drawn: `pg_mail_block_inline()` puts it in front of the node's own style in `_render_tree_node()`'s semantic case, so a style set in the designer wins. The canvas twin is `_SD_MAIL_STYLES` / `_sdMailInline()` (canvas, table cells and the editor's HTML); `tests/email_designs_test.php` compares the two maps line by line.
- Colours are `var(--bs-…,#hex)`: the canvas follows the design's palette, the mail pass writes that palette's colour (`pg_email_palette_vars()`, the light values of `_pg_theme_role()` for primary and secondary, hex only) or else the hex for plain Bootstrap (MA5d2 #9542). The button's text is `var(--pg-on-primary,#ffffff)`, dark on a pale colour. Fonts are Arial, Helvetica, sans-serif.

## What does a campaign store from it?
- `pg_email_campaign_design_body()` (`includes/fn/email_campaign_create.php`): the page's e-mail render; for an e-mail design then `pg_mail_pass()` in the palette of the page's design (`pg_email_page_palette()`), and the fixed footer only when the design has no block marked `data-pg-mail-footer`; with one, `pg_mail_footer_fill()` fills that block (`email-designs-parts.md`). Any other page is stored as before (no pass).

Test: `tests/email_designs_test.php`.

Related: docs/memory/designer/campaign-email-body-way.md, docs/memory/designer/campaign-email-body-mail-pass.md, docs/memory/ui/campaign-content-step.md
