<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's greeting card: the date and time line in the person's
 * time zone and the greeting by the hour with their name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The hour of the day where the person is: their own time zone when they set
 * one, else the site's (the zone get_absolute_time() draws the line in).
 *
 * @param int $timestamp
 * @return int 0-23
 */
function pg_dashboard_v2_local_hour($timestamp)
{
    $date = new DateTime('@' . (int) $timestamp);
    $zone = pg_time_uses_user_zone(array()) ? (string) USER_TIMEZONE : '';
    $date->setTimezone(pg_timezone_object($zone));

    return (int) $date->format('G');
}

/**
 * @param array $user the row validate_user() returned
 * @return string HTML
 */
function pg_dashboard_v2_greeting($user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/home.php');

    $now = time();

    $when = get_absolute_time(array('timestamp' => $now, 'type' => 'date', 'size' => 'long'))
        . ' · ' . get_absolute_time(array('timestamp' => $now, 'type' => 'time'));

    return '
        <section class="card pg-dash-card pg-dash-greeting" aria-labelledby="pg_dash_greeting_title">
            <p class="pg-dash-greeting-when">' . h($when) . '</p>
            <h2 class="pg-dash-greeting-title" id="pg_dash_greeting_title">' . h(ws_home_greeting(pg_dashboard_v2_local_hour($now))) . ', <span class="pg-dash-name">' . h((string) $user['username']) . '</span></h2>
        </section>';
}
