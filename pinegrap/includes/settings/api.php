<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the API cards: the master switch, HTTPS, the public description, log retention and the upload folder.
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

// The switches only make sense once the 2026.4.4 columns exist. Without them
// the card says so instead of drawing controls that the save would ignore.
$api_enabled_checked        = ($api_enabled == 1) ? ' checked="checked"' : '';
$api_require_https_checked  = ($api_require_https == 1) ? ' checked="checked"' : '';
$api_openapi_public_checked = ($api_openapi_public == 1) ? ' checked="checked"' : '';

$output_api_not_ready = '
                    <div class="col-12">
                        <div class="alert alert-secondary mb-0 py-2 small">' . lang('These settings arrive with the 2026.4.4 upgrade, which this site has not run yet.') . '</div>
                    </div>';

// ── Application API ──
$output_api_switches = '
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $api_enabled_checked . ' class="form-check-input" type="checkbox" id="api_enabled" name="api_enabled"/>
                            <label class="form-check-label" for="api_enabled">' . lang('Enable the application API') . '</label>
                            <div class="form-text">' . lang('When this is off every call is answered with 503, whatever key it carries. The application keys are kept and work again as soon as the API is turned back on.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $api_require_https_checked . ' class="form-check-input" type="checkbox" id="api_require_https" name="api_require_https"/>
                            <label class="form-check-label" for="api_require_https">' . lang('Require HTTPS') . '</label>
                            <div class="form-text">' . lang('The key and secret travel with every call, so a call over plain HTTP is refused with 403. Turn this off only on an internal network that has no certificate.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $api_openapi_public_checked . ' class="form-check-input" type="checkbox" id="api_openapi_public" name="api_openapi_public"/>
                            <label class="form-check-label" for="api_openapi_public">' . lang('Publish the API description without credentials') . '</label>
                            <div class="form-text">' . lang('The description at /openapi.json names every endpoint and parameter. It is served to anyone only while this is on; otherwise it needs a valid application key like every other call.') . '</div>
                        </div>
                    </div>';

$pg_settings_cards[] = '
    <div id="pgset-api" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Application API') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">' . ($api_settings_ready ? $output_api_switches : $output_api_not_ready) . '
                    <div class="col-12">
                        <div class="form-text">
                            <i class="bi bi-key me-1"></i>' . lang(array(
                                'string' => 'Application keys and permissions are managed on the {var:1} screen.',
                                'vars'   => array('<a href="api_settings.php">' . lang('Application Access') . '</a>'))) . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Uploads & Logs ──
$output_api_storage = '
                    <div class="pg-f-lg">
                        <label for="api_upload_folder_id" class="form-label">' . lang('Upload Folder') . '</label>
                        <select name="api_upload_folder_id" id="api_upload_folder_id" class="form-select">
                            <option value="0">' . lang('-Not selected-') . '</option>' . select_folder($api_upload_folder_id, 0) . '
                        </select>
                        <div class="form-text">' . lang('Files uploaded through the API are filed in this folder. When no folder is selected, or the selected one has since been deleted, they go to the topmost folder.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="api_log_retention_days" class="form-label">' . lang('Log Retention') . '</label>
                        <div class="input-group">
                            <input type="number" name="api_log_retention_days" id="api_log_retention_days" class="form-control" value="' . (int) $api_log_retention_days . '" min="1" max="3650" inputmode="numeric" style="text-align: right;"/>
                            <span class="input-group-text">' . lang('day(s)') . '</span>
                        </div>
                        <div class="form-text">' . lang('Requests older than this are removed from the API log once a day by the cron job. Between 1 and 3650 days.') . '</div>
                    </div>';

$pg_settings_cards[] = '
    <div id="pgset-api-storage" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Uploads & Logs') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">' . ($api_settings_ready ? $output_api_storage : $output_api_not_ready) . '
                </div>
            </div>
        </div>
    </div>';
