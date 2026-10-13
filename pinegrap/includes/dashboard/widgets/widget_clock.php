<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget "clock": the site's current time for the panel clock.
 *
 * The dashboard draws the minutes itself and uses this answer to stay right:
 * 'epoch' and 'offset' (the site's distance from UTC, in seconds) correct the
 * browser's clock, and 'date' is the long date line, written in the site's
 * language, for when the day turns over.
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
    $now = time();

    return array(
        'status' => 'success',
        'data' => get_absolute_time(array(
            'timestamp' => $now,
            'type' => 'time',
            'timezone_type' => 'site'
        )),
        'date' => get_absolute_time(array(
            'timestamp' => $now,
            'type' => 'date',
            'size' => 'long',
            'timezone_type' => 'site'
        )),
        'epoch' => $now,
        'offset' => (int) date('Z', $now),
        'message' => lang('Data Received successfully.') . $_SESSION['sessionusername'],
    );
}
