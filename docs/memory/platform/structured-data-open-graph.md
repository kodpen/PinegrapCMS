---
name: structured-data-open-graph
description: Rules for JSON-LD and Open Graph (2026.4.4): no invented data, json_encode only, custom JSON-LD, merchant parts, OG image resolvers, SEO card key.
---

## What may structured data contain?
- Never invented data: `review` / `aggregateRating` are not written without real review data. Google `offers` alone is enough; a Rich Results Test warning is not an error. The legacy "google require it so we set random" block was removed - do not bring it back.
- JSON-LD is always an array + `json_encode()`. String concatenation is forbidden: one quoted product name invalidates the whole block and readers silently drop it.

## How is custom JSON-LD handled?
- `page.custom_jsonld` + `config.custom_jsonld`: validate with `json_decode` on save; on output decode -> encode (`pg_custom_jsonld_block()`), the `/` escape makes a `</script` break-out impossible. Never echo the text as is.
- The SEO save (`includes/settings/seo.save.php`) marks invalid site JSON-LD (`mark_error('custom_jsonld')`, dialog `status: 'invalid'`) before the IndexNow file or a column is written. Why (PR #490): `output_error` gave the dialog a 500 after the IndexNow file was already written.

## Which rules apply to OG images and merchant data?
- In the `media` field the value decides (`pg_resolve_submitted_form_og_image`): the field may link to rich text/video too; a video address does NOT go into og:image.
- Merchant parts (`pg_product_merchant_jsonld_parts`) only when `shippable = 1` and `MERCHANT_COUNTRY` is filled; both production paths (legacy block, `_pg_build_product_jsonld`) go through the same helper.
- OG image resolvers use `SELECT *`: `files.image_width/height` came in 4.4, naming the columns breaks the query on an old DB.
- Every resolved image address passes `pg_normalize_url_slashes()`. Stored content can carry double-slash addresses (`http://host//x.jpg`); IIS forgives, but emitted markup must have a single form. A new OG / JSON-LD address source must pass it too.

## Where is the setting and when is JSON-LD not emitted?
- The `strutured_data` key is in the SEO card (not in E-Ticaret - there it was invisible on a site with e-commerce off). Only settings that produce structured data (site JSON-LD, merchant) are tied to the key with `collapse-switcher`; the share image/logo that feeds OG is NOT tied.
- The page-describing JSON-LD splice (`BlogPosting`/`Organization`/breadcrumb/custom) is not produced in e-mail and edit mode; silent when `STRUTURED_DATA` is off (the constant's spelling is broken but consistent - do not fix).
