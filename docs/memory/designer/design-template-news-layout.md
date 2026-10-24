---
name: design-template-news-layout
description: News template page shapes: the lead grid, most read inside the article's record view, newsletter field order, one tip call on the front page, band padding, p titles in the drawer, whole tips.
---

## Why is the lead grid one widget, with `:first-of-type`?
- `home_lead` lists the three newest Lead Story articles; CSS draws the newest large. `:first-of-type` holds whatever else is there; an editor's edit pencil is the first child inside each record (`pg_sw_add_edit_chip()`). So do the home tiles (`pn-stack`).
- On the canvas each loop row has its own `.sd-wrap`, so each counts as the first (editor only).

## How is the article page laid out?
- The record view's own row holds the article (`col-lg-8`) and the `most_read` widget in a `col-lg-4` beside it, shown with `record_found` only, so the right third is not empty from lg. On a phone most read follows the article. The row ends with the Comments h2 in a `col-lg-8`.
- The page's comments row has one `col-8` with the widget row's gutter (`4`, `g-lg-5`), so it lines up under the article. The full-width Latest news band follows.
- Headings: h1 headline, h2 Most read, h2 Comments.

## Why is most read placed inside a widget?
- A page-level column beside the record view would have to wrap the widget's own container. A widget inside a widget's tree is supported: opening the template points it at its row in a second pass (`_pg_tpl_link_widgets()`), the page draws it with `pg_expand_nested_components()`, and the DB-less preview recurses (`_pg_tpl_preview_tree()`).
- Page-level walks (`pg_sw_page_widgets()`) do not see a nested widget. On the article page nothing needs it: the feed takes the record view, and the search index finds the articles through the other lists.
- `tests/design_template_news_test.php` (`test_news_article_row`) guards the shape.

## In what order are the newsletter fields?
- The e-mail address, the consent box, the CAPTCHA, then Subscribe, on the newsletter page, in the side box and in the front page band (`$newsletter_fields`). Subscribe is the last step, so a visitor does not press it before the boxes it needs.

## How many tip calls does the front page have?
- One: the "Send us a tip" tile among the section tiles. The side column beside U.S. and World holds most read alone. The top bar and the footer link the tips page on every page.

## Why do some bands have no bottom padding?
- Neighbouring bands on the same surface share one gap: the upper one has no bottom padding (`pt-*` only). A band on another surface (the tinted columnists, the dark newsletter band) keeps both.

## Why are the drawer's titles p, not headings?
- The menu drawer's title and its "More from us" line are `p` elements: the drawer comes before the page's h1 in the source, so a heading there would start the outline above it. The dialog still takes its name from the title (`aria-labelledby`).

## Why does the desk show a whole tip?
- `staff_tips` prints each tip in full (no line clamp): an editor must read all of it to judge it. `.pn-tip` only wraps long words (`overflow-wrap:anywhere`); the value already has a `<br>` per line, so `white-space:pre-line` would double the spacing.
