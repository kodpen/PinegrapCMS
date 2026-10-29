<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Google Analytics figures part of the Visitor Tracking
 * card (includes/fn/google_analytics.php): connect a Google account, choose
 * the GA4 property whose figures the dashboard's Google Analytics card shows,
 * read them now or stop using the property.
 *
 * Runs in the scope of includes/settings/seo.php and leaves the markup in
 * $google_analytics_figures; the Connect form goes to $pg_settings_modals,
 * outside the settings form. seo.save.php hands the posted fields to
 * pg_ga_save_from_screen().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

$google_analytics_figures = '';

if (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) {

    // Back from connect.php: the connection's own message.
    if (isset($_GET['ga_connect'])) {
        $ga_flash = pg_connect_flash_take();

        if ($ga_flash && ($ga_flash['kind'] === 'error')) {
            $liveform->add_error(h($ga_flash['text']));
        } elseif ($ga_flash) {
            $liveform->add_notice(h($ga_flash['text']) . ' ' . h(lang('Choose the Google Analytics property and save.')));
        }
    }

    $ga_state = pg_ga_state();
    $ga_connection = pg_ga_connection($ga_state);
    $ga_body = '';

    if (!is_array($ga_connection)) {

        if (pg_ga_ready() && pg_connect_app_ready(pg_connect_app('google'))) {
            $ga_body = '
                                        <div class="col-12">
                                            <button type="submit" form="pgset_ga_connect" class="btn btn-sm btn-outline theme-primary rounded-pill"><i class="bi bi-google me-1" aria-hidden="true"></i>' . lang('Connect a Google account') . '</button>
                                        </div>';

            // Forms cannot be nested, so the button above submits this one
            // by its form attribute.
            $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="pgset_ga_connect" class="disable_shortcut d-none">
                ' . pg_connect_start_fields('google', 'google_analytics', pg_settings_url('seo') . '?ga_connect=1#pgset-analytics') . '
            </form>';
        } else {
            $ga_body = '
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang('To show Google Analytics figures, set up the Google application first.')) . ' <a href="connections.php">' . h(lang('Connected accounts')) . '</a></div>
                                        </div>';
        }

    } elseif ($ga_state['property'] === '') {

        $ga_offered = pg_ga_properties($ga_connection['id']);
        $ga_options = '';

        foreach ($ga_offered['properties'] as $ga_id => $ga_name) {
            $ga_options .= '<option value="' . h($ga_id) . '">' . h($ga_name) . '</option>';
        }

        if ($ga_options !== '') {
            $ga_body = '
                                        <div class="pg-f-md">
                                            <label for="ga_property" class="form-label">' . lang('Google Analytics property') . '</label>
                                            <select name="ga_property" id="ga_property" class="form-control"><option value="">' . lang('Choose') . '</option>' . $ga_options . '</select>
                                            <div class="form-text">' . h(lang(array('string' => 'Connected as {var:1}.', 'vars' => $ga_connection['label']))) . '</div>
                                        </div>';
        } else {
            $ga_body = '
                                        <div class="col-12">
                                            <div class="form-text fg-warning">' . h(($ga_offered['error'] !== '')
                                                ? lang(array('string' => 'The Google Analytics properties could not be read: {var:1}', 'vars' => $ga_offered['error']))
                                                : lang('The connected Google account has no Google Analytics 4 property.')) . '</div>
                                        </div>';
        }

    } else {

        if ($ga_state['fetch_error'] !== '') {
            $ga_read = '<span class="fg-warning">' . h(lang(array('string' => 'The Google Analytics figures could not be read: {var:1}', 'vars' => $ga_state['fetch_error']))) . '</span>';
        } elseif ($ga_state['fetched_at'] > 0) {
            $ga_read = h(lang(array('string' => 'The figures were read {var:1}; they are read again every few hours.', 'vars' => get_relative_time(array('timestamp' => $ga_state['fetched_at'], 'format' => 'plain_text')))));
        } else {
            $ga_read = h(lang('The figures have not been read yet.'));
        }

        $ga_body = '
                                        <div class="col-12">
                                            <div class="form-text">' . h(lang('Google Analytics property')) . ': ' . h($ga_state['property_name']) . '</div>
                                            <div class="form-text">' . $ga_read . '</div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="ga_refresh" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Read the figures now') . '</button>
                                            <button type="submit" name="ga_unlink" value="1" class="btn btn-sm btn-outline theme-warning" onclick="return confirm(' . h(json_encode(lang('Stop showing the figures of this Google Analytics property?'))) . ')"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . lang('Stop using this property') . '</button>
                                        </div>';
    }

    $google_analytics_figures = '
                                        <div class="col-12">
                                            <h6 class="mb-1">' . lang('Google Analytics figures') . '</h6>
                                            <div class="form-text">' . lang('Shows the visitors, sessions and views Google Analytics counted on the dashboard.') . '</div>
                                        </div>' . $ga_body . '
                                        <input type="hidden" name="ga_card" value="1"/>';
}
