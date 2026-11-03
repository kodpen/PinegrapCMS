---
name: page-experiments
description: Plan (owner 2026-11-02): A/B experiments on designed pages (a draft as the variant, a sticky bucket, goals in a rollup, the winner published) and audience conditions; steps PX1-PX8
---

## What is wrong now?
- A designed page has one tree for everyone. The designer has drafts (`designer/page-drafts.md`), revisions, a prototype preview and element conditions on data values (`designer/widget-condition-panel.md`, `includes/fn/widgets_conditions.php`), but no way to show half the visitors another headline and learn which converts, nor to show a returning visitor, a country or a member something else. Every compared builder sells both (Webflow Optimize, Framer variants, Wix test sites).
- The figures to judge a variant exist per visit (`visitors.custom_form_submitted`, `order_completed`) but nothing ties a visit to a variant, and the page cache (`includes/fn/page_cache.php`) keeps one copy per address.

## What is proposed?
- A variant is a draft page: the operator makes B as a copy in the Drafts folder, edits it with every designer tool, and an experiment row names the live page, its variant drafts with weights, the goal and the dates. The visitor's first request on the page draws a bucket (weighted, random, kept in one functional cookie `pg_exp`, listed in `pg_consent_catalogue()`); the router renders the variant's tree under the live address for that bucket, crawlers and signed-in staff always get the live page (no cloaking: one address, the control for bots).
- The page cache keeps one copy per variant (the bucket joins the cache key); views and goals are counted into an hourly rollup `experiment_hourly` (never raw rows); goals: a form sent on the page, an order completed, a click on a marked element, another page reached.
- A results screen: views, conversions, rate per variant, lift, and whether the difference is more than chance (a two-proportion test with a plain sentence); "Publish the winner" saves the variant's tree into the live page through `pg_designer_save_page()` (a revision, as any save) and ends the experiment.
- Audience conditions: the condition panel's tokens grow by `{audience:...}` values (returning, country, device, UTM, signed in, member, contact group, experiment variant), evaluated where value conditions are today; a page with one is cached per country and device or, for the personal ones, served without the cache.

## Which decisions bind it?
- No second designer or copy of the tree: a variant is a draft and uses the designer, its lock, its revisions and the Preview as they are; a draft chosen as a variant cannot be published or binned while the experiment runs.
- The live page's title, meta tags, structured data and address are the ones served for every variant (search engines see one page); a variant changes the body only.
- Counting follows `visitors/high-volume-counter-rules.md`; nothing is written per visitor beyond the cookie; a bucket cookie needs no consent (strictly functional) but is listed.
- Express-order totals and the checkout flow are untouched; the order goal reads `pg_order_complete()`'s context only.

## Which questions are decided? (#5038)
1. `experiments`, `experiment_variants`, `experiment_hourly` as written; shape in the issue.
2. The `pg_exp` cookie lives 90 days; the page cache keeps one copy per variant and decides as today.

## Where does it start?
- PX1 alone, then PX2, PX4 and PX6 together. Read the `designer/` and `visitors/` key rules, `designer/page-drafts.md`, `page-revisions.md`, `widget-condition-panel.md`, `platform/page-cache-invalidation.md`. Steps: `page-experiments-steps.md`, `-steps-2.md`.
