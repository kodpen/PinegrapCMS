---
name: sketch-builder
description: The sketch builder (pg_sketch_builder.js, PGSketchBuilder): sections to header, body and footer trees from palette starters, columns, heading, the section rhythm variable
---

## What does the builder take and give?
- `PGSketchBuilder.build(sections, opts)`: the `sections` of `PGSketchReader.read()` (`designer/sketch-reader.md`) in, `{header, footer, body}` out. The first `header` section is the header, the last `footer` section the footer; the rest is the body, from the top.
- A part: `section` (id), `kind`, `block` (palette type or null), `widget` (catalog, blog, booking or null), `unsure` (from the reader), `kept`, `node`. A widget part has `node: null`: the caller places it (`_createSystemWidgetNode()` is async).
- Pure: it copies every starter with fresh ids, so a cached starter is never changed, and leaves `sections` as they were. Loaded with the reader in the designer part `sketchbuild` (`designer/sketch-build.md`).

## What does the caller hand it?
- `opts.make(type)`: the starter tree, null falls back to a text section (`sketch-build-starters.md`). `opts.node(type, props, children)`: `createNode()`; without it plain nodes `sk_<n>`.
- `opts.words`: `{heading, paragraph, alt}`, the sample words of a section built here. The builder owns no words: SK4 gives `_sdT()` literals, as `check_lang.php` scans only `style_designer_*.js` for keys.
- `opts.kept`: section id => node, a section already edited, given back untouched ("Build again").
- `opts.site` `{name, year, path, pages: [{name, address}], toggle}` (#11699): with it the header is a new navbar (brand = site name to `path`, links = pages with a name and an address, `path` + address, at most six) and the footer the name, the links and "© year name"; no starter is asked for. Used only when the design has no header or footer of its own (`sketch-build.md`).
- `opts.page` `{title, description, name}`: an intro with no typed word takes the title (else the name) as heading and the description as its first paragraph (added after the heading when the block has none). `opts.names`: kind => name, the heading (and layer name) of any section with no typed word. A typed word always wins.

## Which block is each kind?
- `blockFor(section)`: header `navbar-light-cta` with a button, else `navbar-centered-logo`; intro `hero-centered`, with a picture `hero-image-bg`, a picture beside text (2 columns) `hero-split`; features `features-grid`, 2 columns `features-alt`; cards `services-icon-cards`, with pictures `services-image-cards`; gallery `image-grid`; form `contact-form`; cta `cta-simple`, 2 columns `cta-inline`; footer `footer-minimal`, 3 columns or more `footer-columns`.
- Text and picture are built here (section, container, heading and paragraph or a wide picture); catalog, blog, booking are widgets.

## What does it take from the sketch?
- The typed heading replaces the block's first h1-h6, HTML-escaped; a badge before it keeps its word (`sketch-build-starters.md`).
- Features, cards and gallery: the first row of two or more columns gets the sketch's column count, at most 6: extra columns go, missing ones copy the last, a copy's heading ending in its number ("Fresh 4", a trailing number replaced); lg is 12/n where it divides (2, 3, 4, 6), md 6 above two.
- Every built root carries `_sketchId` (the section's id), for "Build again" to match.

## Which variables does it wear?
- A body section with `py-5` (`sketch-build-starters.md`) gets `padding-top` and `padding-bottom: var(--pg-var-space-section, 3rem)` after its own inline style (and `_styles` rows when `_stylesInit`) (the stylesheet's token name, #11699). 3rem is the starters' `py-5`, so a design without variables draws as before. Header and footer get none.
- Colours, fonts and corners come through the starters' Bootstrap classes, which the variables' theme tokens drive (`designer/design-variables-model.md`); the section rhythm is the one variable no theme token carries.

## Where is it tested?
- `tests/sketch_builder_test.php`, in Node with a stub starter maker (skipped without Node).
