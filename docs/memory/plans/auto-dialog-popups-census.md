---
name: auto-dialog-popups-census
description: Census PU1 of auto-dialog-popups: who touches auto_dialogs, open_dialog() and jQuery UI, ui-dialog styles, the cookie notice layer and pg:consent, a shared component drawn outside a page
---

## Who reads or writes auto_dialogs?
- Screens `view_auto_dialogs.php`, `add_auto_dialog.php`, `edit_auto_dialog.php`; templates `includes/templates/view_auto_dialogs.php`, `edit_auto_dialog.php`; `includes/forms/auto_dialog_form.php` (`ui/auto-dialog-shared-form.md`); writes `includes/fn/auto_dialogs.php`. API `includes/api/resources/design_dialogs_regions.php` (`api/api-design-dialogs-regions.md`).
- Site: `get_page_content.php` under `if (AUTO_DIALOGS)`: enabled rows, `pg_auto_dialogs_for_page()`, then cookie `software[auto_dialog_<id>]` unset or older than `frequency` hours, and `page` blank or a substring of the page name (case-blind).
- Also `install/index.php`, `includes/fn/innodb.php`, `list_table.php`, `output.php` (menu), `includes/panel/search.php`, `get_help_url.php`; switch `config.auto_dialogs` (Features card, `AUTO_DIALOGS`).
- Starter rows, both `data/backups/*/sql.sql`: ids 2, 3, disabled, `/ebook-offer-dialog`, `/ticket-discount-dialog`, 15 s, 24 h.
- Tests: `auto_dialog_form_test.php`, `api_design_dialogs_regions_test.php`, `auto_dialog_number_boxes_test.php`, `settings_lists_td11_test.php`, `web_address_test.php`; a line in six panel tests.

## What does the site script do?
- `frontend.src.js` `software.init_auto_dialogs()`: stops in an iframe and on body class `disable_auto_dialogs`, skips `disable_auto_dialog_<id>` (set only by a page's own body tag); waits delay minus `visit_length` (1 s at least); `software.open_dialog()` with classes `auto_dialog auto_dialog_<id>`; writes the cookie until 2030, outside `pg_consent_catalogue()`.
- Preview `?preview_auto_dialog=<id>` (role < 3): no delay, no cookie; reserved in `pg_sw_url_param_reserved()` and `style_designer.src.js`.
- Since PU2 the browser picks and draws the `<dialog>`; no session key. Since PU3 one cookie `pg_popup`, in the catalogue, and the consent wait (`platform/auto-dialog-browser-dialog.md`).
- `software.open_dialog()` has one caller, `init_auto_dialogs()`; `open_dialog()` in `assets/js/backend.src.js` is the panel's own.

## Which jQuery UI dialogs stay?
- `frontend.src.js`: `software_initialize_edit_region_dialog()`, `software.init_share_comment()`, `software.kiosk.show_dialog()`; the panel's theme designer. jQuery UI loads on every site page (`get_page_content.php` `$output_jquery`).

## Which stylesheets style ui-dialog?
- `frontend.src.css`, `livesite.src.css`, `pinegrap.src.css` (and hand-kept `.min.css`), `generate_system_theme_css.php`, and one `div.software.ui-dialog` rule in each of 8 backup themes: `english_default/files/theme-{blue,foundrygreen,maroon,orange}.css`, `turkish_default/files/theme_{_noise,handwrited,modern,smoothy}.css`. None names `auto_dialog`.
- Share-comment and kiosk dialogs carry `software mobile_dialog` too, so these rules cannot all go.

## How does the cookie notice sit?
- `includes/fn/consent.php` `pg_consent_inject()` (only with `pg_consent_enabled()`, never for a bot), before the last `</body>`: `#pg-cc`, card `role="dialog" aria-modal="false"`, fixed, z-index 2147483000 (`assets/css/pg_consent.css`); not a `<dialog>`.
- `assets/js/pg_consent.js` is deferred (runs after the inline auto dialog script); `start()` dispatches `pg:consent` on `document`, detail the choices, on load with a stored answer and after each answer. No notice, no event.
- `showModal()` makes the notice and its reopen button inert: no modal pop-up while the notice is open.

## How can PHP draw a shared component outside a page?
- `includes/fn/designer.php` `pg_common_region_component_html($sid, $mode)` (used by the `<cregion>` pass). Opened alone: `edit_system_style.php?component=` (`designer/shared-component-opened-alone.md`).
- Left out: the component's CSS outside its design; the screens' old look (`panel-old-screens-census-site-content.md`).
