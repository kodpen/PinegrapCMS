---
name: designer-publish-weight
description: The Publish check's weight measure (includes/fn/publish_weight.php): what it counts on the rendered draft, the budget and its grades, the findings it gives, how it joins the finding shape
module: publish_weight
---

## Where does it live, and what does it read?
- `includes/fn/publish_weight.php`, pure, loaded where the Publish check runs (not by `functions.php`). It reads the draft's server render (`seo-check-draft-render.md`; the audit render writes `data-pg-node`) and the sizes of the files that page loads. No screen calls it yet.
- `pg_publish_weight_urls($html)`: the stylesheets, scripts and pictures the page loads, without query, once each. `pg_publish_weight_local_files($urls, $root, $base)` gives the site's own files' bytes and a picture's pixel size; another host, a path outside `$root` or a missing file is left out.

## What does `pg_publish_weight_measure($html, $files)` count?
- `markup`: the page's bytes without its inline `<style>` and JavaScript `<script>` (JSON-LD stays markup). `styles`: inline plus each linked stylesheet once. `scripts`: inline plus each `src` once. `images`: the first screen's pictures, an `<img>` without `loading="lazy"` (none in `noscript`/`template`). `total`: the four.
- A file whose size is not given adds nothing and is listed in `unknown`.
- `oversized`: natural width above twice the `width` attribute (by the height when the width is no number); exactly twice stays; SVG has no pixel size. `unsized`: no number in `width` or `height`. `blocking`: a classic `<script src>` in the head without `async`/`defer`.

## What is the budget?
- `pg_publish_weight_budget()`, bytes: markup 100 KB, styles 150 KB, scripts 300 KB, first-screen pictures 1,000 KB, total 1,500 KB. `pg_publish_weight_grade()`: green at or under, amber up to twice, red above; `state` is the worst measure. The sheet shows these numbers.

## How are unused rules found?
- `pg_publish_weight_unused_rules($css, $used)`, `$used` the pages' `pg_publish_weight_used_names()` merged with `+`. A rule is listed when none of its selectors can match: a class or id it needs is on no page. Rules inside `@media`/`@supports`/`@layer`/`@container` are read with their condition; `@font-face`, `@keyframes`, `@import` give none.
- Kept as used: selectors with no class or id, attribute selectors and pseudo-class arguments (`:not(.x)`) not read, classes scripts add while the page runs (`show`, `active`, `fade`, `is-*`, `has-*`, `js-*` …, `pg_publish_weight_state_class()`). Listed only, never removed.

## Which findings does it give?
- `pg_publish_weight_findings($measure, $unused, $texts)` in PC1's shape (`designer-publish-findings.md`), source and group `weight`, level `look`, never blocking: `weight_<measure>` over budget (detail `bytes / budget`), `img_oversized` and `img_no_size` per picture (node = nearest `data-pg-node`, detail its address), `script_blocking` per script, one `css_unused` (count, one rule a line).
- `pg_publish_weight_failed($why, $texts)`: one `failed` finding, blocks nothing. `pg_publish_weight_texts()` gives the translated sentences; English stands in without them.

## How does it join `PgPublishFindings.read()`?
- PC1 (#11518) did not read a weight source. The findings are already in its shape, so `read()` takes `sources.weight` as that array (or `false` → the failed finding) and concatenates it before `merge()` and `sort()`; nothing of PC1 is rewritten.

## How fast is it?
- 132 KB page with 1,500 elements and 188 pictures plus a 167 KB stylesheet of 6,000 rules, PHP 8.3, 4 cores: measure, unused rules and findings 24 ms median (15 runs). The editor loads none of it: opening, drawing and editing are unchanged.

Test: `tests/publish_weight_test.php`.
