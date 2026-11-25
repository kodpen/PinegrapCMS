---
name: sketch-builder
description: The sketch builder (pg_sketch_builder.js, PGSketchBuilder): sections to header, body and footer trees from palette starters, columns, heading, the section rhythm variable
---

## What does the builder take and give?
- `PGSketchBuilder.build(sections, opts)`: the `sections` of `PGSketchReader.read()` (`designer/sketch-reader.md`) in, `{header, footer, body}` out. The first `header` section is the header, the last `footer` section the footer; the rest is the body, from the top.
- A part: `section` (id), `kind`, `block` (palette type or null), `widget` (catalog, blog, booking or null), `unsure` (from the reader), `kept`, `node`. A widget part has `node: null`: the caller places it (`_createSystemWidgetNode()` is async).
- Pure: it copies every starter with fresh ids, so a cached starter is never changed, and leaves `sections` as they were. Loaded with the reader in the designer part `sketchbuild` (`designer/sketch-build.md`).

## What does the caller hand it?
- `opts.make(type)`: the starter tree (`createFromPalette()`); null falls back to a text section. `opts.node(type, props, children)`: `createNode()`; without it plain nodes `sk_<n>`.
- `opts.words`: `{heading, paragraph, alt}`, the sample words of a section built here. The builder owns no words: SK4 gives `_sdT()` literals, as `check_lang.php` scans only `style_designer_*.js` for keys.
- `opts.kept`: section id => node, a section already edited, given back untouched ("Build again").

## Which block is each kind?
- `blockFor(section)`: header `navbar-light-cta` with a button, else `navbar-centered-logo`; intro `hero-centered`, with a picture `hero-image-bg`, a picture beside text (2 columns) `hero-split`; features `features-grid`, 2 columns `features-alt`; cards `services-icon-cards`, with pictures `services-image-cards`; gallery `image-grid`; form `contact-form`; cta `cta-simple`, 2 columns `cta-inline`; footer `footer-minimal`, 3 columns or more `footer-columns`.
- Text and picture are built here (section, container, heading and paragraph or a wide picture); catalog, blog, booking are widgets.

## What does it take from the sketch?
- The typed heading replaces the block's first heading, HTML-escaped (a heading's `text` is HTML).
- Features, cards and gallery: the first row of two or more columns gets the sketch's column count, at most 6: extra columns go, missing ones copy the last; lg is 12/n where it divides (2, 3, 4, 6), md 6 above two.
- Every built root carries `_sketchId` (the section's id), for "Build again" to match.

## Which variables does it wear?
- A body section gets `padding-top` and `padding-bottom: var(--pg-var-space_section, 3rem)` after its own inline style (and `_styles` rows when `_stylesInit`). 3rem is the starters' `py-5`, so a design without variables draws as before. Header and footer get none.
- Colours, fonts and corners come through the starters' Bootstrap classes, which the variables' theme tokens drive (`designer/design-variables-model.md`); the section rhythm is the one variable no theme token carries.

## Where is it tested?
- `tests/sketch_builder_test.php` runs reader and builder in Node with a stub starter maker (skipped without Node).
