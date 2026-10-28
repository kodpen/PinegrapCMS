---
name: record-page-schema-type
description: A custom form chooses the schema.org type of its record pages (BlogPosting, Article, NewsArticle, none); where it is stored, read and set.
module: seo
---

## Which type does a record page's JSON-LD carry?
- The form's `custom_form_pages.schema_type` (2026.11.0, step 11.0.55, #2182): `BlogPosting` (default, what every record page wrote before), `Article`, `NewsArticle` or `none`. `pg_record_schema_type()` reads any other word, '' included, as BlogPosting; the list is `pg_record_schema_types()`.
- `get_page_content.php` reads it for the resolved record's form (`pg_form_page_schema_type($pg_blog_form['page_id'])`) and passes it as `type` to `pg_build_blogposting_jsonld()`; the three types share the same properties (author rules: `structured-data-open-graph.md`).
- `none` writes neither the record block nor its breadcrumb: the page's own JSON-LD (and the site's) is left as the only description. Open Graph article tags are not affected.

## Why only these four?
- Decision #2475 (option A): one column, same builder. WebApplication / SoftwareApplication need field bindings (`applicationCategory`, `offers`) and are added only if a later plan asks; `offers.price` must never be invented.

## Where is it set?
- Classic form settings: `pg_page_form_schema_type_row()` in `includes/forms/page_form.php`, under the Schema Author rows.
- Visual designer: the custom form widget's "Schema Type" row (`style_designer.js`, `data-s="schema_type"` on the page's `formSettings`), loaded and saved by `pg_cf_load_page_form_settings()` / the page form save in `includes/fn/custom_form.php`.
- Every reader and writer waits for the column (`pg_record_schema_type_setting_ready()`): files land before the upgrade runs. Test: `tests/record_schema_type_test.php`.
