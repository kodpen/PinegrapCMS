---
name: template-previews-code
description: What the code holds for plans/template-previews.md (development 8d9a31a): the installer's design step and layout, the DB-less preview, the drawings and their users, a bug found
---

## The installer's design step (install/index.php)?
- Cards: `$output_design_choices` (~L2737-2816), one per `pg_design_templates()` entry: badges, name, description, the three shared points plus the template's `highlights`; Standard installation last. Then look and palette pickers, page buttons (`#pg_design_pages`), e-mail buttons (`#pg_design_mails_row`) and the 1280×800 frame `.pg-design-frame` (~L3302-3329).
- Script (~L4377-4481): `pg_install_select_design()`, `pg_install_update_preview()` (page buttons from `data-preview-pages`), `pg_install_preview_url()`, `pg_install_scale_preview()`. Picking a template never sets the pickers.
- Layout CSS (~L3094-3103): `main#content.pg-install { max-width: 1280px }` overrides the `xl:container` (Bootstrap 6: 1200 px at xl, 1440 at 2xl); `.pg-wizard-grid` is 250px | content | 310px side column from 1280 px, leaving the step about 650 px.
- `install_action=design_preview` (~L436-482) prints `pg_design_template_preview_html()` without a database; its links lead to the template's other preview pages and it posts `{pgDesignPreview: key}` to its parent.

## The drawings (includes/fn/design_themes.php)?
- `pg_design_thumb_svg($look, $palette, $class, $label, $kind)` (~L832): a 320×200 SVG coloured by `pg_design_thumb_vars()`; nine templates have a drawing of their own (`_pg_design_thumb_<thumb>()`, the template's `thumb` key), the rest the generic home page.
- Used only in `includes/designer_screen.php`: `pg_designer_list_thumb()` (~L959: the large card's browser frame and the tiles), `pg_designer_home_drawing('template')` (~L1026: three minis), `pg_designer_template_modal()` (~L1844: one per card) and the picker script that recolours them (~L2048-2075).

## Template functions (includes/fn/designer.php)?
- `pg_design_template_summary()` (~L7845: name, icon, look, palette, highlights, thumb), `pg_design_template_theme()` (~L7880), `pg_design_template_preview_pages()` (~L9495), `pg_design_template_email_pages()` (~L9531), `pg_design_template_preview_html()` (~L9631, any template the request can load, custom ones from their rows).

## Drawing a real page?
- `pg_background_render()` and `audit_run.php` (`PG_ANONYMOUS_RENDER` before `init.php`, the role read from the session, the session closed for the render): `platform/background-render.md`. `get_page_content($page_id, …, $mode = 'preview', …, $toolbar = false)` draws by id. The editor home's gate is `pg_designer_access()` (`view_system_styles.php`).

## Which bug turned up while the screens were drawn?
- The installer loads the designer script for starter layouts from `implode('', pg_js_bundle_urls('assets/js/style_designer.min.js'))` (~L2735, ~L4526). When the sources are served one by one (`ENVIRONMENT` development, or the .min.js missing) the addresses are glued into one, the script never loads and a template install stops with "The design template could not be prepared in this browser". TP2 loads them in order.
