<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Appearance screen writes: the panel theme, the
 * dashboard, the rich-text editor and the private label branding.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// The Dashboard card is drawn for designers and administrators only, and only
// they may write what it holds. For a manager none of it is read, even when
// the request carries the fields, so their save of this category neither
// changes nor blanks the dashboard.
$dashboard_save = pg_dashboard_appearance_allowed($user['role']);
$dashboard_save_values = array();

if ($dashboard_save) {

    $dashboard_save_labels = array(
        'widget_theme'   => lang('Card Style'),
        'panel_backdrop' => lang('Panel Backdrop'));

    foreach (pg_dashboard_appearance_choices() as $dashboard_save_column => $dashboard_save_accepted) {

        $dashboard_save_value = post_value('dashboard_' . $dashboard_save_column);

        // A select the card did not draw (the 2026.4.4 columns are not there
        // yet) was not posted, and its column is left as it is.
        if (($dashboard_save_value === null) || !waf_table_has_column('dashboard', $dashboard_save_column)) {
            continue;
        }

        // Anything a select does not offer is a hand-made request: refused
        // like a marked field, before anything of the category is written.
        if (!in_array((string) $dashboard_save_value, $dashboard_save_accepted, true)) {
            $liveform->mark_error('dashboard_' . $dashboard_save_column, lang(array(
                'string' => 'Sorry, the value you entered for {var:1} is not valid.',
                'vars'   => h($dashboard_save_labels[$dashboard_save_column]))));
            continue;
        }

        $dashboard_save_values[$dashboard_save_column] = (string) $dashboard_save_value;
    }

    if ($liveform->check_form_errors()) {
        return;
    }
}

// Private Label Branding: the administrator's card (appearance.php). Read
// only when that card was drawn and posted, so another role's save, or one
// from a page drawn before the card existed, leaves the branding alone.
// Every field holds one value: one posted as a list would reach the
// data/config.php writer as an array, so it refuses the whole save before
// anything is written.
$private_label_save = ((int) $user['role'] === 0) && (post_value('private_label_card') !== null);

if ($private_label_save) {

    $private_label_fields = array_merge(array('private_label'), array_keys(pg_private_label_config_keys()));
    $private_label_lists = pg_request_unexpected_lists(array_intersect_key($_POST, array_flip($private_label_fields)));

    if ($private_label_lists) {
        $liveform->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $private_label_lists)))));
        return;
    }
}


    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            custom_css = '" . escape(post_value('custom_css')) . "',
            advanced_visual_effects = '" . escape(post_value('advanced_visual_effects')) . "',
            page_editor_version = '" . escape(post_value('page_editor_version')) . "',
            page_editor_font = '" . escape(post_value('page_editor_font')) . "',
            page_editor_font_size = '" . escape(post_value('page_editor_font_size')) . "',
            page_editor_font_style = '" . escape(post_value('page_editor_font_style')) . "',
            page_editor_font_color = '" . escape(post_value('page_editor_font_color')) . "',
            page_editor_background_color = '" . escape(post_value('page_editor_background_color')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

if ($dashboard_save) {

    // Column names from pg_dashboard_appearance_choices(), values from its
    // lists: nothing here comes from the request as it was sent.
    foreach ($dashboard_save_values as $dashboard_save_column => $dashboard_save_value) {
        db("UPDATE dashboard SET " . $dashboard_save_column . " = '" . escape($dashboard_save_value) . "'");
    }

    // The refresh time stays per sign-in, as welcome.php?refresh=N keeps it.
    // A value that is not a number keeps the one in use; one outside 10
    // seconds to a day is brought inside it, and the pane read after the
    // save shows what was kept.
    $_SESSION['software']['welcome']['widget']['refresh'] = pg_dashboard_refresh_seconds(
        post_value('dashboard_refresh'),
        pg_dashboard_refresh_seconds($_SESSION['software']['welcome']['widget']['refresh'] ?? '', 60));

    // Reset Widgets: the settings above are saved first, then the dashboard
    // forgets its arrangement. "default" is what the dashboard reads as the
    // factory order (welcome.php), the same value api.php's
    // update_dashboard_widgets stores for it.
    if (post_value('dashboard_reset_widgets')) {

        db("UPDATE dashboard SET order_widgets = 'default'");

        // For the first pane drawn after this save (prep.php).
        $_SESSION['software']['welcome']['widget']['reset'] = 1;

        $liveform->add_notice(lang('The dashboard widgets are back in their default order.'));
    }
}

if ($private_label_save) {

    $private_label_on = (post_value('private_label') == 1);

    db_q("UPDATE config SET private_label = ?", array($private_label_on ? '1' : '0'));

    // The constants as this request loaded them tell a changed value from
    // one left as it was (pg_private_label_config_content()).
    $private_label_current = array();

    foreach (pg_private_label_config_keys() as $private_label_constant) {
        $private_label_current[$private_label_constant] = defined($private_label_constant) ? (string) constant($private_label_constant) : null;
    }

    $private_label_original = is_readable(CONFIG_FILE_PATH) ? (string) @file_get_contents(CONFIG_FILE_PATH) : '';
    $private_label_content = pg_private_label_config_content($private_label_original, $private_label_on, $_POST, $private_label_current);

    // Rewritten only from a successful read: an empty result would replace
    // config.php with an empty file. pg_write_config_file() goes through a
    // temp file and rename, so a failure leaves the old file intact.
    if ($private_label_content === '') {
        $liveform->add_error(lang('Config file could not be opened.'));
    } elseif (($private_label_content !== $private_label_original) && !pg_write_config_file($private_label_content)) {
        $liveform->add_error(lang('The config file could not be written.'));
    }
}
