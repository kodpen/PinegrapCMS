<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Integrations screen writes: the application API (master switch, HTTPS, public description, log retention, upload folder) and Google reviews.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// ── Google Reviews ──
// A key or a Place ID that is not one refuses the whole category before
// anything is written; the sealed key is stored after the API columns.
$google_reviews_post = pg_grev_settings_check($_POST, $liveform);

// API uploads are not filed in the Workspace's folders nor in the Recycle
// Bin (pg_settings_folder_choice_ok()): they would show up as a
// conversation's files.
if (!pg_settings_folder_choice_ok((int) post_value('api_upload_folder_id'))) {
    $liveform->mark_error('api_upload_folder_id', lang('Choose a folder outside the Workspace and the Recycle Bin.'));
}

if ($liveform->check_form_errors()) {
    return;
}

// The columns arrive together with the 2026.4.4 upgrade; on a site that has
// not run it the card shows a note and there is nothing to write.
if (waf_table_has_column('config', 'api_enabled')) {

    // The purge job treats anything under a day as the default, so the range
    // the form offers starts at one; the upper bound is what the form says.
    $api_log_retention_days = max(1, min((int) post_value('api_log_retention_days'), 3650));

    db("UPDATE config SET
        api_enabled = '" . (post_value('api_enabled') ? 1 : 0) . "',
        api_require_https = '" . (post_value('api_require_https') ? 1 : 0) . "',
        api_openapi_public = '" . (post_value('api_openapi_public') ? 1 : 0) . "',
        api_log_retention_days = '" . $api_log_retention_days . "',
        api_upload_folder_id = '" . (int) post_value('api_upload_folder_id') . "',
        last_modified_user_id = '" . USER_ID . "',
        last_modified_timestamp = UNIX_TIMESTAMP()");

    // The Team devices switch is drawn only where api_apps has its kind column
    // (includes/settings/api.php); where it is not drawn, nothing was posted
    // for it and the applications are left as they are.
    if (waf_table_has_column('api_apps', 'kind')) {

        if (!defined('PG_API_PANEL')) {
            define('PG_API_PANEL', true);
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/api/device_switch.php');

        api_device_switch_save(post_value('api_team_devices') ? true : false, USER_ID, $_SESSION['sessionusername']);
    }
}

pg_grev_settings_save($google_reviews_post, $liveform);
