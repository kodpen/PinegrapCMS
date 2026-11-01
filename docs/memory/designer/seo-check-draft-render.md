---
name: seo-check-draft-render
description: The editor's SEO check renders the draft on the server (includes/fn/seo_draft.php): document mode, draft row override in get_page_content(), the save's gate, widgets as the Preview, fragment fallback
module: seo_draft
---

## What does designer/seo_check judge now?
- A saved page: the draft rendered by the server as the structure pass renders the saved page (`pg_seo_check_draft_render()` → `pg_seo_render_page()` → `pg_background_render()`), judged by `pg_seo_analyze_html()` in `document` mode with `pg_seo_check_draft_context()` → `pg_seo_document_context()`, the structure pass's own context builder. The answer carries `mode: 'document'`.
- A page not saved yet, one the user may not edit, a type the pass does not render, a tree the save would refuse, or a render that fails or returns no `<html`: the editor's `generateHTML()` markup in `fragment` mode, as before (`mode: 'fragment'`).
- Link and speed stay read from the saved row (`seo-score-live-check-parity.md`).

## Which stored findings are carried over?
- `pg_seo_check_carried()`: in fragment mode the document-only codes (`pg_seo_document_only_codes()`, from the audit rule registry) the fragment did not find, marked `stored`; in document mode none, the draft answers them itself. Before, a fixed title tag or a second H1 removed on the canvas kept reading "from the saved page" until the next save and pass.
- `h1_only_in_template` is not computed on the draft (it needs the stored layer split).

## How does the draft reach the renderer?
- `pg_seo_draft_override()` holds page_id, `page_tree_code` (`generate_style_code_from_tree()` of the gated tree, the page's style body classes), title, description, keywords, noindex/nofollow. `get_page_content()` applies it with `pg_seo_draft_apply()` (pure: only those fields, only that page, a column the row lacks stays absent) and only while `pg_seo_rendering()` is on: a visitor never reads a draft.
- The tree passes `_validate_and_clean_tree_json()`, `pg_designer_gate_tree()` (`includes/designer_access.php`, the save's permission gate, also used by `pg_designer_save_page()`) and `_sanitize_dynamic_placeholders()`: what is rendered is what Save would write, so a content-level operator's custom_php never runs.
- System widgets draw from the editor's trees and settings (`pg_designer_preview_override_rows()` into `pg_sw_preview_overrides()`, as the Preview) for a designer only; a content-level operator's draft draws the stored widgets.
- `$_SESSION` is lent empty for the render and put back. The request is still the signed-in `api.php` call, so constants set by `initialize_user()` stay; the Preview draws under the same limit.

## What does the editor send?
- `seo_check` body: as before plus, for a saved page, `tree_json` (the canvas tree) and `widgets` (`_sdPreviewWidgetPayload()` over `generateHTML()` with the widget markers on).

Test: `tests/seo_draft_test.php`.
