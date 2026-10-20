---
name: ai-write
description: "Write with AI" drafts for product, page and form record fields (ai_write panel action, includes/fn/ai_write.php): targets, context, model, quota guards, who may ask, reading the answer
---

## What exists?
- Panel action `ai_write` (`includes/panel/ai_write.php`, PR #581): a draft for one field, written by Pinegrap AI from the form's current values (unsaved edits included); nothing is saved. Logic in `includes/fn/ai_write.php`, pure parts tested in `tests/ai_write_test.php`. The buttons on the screens: docs/memory/ui/ai-write-panel.md; the mark on a used draft: docs/memory/ui/ai-write-marks.md.
- Targets (`pg_ai_write_targets()`): `product_description` (paragraphs and a "- " list, returned also as HTML), `product_short` (≤100), `seo_title` (≤60; product `title`, page `page_title`), `seo_description` (≤160; `meta_description`), `record_field` (a custom form record's field: `record_id` = the form's page, `field_id`; format and length from the field, `pg_ai_write_form_spec()`). The context keys a record may send are in `pg_ai_write_record_fields()`; unknown keys are dropped, rich text is reduced to plain text, and a stored record's groups, attributes or page text fill what the form left empty. The field's own key travels as `current_text`, so the model rewrites it instead of quoting it as a fact.

## Which model, which language?
- Pinegrap AI through `pg_ai_chat()` (`includes/translate/engine_ai.php`), which carries the licence, the model and the hold after a failed call. Without it (Workspace off or `ws_ai_missing()` not empty) the person's browser writes with its own model: docs/memory/ui/ai-write-browser.md. `pg_ai_write_engine()` picks. Claude is not asked: a routine run takes 1–3 minutes and counts against a few runs a day, a form button has to answer while the person waits.
- Screens offer the buttons when `pg_ai_write_available()` names an engine: the feature on, the person may edit the record.
- Written in the pages' language (`TRANSLATION_SOURCE_LANGUAGE`, name from `pg_tr_known_languages()`), following the style note of Settings › Languages (`TRANSLATION_STYLE_NOTE`, shared with the AI translation engines); the site name (`ORGANIZATION_NAME`) goes in as data. Temperature 0.7 so "Try again" gives another text.
- The prompt forbids invented specifications, prices, discounts, delivery times and guarantees, and treats the record's data as data, not instructions.

## What protects the licence's quota?
- Per person 10 drafts a minute (`waf_rate`, scope `ai_write`). Per site `PG_AI_WRITE_DAILY_CAP` in data/config.php, default 200, 0 switches the feature off (the browser's way too, which only the per-person limit counts); counter `data/temp/ai_write_usage.json` under flock. A counter that cannot be opened lets the call through, and a Cleanup of data/temp starts the day's count again.
- The session is closed before the model call, so the person's other screens do not wait.

## Who may ask?
- Roles 0–2; role 3 for products with `manage_ecommerce`, for a saved page only when `pg_designer_page_access()` says `edit`, for form records with `manage_forms` and edit rights to the form page's folder (the record screens' manager rule). The action is exempt from api.php's general gate and pinned in `tests/panel_actions_test.php`.

## How is the answer read?
- `{"text": …}` is asked for; think blocks, code fences, another key name and a raw line break inside the string are tolerated, otherwise the plain answer is taken (`pg_ai_write_answer()`).
- `pg_ai_write_tidy()` turns markup and Markdown into plain text, drops a "Title:" label and quotes around a line, keeps "<10 cm" (no `strip_tags()`), joins list items a blank line apart, and cuts at a word boundary.
- The description's HTML is built here with `h()` on every word (`pg_ai_write_html()`): nothing the model writes becomes markup in the panel.
- Each draft writes one activity log line ("asked Pinegrap AI for a draft of …").
