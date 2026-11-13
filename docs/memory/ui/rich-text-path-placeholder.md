---
name: rich-text-path-placeholder
description: How rich text is stored with the {path} placeholder (prepare_rich_text_editor_content_for_input()): which addresses fold, why a //host address (Media Embed's YouTube player) keeps its two slashes
module: editor
---

## What does the input step fold?
- `prepare_rich_text_editor_content_for_input($content, $path = PATH)` (`includes/fn/editor.php`) writes `{path}` for the site path at the start of an `href="`, `src="` or `value="` address (also `data-src="` and the like, as before). Every rich text save goes through it: page regions, products and product groups, ads, common regions, form fields, calendar events, the external API.
- The output step (`prepare_rich_text_editor_content_for_output()`, `get_page_content.php`) puts `PATH` back; the external API (`api_form_value_out()`, `api_design_ad_html_out()`) puts the absolute site address.

## Which addresses are left alone?
- One where another slash follows the path: on a site at the web root that is a protocol-relative address on another host. The Media Embed button writes YouTube and Vimeo players as `src="//www.youtube.com/embed/…"`, and links can be typed as `//example.com/…`.
- Symptom before (#9131): the iframe was stored as `src="{path}/www.youtube.com/embed/…"`. The site rendered it right (`/` + `/www…`), but the API gave `https://<site>//www.youtube.com/…` (a 404 path on the site) and a site moved into a folder rendered `/shop//www.youtube.com/…`.
- Rows saved before the fix keep `{path}/<host>` until they are saved again: the editor opens them as `//<host>` and the next save stores that. No migration step rewrites them.

## What else does it read?
- A list item by item, null as '', a number as its text (what `str_replace()` read). `$` and `\` in the content stay text.

Tests: `tests/rich_text_path_placeholder_test.php`. Related: [[api-design-ads]], [[api-form-rich-text-in]].
