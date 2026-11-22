<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Appearance cards: the panel theme, the dashboard, the
 * rich-text editor and, for an administrator, the private label branding.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/appearance_settings_fold.php.
 * A switch's fields sit under it, drawn whatever its state. Fills
 * $pg_settings_cards with the one list of rows. Which cards belong here is
 * said once, in includes/settings/registry.php; this file holds them, and
 * appearance.save.php writes what they edit. The variables they read are
 * prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/appearance_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_appearance_fold_order().
$appearance_parts = array();

// ── Panel look ──
// Panel CSS is printed in the control panel only (output_header() in
// includes/fn/output.php), never on the site.
$appearance_parts['theme'] = array('html' => '
                        <div id="pgset-theme" class="pg-set-card" data-pg-advanced-visuals="' . ($advanced_visual_effects_checked ? '1' : '0') . '">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Panel look') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . $advanced_visual_effects_checked . ' id="advanced_visual_effects" name="advanced_visual_effects" aria-describedby="advanced_visual_effects_help"/>
                                                <label class="pg-text-2" for="advanced_visual_effects">' . lang('Transparent acrylic effect') . '</label>
                                            </div>
                                            <div class="form-text" id="advanced_visual_effects_help">' . lang('For everyone who uses the panel. Turn it off if the panel feels slow.') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="custom_css">' . lang('Panel CSS') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="custom_css" id="custom_css" rows="25" cols="60" wrap="off">' . h($custom_css) . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'custom_css', 'code_type' => 'css')) . '
                                            </div>
                                            <div class="form-text">' . lang('Styles the control panel only, never the site.') . (((int) $user['role'] === 0) ? ' ' . lang('A whole stylesheet can be set under Private label branding.') : '') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_appearance_fold_theme($advanced_visual_effects_checked !== '', $custom_css);

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
// row and apply to everyone's dashboard, under "For everyone"; the refresh
// time is this sign-in's, under "Only for you, until you sign out".
// Reset widgets is a named submit button: the save writes the settings first,
// then puts the widgets back in their default order.
if ($dashboard_card_shown) {

    $output_dashboard_appearance = '
                                        <div class="col-12 ">
                                            <div class="alert theme-secondary mb-0 py-3 small">' . lang('Run the upgrade to use this setting.') . '</div>
                                        </div>';

    if ($dashboard_appearance_ready) {

        $output_dashboard_appearance = '
                                        <div class="pg-f-md">
                                            <label for="dashboard_widget_theme" class="form-label">' . lang('Card style') . '</label>
                                            <select name="dashboard_widget_theme" id="dashboard_widget_theme" class="form-control">' . $output_dashboard_theme_options . '</select>
                                            <div class="form-text">' . lang('How the cards on the dashboard are drawn, for everyone who uses the panel.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="dashboard_panel_backdrop" class="form-label">' . lang('Panel backdrop') . '</label>
                                            <select name="dashboard_panel_backdrop" id="dashboard_panel_backdrop" class="form-control">' . $output_dashboard_backdrop_options . '</select>
                                            <div class="form-text">' . lang('What sits behind the dashboard cards. Match Card Style picks the one that suits the chosen style.') . '</div>
                                        </div>';
    }

    $appearance_parts['dashboard'] = array('html' => '
                        <div id="pgset-dashboard" class="pg-set-card"'
                            . ($dashboard_appearance_ready
                                ? ' data-pg-dashboard-theme="' . h($dashboard_appearance['widget_theme']) . '"'
                                  . ' data-pg-dashboard-backdrop="' . h($dashboard_appearance['backdrop']) . '"'
                                : '')
                            . ' data-pg-dashboard-refresh="' . (int) $dashboard_refresh . '"'
                            . ' data-pg-dashboard-reset="' . ($dashboard_reset_done ? '1' : '0') . '">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Dashboard') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('For everyone') . '</h3></div>' . $output_dashboard_appearance . '
                                        <div class="col-12">
                                            <button type="submit" name="dashboard_reset_widgets" value="1" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>' . lang('Reset widgets') . '</button>
                                            <div class="form-text">' . lang('Puts the dashboard cards back in their default order. This button also saves the other settings on this screen.') . '</div>
                                        </div>
                                        <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Only for you, until you sign out') . '</h3></div>
                                        <div class="pg-f-xs">
                                            <label for="dashboard_refresh" class="form-label">' . lang('Refresh every') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('seconds') . '</span>
                                                <input type="number" name="dashboard_refresh" id="dashboard_refresh" class="form-ghost text-end" value="' . (int) $dashboard_refresh . '" min="10" max="86400" step="1" inputmode="numeric"/>
                                            </label>
                                            <div class="form-text">' . lang('How often the dashboard asks for fresh figures, at least 10 seconds. It is kept for this sign-in only and goes back to 60 seconds at the next one.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_appearance_fold_dashboard(
                            $dashboard_appearance_ready ? $dashboard_choice_labels['widget_theme'][$dashboard_appearance['widget_theme']] : '',
                            $dashboard_refresh);

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

// ── Rich-text editor ──
// The five tool switches, each a button of the editor's toolbar.
$editor_tools = '';

foreach (array(
    'page_editor_font'             => array($page_editor_font_checked, lang('Font')),
    'page_editor_font_size'        => array($page_editor_font_size_checked, lang('Font size')),
    'page_editor_font_style'       => array($page_editor_font_style_checked, lang('Font style')),
    'page_editor_font_color'       => array($page_editor_font_color_checked, lang('Text colour button')),
    'page_editor_background_color' => array($page_editor_background_color_checked, lang('Background colour button')),
) as $editor_tool => $editor_tool_face) {
    $editor_tools .= '
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . $editor_tool_face[0] . ' id="' . $editor_tool . '" name="' . $editor_tool . '"/>
                                                <label class="pg-text-2" for="' . $editor_tool . '">' . $editor_tool_face[1] . '</label>
                                            </div>
                                        </div>';
}

$appearance_parts['editor'] = array('html' => '
                        <div id="pgset-editor" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Rich-text editor') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12">
                                            <fieldset><legend class="form-label pg-field-label">'. lang('Editor version') . '</legend>
                                            <div class="pg-seg flex-wrap">
                                                <label class="pg-seg-item" for="page_editor_version_latest"><input value="latest" class="visually-hidden" type="radio" id="page_editor_version_latest" name="page_editor_version" ' . $page_editor_version_latest_checked . '>'. lang('Latest') . '</label>
                                                <label class="pg-seg-item" for="page_editor_version_previous"><input value="previous" class="visually-hidden" type="radio" id="page_editor_version_previous" name="page_editor_version" ' . $page_editor_version_previous_checked . '>'. lang('Previous') . '</label>
                                            </div>
                                            </fieldset>
                                        </div>' . $editor_tools . '
                                        <div class="col-12">
                                            <div class="pg-text-meta">' . h(lang(array('string' => 'Spell checker: {var:1}', 'vars' => $spell_checker_engine_info['name']))) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_appearance_fold_editor($page_editor_version_previous_checked !== '' ? 'previous' : 'latest');

// ── Private label branding ──
// The panel's own logo, stylesheet and footer links, kept in data/config.php
// (pg_private_label_config_keys()) behind the private_label switch of the
// config row, its fields drawn whatever the switch says. The administrator's
// alone, as private_label.php was: for anyone else the card is not drawn and
// appearance.save.php writes none of it. A logo or stylesheet that is
// init.php's default shows an empty box.
if ((int) $user['role'] === 0) {

    $private_label_values = array();

    // Drawn after a save in the same request, the values it wrote.
    $private_label_saved = pg_private_label_saved_values();

    foreach (pg_private_label_config_keys() as $private_label_field => $private_label_constant) {
        $private_label_values[$private_label_field] = is_array($private_label_saved)
            ? (isset($private_label_saved[$private_label_field]) ? (string) $private_label_saved[$private_label_field] : '')
            : (defined($private_label_constant) ? (string) constant($private_label_constant) : '');
    }

    if ($private_label_values['logo_url'] === PATH . SOFTWARE_DIRECTORY . '/assets/images/logo.png?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/images/logo.png')) {
        $private_label_values['logo_url'] = '';
    }

    if ($private_label_values['control_panel_stylesheet_url'] === pg_default_control_panel_stylesheet_url()) {
        $private_label_values['control_panel_stylesheet_url'] = '';
    }

    $private_label_on = (defined('PRIVATE_LABEL') && PRIVATE_LABEL);

    // The footer links as list rows, each edited in its drawer, whose boxes
    // join the settings form by its id (the drawer sits outside it) and post
    // footer_link_<n>_label / _url in row order. "Add a link" opens the next
    // row's drawer while there are fewer than five. Move up, Move down and
    // Remove are named submit buttons: the pane's save runs and
    // pg_private_label_links_order() applies them (appearance.save.php).
    $private_label_in_dialog = !empty($pg_settings_in_dialog);
    $private_label_joins = ' form="' . ($private_label_in_dialog ? 'pg_settings_modal_form' : 'pg_settings_page_form') . '"';
    $private_label_foot = '<button type="submit"' . $private_label_joins . ' class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>';
    $private_label_links = pg_private_label_links_order($private_label_values);
    $private_label_link_count = 0;

    for ($private_label_link = 1; $private_label_link <= 5; $private_label_link++) {
        if (($private_label_links['footer_link_' . $private_label_link . '_label'] !== '') || ($private_label_links['footer_link_' . $private_label_link . '_url'] !== '')) {
            $private_label_link_count = $private_label_link;
        }
    }

    $private_label_link_fields = function ($n) use ($private_label_links, $private_label_joins) {
        return '
                            <div class="row gy-5">
                                <div class="col-12">
                                    <label for="footer_link_' . $n . '_label" class="form-label">' . lang('Label') . '</label>
                                    <input type="text" name="footer_link_' . $n . '_label" id="footer_link_' . $n . '_label"' . $private_label_joins . ' class="form-control" value="' . h($private_label_links['footer_link_' . $n . '_label']) . '" />
                                </div>
                                <div class="col-12">
                                    <label for="footer_link_' . $n . '_url" class="form-label">' . lang('Address') . '</label>
                                    <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="footer_link_' . $n . '_url" id="footer_link_' . $n . '_url"' . $private_label_joins . ' class="form-control" value="' . h($private_label_links['footer_link_' . $n . '_url']) . '" />
                                </div>
                            </div>';
    };

    $output_private_label_links = '';

    for ($private_label_link = 1; $private_label_link <= $private_label_link_count; $private_label_link++) {
        $private_label_link_label = $private_label_links['footer_link_' . $private_label_link . '_label'];
        $private_label_link_title = (trim($private_label_link_label) !== '') ? $private_label_link_label : lang('No label');
        $private_label_drawer = 'pg-footer-link-' . $private_label_link;

        $output_private_label_links .= '
                                                    <div class="pg-list-row" data-pg-footer-link>
                                                        <span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><span class="bi bi-link-45deg"></span></span>
                                                        <div class="pg-list-row-main">
                                                            <div class="pg-list-row-head"><span class="pg-list-row-title">' . h($private_label_link_title) . '</span></div>
                                                            <div class="pg-list-row-text">' . h($private_label_links['footer_link_' . $private_label_link . '_url']) . '</div>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1 align-self-center">
                                                            <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $private_label_drawer . '" aria-controls="' . $private_label_drawer . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), $private_label_link_title)))) . '"><span class="pg-btn-label">' . h(lang('Edit')) . '</span></button>
                                                            <span class="pg-menu-wrap">
                                                                <button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('More actions'), $private_label_link_title)))) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                                                <ul class="menu pg-menu-end">
                                                                    <li><button type="submit" name="footer_link_action" value="up:' . $private_label_link . '" class="menu-item"' . (($private_label_link === 1) ? ' disabled="disabled"' : '') . '><i class="bi bi-arrow-up me-3" aria-hidden="true"></i>' . h(lang('Move up')) . '</button></li>
                                                                    <li><button type="submit" name="footer_link_action" value="down:' . $private_label_link . '" class="menu-item"' . (($private_label_link === $private_label_link_count) ? ' disabled="disabled"' : '') . '><i class="bi bi-arrow-down me-3" aria-hidden="true"></i>' . h(lang('Move down')) . '</button></li>
                                                                    <li><hr class="menu-divider"></li>
                                                                    <li><button type="submit" name="footer_link_action" value="remove:' . $private_label_link . '" class="menu-item fg-emphasis-danger" onclick="return confirm(' . h(json_encode(lang('Remove this link from the panel\'s footer?'))) . ');"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Remove')) . '</button></li>
                                                                </ul>
                                                            </span>
                                                        </div>
                                                    </div>';

        $pg_settings_modals[] = pg_settings_drawer($private_label_drawer, h($private_label_link_title), $private_label_link_fields($private_label_link), $private_label_in_dialog, $private_label_foot);
    }

    $output_private_label_links = ($output_private_label_links !== '')
        ? '
                                                <div class="col-12">' . $output_private_label_links . '
                                                </div>'
        : '
                                                <div class="col-12">
                                                    <div class="pg-text-meta">' . h(lang('No link yet. Links added here stand in the panel\'s footer.')) . '</div>
                                                </div>';

    if ($private_label_link_count < 5) {
        $private_label_drawer = 'pg-footer-link-' . ($private_label_link_count + 1);
        $pg_settings_modals[] = pg_settings_drawer($private_label_drawer, h(lang('Add a link')), $private_label_link_fields($private_label_link_count + 1), $private_label_in_dialog, $private_label_foot);

        $output_private_label_links .= '
                                                <div class="col-12">
                                                    <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $private_label_drawer . '" aria-controls="' . $private_label_drawer . '"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Add a link')) . '</span></button>
                                                </div>';
    }

    $appearance_parts['private-label'] = array('html' => '
                        <div id="pgset-private-label" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Private label branding') . '
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="private_label_card" value="1" />
                                    <div class="row gy-5">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" class="pg-switch" value="1"' . ($private_label_on ? ' checked="checked"' : '') . ' id="private_label" name="private_label" aria-describedby="private_label_help"/>
                                                <label class="pg-text-2" for="private_label">' . lang('Use your own branding') . '</label>
                                            </div>
                                            <div class="form-text" id="private_label_help">' . lang('The control panel shows your logo, stylesheet and footer links instead of Pinegrap\'s.') . '</div>
                                        </div>
                                        <div class="col-12" id="private_label_fields">
                                            <div class="row gy-5">
                                                <div class="col-12 md:col-6">
                                                    <label for="logo_url" class="form-label">' . lang('Logo address') . '</label>
                                                    <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="logo_url" id="logo_url" class="form-control" value="' . h($private_label_values['logo_url']) . '" />
                                                    <div class="form-text">' . lang('Leave blank to use the default Pinegrap logo') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6">
                                                    <label for="control_panel_stylesheet_url" class="form-label">' . lang('Panel stylesheet address') . '</label>
                                                    <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="control_panel_stylesheet_url" id="control_panel_stylesheet_url" class="form-control" value="' . h($private_label_values['control_panel_stylesheet_url']) . '" />
                                                </div>
                                                <div class="col-12">
                                                    <h3 class="pg-group-label mb-0">' . lang('Footer links') . '</h3>
                                                </div>' . $output_private_label_links . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_appearance_fold_private_label($private_label_on);
}

$appearance_rows = array();

foreach (pg_appearance_fold_order() as $appearance_key) {
    if (isset($appearance_parts[$appearance_key])) {
        $appearance_rows[] = array('key' => $appearance_key) + pg_appearance_fold_face($appearance_key) + $appearance_parts[$appearance_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($appearance_rows);
