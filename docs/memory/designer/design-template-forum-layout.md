---
name: design-template-forum-layout
description: Forum template look: pf- User Styles, Figtree and JetBrains Mono, tint after card, count strips, vanishing bands, raised badge and byline links, focus rings, light e-mail frames.
---

## How does the look ship?
- One User Styles entry of `pf-` rules (`pg_design_template_user_styles(array(), $forum_css)`): no block rule is shipped or used. Tokens only; the accent `--pf-accent:var(--pg-primary-fg,var(--bs-link-color))`; `color-mix()` only under `@supports`, after a plain `--bs-tertiary-bg`; no `!important`, `:first-child`, `:last-child` or combinator (`test_forum_css`).
- Fonts from the font picker: Figtree 400-700 for text and headings, JetBrains Mono 400/600 for `code`, `kbd`, `pre` and `samp`.
- `.pf-tint` comes after `.pf-card`: in the other order every `pf-card pf-tint` box lost its tint.

## Which shapes are worth knowing?
- A topic row is an article whose title link stretches over the row. The board badge (`.pf-badge-board`) and the byline (`.pf-person`) are `position:relative;z-index:2`, so they open the board and the member's topics. The counts print the number under its label, read label first.
- A row with the list's pencil (`pg-edit-host`) pads its start, so the words clear the chip. No row's first element is a `.row` (the chip goes into it).
- Count strips: a list whose loop area is empty, in a `has_records` box, prints `^^__result_count^^` and no empty sentence. There are 24: home 3, board tiles 6, section tiles 6, forum, board, docs, dashboard 6. Board and section tiles each place their own (`board_count_<key>`, `doc_count_<key>`, page '').
- The announcements and featured guides bands are `.pf-vanish`: `:has(.pg-sw-empty){display:none}`; an older browser shows the empty sentence.
- What members write sits in `.pf-post` (`position:relative;contain:paint`, tables scroll, code in the mono font); a guide keeps a 42rem `.pf-measure`.
- The topic page: the topic and its replies (`.pf-replies`) in the main column, and a sticky aside (`.pf-sticky` from lg) with the facts, Report a post and the guidelines. A printed topic leaves out the replies and the aside.

## How is it accessible?
- Ink outlines for focus: `body .btn`, fields, check boxes, page links and close buttons, `.pf-navlink` (inset), `.nav-underline .nav-link`, `.pf-tag`, and the masthead's bare menu button (`.pf-masthead .btn`).
- One h1 per page, no skipped level, no h2 said twice: the footer columns (Topics, Guides, Community, Your account) repeat no page title, and the drawer's title is a `p`. Every aside and nav is named; every `aria-labelledby`, `for` and jump link has its target (`test_forum_landmarks_and_headings`).
- The staff directory's photos have an empty alt: the name beside them says who it is.
- E-mail frames are `data-bs-theme="light"`: inline colours are written for a light card, each `var()` with its fallback, and no CSS capitals (`pf-kicker` and `pf-label` stay out of e-mails: a Turkish i would print dotless).

## What does the installer preview show?
- 14 public pages (home, forum, board, unanswered, docs, docs_section, search and the static pages) and the 11 e-mails. The preview repeats each loop three times and prints each element's sample words (Date, 2 hours ago): never a calendar date, which would go stale on the canvas.
