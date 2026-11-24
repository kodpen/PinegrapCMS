---
name: designer-publish-findings
description: The Publish check's finding shape (pg_publish_findings.js): what the four checks become, one concept kept once, the blocking table, a failed check, a page's summary
---

## Where does it live, and what does it read?
- `assets/js/pg_publish_findings.js`, `PgPublishFindings`: pure, no DOM, no request; not loaded by any screen yet (the Publish sheet's results load it on first use). The checks run where they always ran and hand their answers in.
- `read(sources, texts)`: `audit` (`validateTree()`'s issues), `seo` (designer/seo_check's answer: failed meta `checks`, `findings`, `a11y` rows, `concepts`), `links` (`PgProtoHotspots.canvasMarks()` of the page), `unreached` (no link leads to the page), `leftovers` (`{ node, shared, field, label }`: values a place keeps for a closed component field). One reader each: `fromAudit`, `fromSeo`, `fromA11y`, `fromLinks`, `fromLeftovers`.

## What is a finding?
- `{ id, source, code, group, level, text, node, shared, where, count, detail, seo, concept }`. `group`: `links`, `a11y`, `seo`, `weight`, `content` (`groups()`), the sheet's five groups in order. `level`: `block`, `look` or `failed`.
- `id` = `source:code:node:index` (index: the nth link of a text), stable for the same finding on the same element, so a choice about it (Ignore) can be kept.
- `text`: the check's own label; for the codes no check words (`link_broken`, `link_empty`, `page_unreached`, `field_leftover`, the `*_failed` ones) the sheet hands its translated sentences as `texts` (code => sentence), else an English fallback.
- A warned SEO meta check (few keywords, a long title) is no finding: only a failed one is.

## Which group does an audit rule go to?
- Accessibility by default; `link_blank_noopener` and `form_no_action` Links; `h1_missing`, `h1_multiple`, `heading_starts_at_h2`, `img_alt_filename`, `presentational_tag` Search engines; `cf_field_unnamed`, `mail_block` Content (`AUDIT_GROUP`).

## What blocks?
- `blocking()`: `links:link_broken` (a canvas link marked broken: kept by a page that is gone, or no page at its address), `audit:form_no_action`, `seo:title_present` (failed), `seo:title_tag_missing`, `a11y:a11y_page_title`, `component:field_leftover`. Short and fixed; nothing else blocks. A link without a target and a page no link leads to are to look at.

## How is one concept found twice kept once?
- By the audit rule registry rows (`concepts`): an SEO finding or accessibility row whose concept the canvas audit found (its editor rules), or whose SEO code an audit issue names, is dropped: the canvas finding names the element. Else the accessibility row is kept over the SEO finding; kept, it blocks when any copy blocks.

## What does a failed check give?
- `audit: false`, `links: false`, `leftovers: false`, or an SEO answer that is `false` or not `status: 'success'`: one `failed` finding in its group (the SEO check gives one in Accessibility and one in Search engines, its message as `detail`), which blocks nothing. A source left out (null) was not asked for and gives none; `a11y: null` (a page not saved yet) gives none.
- `summary(found)`: `state` `block`, `look` or `ready` ("n blocking", "n to look at", "Ready"), the counts, and a count per group; failed ones are counted apart and change no state. Sorted by `read()`: blocking first, then the groups' order.

Test: `tests/designer_publish_findings_test.php`.
