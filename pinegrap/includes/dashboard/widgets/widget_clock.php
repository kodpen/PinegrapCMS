<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget "clock": the site's current time for the panel clock.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_clock($request, $user)
{
    $output_data = '';
    //return success json output
    $response = array(
        'status' => 'success',
        'data' => get_absolute_time(array(
            'timestamp' => time(),
            'type' => 'time',
            'timezone_type' => 'site'
        )),
        'message' => lang('Data Received successfully.') . $_SESSION['sessionusername'],
    );
    return $response;
}
