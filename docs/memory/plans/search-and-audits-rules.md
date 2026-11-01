---
name: search-and-audits-rules
description: Research of 2026-11-01 behind plans/search-and-audits.md: the visual editor's audit, the SEO structure rules and the accessibility report side by side; where inputs, codes and severities differ
---

## Which engines judge a page?
- Editor "!" badges: `validateTree()` in `style_designer.src.js` (`_validationRules`, `_pageLevelIssues()`, `_sdCanvasAuditIssues()`) on the editor tree and the canvas, live.
- Editor SEO panel: `seo_check` (`includes/panel/designer.php`): meta, plus `pg_seo_analyze_html()` in `fragment` mode on `generateHTML()` (shared components, widgets, PHP regions and the head not drawn; Open Graph and JSON-LD off), plus the document-only findings of the last stored pass, plus stored link and speed.
- Panel: `pg_seo_analyze_page()` on the server render (`document` mode) and `pg_a11y_check_html()` on the same render; nightly or by button.

## Same concept, three rules
| Concept | Editor | SEO structure | Accessibility |
|---|---|---|---|
| Image alt | `img_alt` | `img_no_alt` error | `a11y_img_alt` error |
| Link or button name | `empty_btn` | `link_empty` error, `button_empty` warning | `a11y_link_name`, `a11y_button_name` error |
| Generic link text | `link_generic` | `link_generic_anchor` notice | `a11y_link_generic` notice |
| Heading order | `heading_skip` | `heading_level_skip` warning | `a11y_heading_order` warning |
| Main landmark | `main_missing` | `main_missing` notice | `a11y_main_missing` warning |
| Field label | `input_no_label` | `input_no_label` warning | `a11y_form_label` error |
| Frame title | none | `iframe_no_title` notice | `a11y_frame_title` error |
| Page language | none | `html_lang_missing` warning | `a11y_html_lang` error |
| Contrast | `low_contrast`, resolved CSS | none | `a11y_contrast`, inline styles only |
| Target size | `target_size`, layout | none | `a11y_target_size`, inline px only |

- Editor rules without an `seo:` code although SEO has the concept: `img_alt` (`img_no_alt`), `empty_btn` (`link_empty`, `button_empty`), `heading_skip` (`heading_level_skip`).

## What is in one place only?
- Editor: `dup_id`, `nested_interactive`, `form_no_submit`, `form_no_action`, `redundant_entry`, `cf_field_unnamed`.
- Accessibility report: `a11y_idref_broken`, `a11y_error_association`, `a11y_media_autoplay`.
- SEO: head, canonical, Open Graph, JSON-LD, content, paragraph and image-attribute rules.

## Why does it matter once every page is a visual design?
- A visual design sets colours and sizes with classes, themes and palettes. The server's contrast and target-size checks see almost none of them, the editor's see all (`designer/designer-audit-contrast.md`: the Store template's `text-primary` on `bg-light`, 4.27:1). The report reads clean while the editor warns.
- The editor's SEO structure number comes from a fragment without the server-drawn parts, and its document findings from the last stored pass, which the dispatcher runs 120 s a day: it moves away from the panel's as the site grows. The parity fix of `designer/seo-score-live-check-parity.md` made the arithmetic the same, not the rules or the input.
