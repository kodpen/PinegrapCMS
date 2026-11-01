---
name: designer-regions-api-missing-fields
description: api.php get_designer_regions reads missing content and search fields as not sent, so the LiveSite migration's request gets clean JSON (#4722)
---

## What does get_designer_regions read?
- `pg_panel_get_designer_regions()` in `includes/panel/designer.php`: `content` (any truthy value adds `cregion_content`) and `search` (a name filter). Both are optional; a missing field, or a `search` that is not text, means "not sent".

## Why does it matter?
- `migration.php` (LiveSite migration) sends only `action` and `content`, never `search`, and decodes the whole body as JSON. Before #4760 the missing `search` printed `Undefined array key "search"` before the JSON on a source site that shows errors, the answer could not be decoded and the migration stopped at Designer Regions (#4722). The sibling `get_styles` had the same fix in #4356 (#3961).
- Same rule as the public storefront actions: a missing field never warns (`core/json-endpoint-rules.md`).

Test: `tests/api_designer_regions_missing_fields_test.php`.
