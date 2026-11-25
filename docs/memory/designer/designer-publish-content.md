---
name: designer-publish-content
description: The Publish check's Content group (pg_publish_content.js): starters' sample words and pictures left, a link to the page itself, the audit's empty heading moved in
---

## Where does it live, and what does it read?
- `assets/js/pg_publish_content.js`, `PgPublishContent`: pure, no DOM, no request; loaded with `pg_publish_findings.js` in the `publishcheck` part on the Publish sheet's first check (`designer-publish-check-results.md`).
- `samples(starters)`: the sample values from what the palette builds, a block's tree or a component's default props, so no list of sample words is kept by hand and the words are in the site's language (`_sdT()`). Answer `{ texts, images }`, plain JSON, sorted, to keep once built.
- `check(tree, opts)`: rows `{ code, node, shared, index, where, detail, count }` of one page's tree; `opts`: `samples`, `links` (the page's link rows as `PgProtoHotspots.mark()` marks them), `page: { id, tab }`, `shared` (a shared component's id when its own tree is checked).

## What is a sample value?
- A text of three words or more equal to a starter's, compared without tags, character references, extra spaces or case. Shorter texts ("Get Started", "Contact") are labels kept on purpose as often as left, so they are never reported.
- At any length: `lorem ipsum`, an `example.com` / `.net` / `.org` address.
- A picture whose address a starter wrote (`src`, `image`, `poster`, a style's `url()`, `_attrs` included), or from `picsum.photos` or `placehold.co`, the canon sample hosts (`designer-block-sample-content.md`).
- Words are read from a node's own props, never `_`-props, and not from names that hold no visitor words: classes, styles, colours, addresses, ids, tags, editor names (`customName`), settings (`NOT_TEXT`). A bound prop (`_bindings`) is drawn per request and is no sample. A `shared_ref` is not opened.
- One row per node and kind; `detail` is the first value as the page has it, `count` how many.

## What is a link to the page itself?
- A marked row with `mark: 'tab'` and the page's tab key, or `tab` / `page` with the saved page's id. An address with `#` goes to a place on the page and is kept.
- Not reported: a link inside `nav`, `header`, `footer` or a navbar component (a site's links to every page), classic menus and regions.

## What about an empty heading?
- The canvas audit has it (`heading_empty`, `validateTree()`); it is not found a second time. `regroup(found)` gives a copy of the findings with that audit rule's group set to `content`; no other finding changes.

## How does it join the finding shape?
- `findings(rows, texts)` gives the shape of `designer-publish-findings.md`: `id` `content:<code>:<node>:<index>`, `source` `content`, `group` `content`, `level` `look`; nothing here blocks. Codes `sample_text`, `sample_image`, `link_self`; the sheet hands translated sentences as `texts`, else an English fallback.
- The sheet's result: `PgPublishContent.regroup(PgPublishFindings.read(sources, texts)).concat(PgPublishContent.read(tree, opts, texts))`; Content is the last group and none of it blocks, so the list stays in `read()`'s order. `summary()` counts it as any finding.

## What does it cost?
- On the fixture page (`designer-performance.md`, 1,539 nodes, Node 22, median of 25): `samples()` with the page itself as starter 5.7 ms, `check()` 6.9 ms with those samples, 5.6 ms without. Run once when the sheet opens, never while editing.

Test: `tests/designer_publish_content_test.php`.
