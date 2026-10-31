<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Appearance cards: the panel theme, the dashboard, the
 * rich-text editor and, for an administrator, the private label branding.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// ── Theme ──
$pg_settings_cards[] = '
                        <div id="pgset-theme" class="pg-set-card" data-pg-advanced-visuals="' . ($advanced_visual_effects_checked ? '1' : '0') . '">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Theme') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $advanced_visual_effects_checked . ' class="" type="checkbox" id="advanced_visual_effects" name="advanced_visual_effects"/></div>
                                                <label class="" for="advanced_visual_effects">' . lang('Enable Transparent Acrylic Effect') . '<br/><span class="form-text">' . lang('If the device you are using is weak in hardware, you can disable this setting to get a faster interface.') . '</span></label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label" for="custom_css">' . lang('Custom CSS') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="custom_css" id="custom_css" rows="25" cols="60" wrap="off">' . $custom_css . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'custom_css', 'code_type' => 'css')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// The acrylic effect is a class on the body, set when the page is drawn, and
// every glass rule of the menus and the dialog hangs on it. The pane is read
// again after every save, so the card carries what is stored now and its
// script puts the class on or off the page behind the dialog at once.
$pg_settings_scripts[] = '
<script>
    (function () {
        var card = document.getElementById("pgset-theme");

        if (card && document.body) {
            document.body.classList.toggle("advanced-visuals", card.getAttribute("data-pg-advanced-visuals") === "1");
        }
    })();
</script>';

// ── Dashboard ──
//
// Designers and administrators only ($dashboard_card_shown, prep.php); the
// save checks the same gate, so a manager's save of this category leaves the
// dashboard alone. Card style, backdrop and widget order are on the dashboard
// row and apply to everyone's dashboard; the refresh time is this sign-in's.
// Reset Widgets is a named submit button: the save writes the settings first,
// then puts the widgets back in their default order.
if ($dashboard_card_shown) {

    $output_dashboard_appearance = '
                                        <div class="col-12 ">
                                            <div class="alert theme-secondary mb-0 py-3 small">' . lang('Run the upgrade to use this setting.') . '</div>
                                        </div>';

    if ($dashboard_appearance_ready) {

        $output_dashboard_appearance = '
                                        <div class="pg-f-md">
                                            <label for="dashboard_widget_theme" class="form-label">' . lang('Card Style') . '</label>
                                            <select name="dashboard_widget_theme" id="dashboard_widget_theme" class="form-control">' . $output_dashboard_theme_options . '</select>
                                            <div class="form-text">' . lang('How the cards on the dashboard are drawn, for everyone who uses the panel.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="dashboard_panel_backdrop" class="form-label">' . lang('Panel Backdrop') . '</label>
                                            <select name="dashboard_panel_backdrop" id="dashboard_panel_backdrop" class="form-control">' . $output_dashboard_backdrop_options . '</select>
                                            <div class="form-text">' . lang('What sits behind the dashboard cards. Match Card Style picks the one that suits the chosen style.') . '</div>
                                        </div>';
    }

    $pg_settings_cards[] = '
                        <div id="pgset-dashboard" class="pg-set-card"'
                            . ($dashboard_appearance_ready
                                ? ' data-pg-dashboard-theme="' . h($dashboard_appearance['widget_theme']) . '"'
                                  . ' data-pg-dashboard-backdrop="' . h($dashboard_appearance['backdrop']) . '"'
                                : '')
                            . ' data-pg-dashboard-refresh="' . (int) $dashboard_refresh . '"'
                            . ' data-pg-dashboard-reset="' . ($dashboard_reset_done ? '1' : '0') . '">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Dashboard') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . $output_dashboard_appearance . '
                                        <div class="pg-f-xs">
                                            <label for="dashboard_refresh" class="form-label">' . lang('Refresh Time') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('seconds') . '</span>
                                                <input type="number" name="dashboard_refresh" id="dashboard_refresh" class="form-ghost" value="' . (int) $dashboard_refresh . '" min="10" max="86400" step="1" inputmode="numeric" style="text-align: right;"/>
                                            </label>
                                            <div class="form-text">' . lang('How often the dashboard asks for fresh figures, at least 10 seconds. It is kept for this sign-in only and goes back to 60 seconds at the next one.') . '</div>
                                        </div>
                                        <div class="col-12 ">
                                            <button type="submit" name="dashboard_reset_widgets" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . lang('Reset Widgets') . '</button>
                                            <div class="form-text">' . lang('Puts the dashboard cards back in their default order. This button also saves the other settings on this screen.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

    // Over the dashboard, a save shows on it: the pane is read again after
    // every save, so the card carries what is stored now. Card style and
    // backdrop are attributes the stylesheet reads and change in place. A new
    // refresh time or a reset order needs the page drawn again (its timer is
    // set on load, the server places the cards), so the dashboard is loaded
    // again once the dialog closes, after the confirmation has been read --
    // without welcome.php?refresh=N, which would set the old time again, and
    // without the #settings hash, which would open the dialog again. On any
    // other screen, and on the settings page, there is no #content carrying
    // data-widget-theme and nothing happens.
    $pg_settings_scripts[] = '
<script>
    (function () {
        var board = document.querySelector("main#content[data-widget-theme]");
        var card = document.getElementById("pgset-dashboard");
        var dialog = document.getElementById("pg_settings_modal");

        if (!board || !card) {
            return;
        }

        var theme = card.getAttribute("data-pg-dashboard-theme");
        var backdrop = card.getAttribute("data-pg-dashboard-backdrop");

        if (theme) {
            board.setAttribute("data-widget-theme", theme);
        }

        if (backdrop) {
            board.setAttribute("data-panel-backdrop", backdrop);
        }

        var refresh = (parseInt(card.getAttribute("data-pg-dashboard-refresh"), 10) || 0) * 1000;
        var stale = (card.getAttribute("data-pg-dashboard-reset") === "1")
            || (refresh > 0 && window.pg_widgets && (window.pg_widgets.interval !== refresh));

        if (!stale || !dialog || window.pgDashboardReloadArmed) {
            return;
        }

        window.pgDashboardReloadArmed = true;

        pgUI.dialog.on(dialog, "hidden", function () {
            window.location.href = window.location.pathname;
        }, { once: true });
    })();
</script>';
}

// ── Rich-text Editor ──
$pg_settings_cards[] = '
                        <div id="pgset-editor" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Rich-text Editor') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <fieldset><legend class="form-label fs-md">'. lang('Editor Version') . '</legend>
                                            <div class="form-field">
                                                <input value="latest" class="radio" type="radio" id="page_editor_version_latest" name="page_editor_version" ' . $page_editor_version_latest_checked . '>
                                                <label class="" for="page_editor_version_latest">'. lang('Latest') . '</label>
                                            </div>
                                            <div class="form-field">
                                                <input value="previous" class="radio" type="radio" id="page_editor_version_previous" name="page_editor_version" ' . $page_editor_version_previous_checked . '>
                                                <label class="" for="page_editor_version_previous">'. lang('Previous') . '</label>
                                            </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $page_editor_font_checked . ' class="" type="checkbox" id="page_editor_font" name="page_editor_font"/></div>
                                                <label class="" for="page_editor_font">' . lang('Font Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $page_editor_font_size_checked . ' class="" type="checkbox" id="page_editor_font_size" name="page_editor_font_size"/></div>
                                                <label class="" for="page_editor_font_size">' . lang('Font Size Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $page_editor_font_style_checked . ' class="" type="checkbox" id="page_editor_font_style" name="page_editor_font_style"/></div>
                                                <label class="" for="page_editor_font_style">' . lang('Font Style Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $page_editor_font_color_checked . ' class="" type="checkbox" id="page_editor_font_color" name="page_editor_font_color"/></div>
                                                <label class="" for="page_editor_font_color">' . lang('Font Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $page_editor_background_color_checked . ' class="" type="checkbox" id="page_editor_background_color" name="page_editor_background_color"/></div>
                                                <label class="" for="page_editor_background_color">' . lang('Background Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="spell_check_engine_info" class="form-label">' . lang('Spell Checker Engine') . '</label>
                                            <input class="form-control disabled" readonly="true" id="spell_check_engine_info" value="' . $spell_checker_engine_info['name'] . '"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Private Label Branding ──
// The panel's own logo, stylesheet and footer links, kept in data/config.php
// (pg_private_label_config_keys()) behind the private_label switch of the
// config row. The administrator's alone, as private_label.php was: for
// anyone else the card is not drawn and appearance.save.php writes none of
// it. A logo or stylesheet that is init.php's default shows an empty box.
if ((int) $user['role'] === 0) {

    $private_label_values = array();

    foreach (pg_private_label_config_keys() as $private_label_field => $private_label_constant) {
        $private_label_values[$private_label_field] = defined($private_label_constant) ? (string) constant($private_label_constant) : '';
    }

    if ($private_label_values['logo_url'] === PATH . SOFTWARE_DIRECTORY . '/assets/images/logo.png?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/images/logo.png')) {
        $private_label_values['logo_url'] = '';
    }

    if ($private_label_values['control_panel_stylesheet_url'] === pg_default_control_panel_stylesheet_url()) {
        $private_label_values['control_panel_stylesheet_url'] = '';
    }

    $private_label_on = (defined('PRIVATE_LABEL') && PRIVATE_LABEL);

    $output_private_label_links = '';

    for ($private_label_link = 1; $private_label_link <= 5; $private_label_link++) {
        $output_private_label_links .= '
                                            <div class="col-12 md:col-6">
                                                <label for="footer_link_' . $private_label_link . '_label" class="form-label">' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array((string) $private_label_link))) . '</label>
                                                <input type="text" name="footer_link_' . $private_label_link . '_label" id="footer_link_' . $private_label_link . '_label" class="form-control" value="' . h($private_label_values['footer_link_' . $private_label_link . '_label']) . '" />
                                            </div>
                                            <div class="col-12 md:col-6">
                                                <label for="footer_link_' . $private_label_link . '_url" class="form-label">' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array((string) $private_label_link))) . '</label>
                                                <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="footer_link_' . $private_label_link . '_url" id="footer_link_' . $private_label_link . '_url" class="form-control" value="' . h($private_label_values['footer_link_' . $private_label_link . '_url']) . '" />
                                            </div>';
    }

    $pg_settings_cards[] = '
                        <div id="pgset-private-label" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Private Label Branding') . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="private_label_card" value="1" />
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . ($private_label_on ? ' checked="checked"' : '') . ' class="collapse-switcher" type="checkbox" id="private_label" name="private_label" data-bs-target="#private_label_fields"/></div>
                                                <label class="" for="private_label">' . lang('Enable Private Label') . '</label>
                                            </div>
                                            <div class="form-text">' . lang('Update the branding for the control panel.') . '</div>
                                        </div>
                                        <div class="col-12 collapse' . ($private_label_on ? ' show' : '') . '" id="private_label_fields">
                                            <div class="row gy-5">
                                                <div class="col-12 md:col-6">
                                                    <label for="logo_url" class="form-label">' . lang('Logo URL') . '</label>
                                                    <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="logo_url" id="logo_url" class="form-control" value="' . h($private_label_values['logo_url']) . '" />
                                                    <div class="form-text">' . lang('leave blank to use the default product logo') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6">
                                                    <label for="control_panel_stylesheet_url" class="form-label">' . lang('Control Panel Stylesheet URL') . '</label>
                                                    <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="control_panel_stylesheet_url" id="control_panel_stylesheet_url" class="form-control" value="' . h($private_label_values['control_panel_stylesheet_url']) . '" />
                                                </div>
                                                <div class="col-12">
                                                    <h3 class="h6 fw-bold mb-0">' . lang('Footer Links') . '</h3>
                                                </div>' . $output_private_label_links . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}
