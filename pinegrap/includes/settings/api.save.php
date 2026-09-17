<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the API screen writes: the master switch, HTTPS, the public description, log retention and the upload folder.
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
}
