<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New dashboard - the greeting card at the top of the page.
 *
 * The date and time line and "Good evening, <name>". The "since your last
 * sign-in" chips, the shortcuts and the four tiles join it in their own step.
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
 * The greeting for an hour of the day: the Workspace home's rule (night
 * before 05:00 and from 23:00, morning to noon, afternoon to 18:00, then
 * evening), with the same texts.
 *
 * @param int $hour 0-23
 * @return string
 */
function pg_dashboard_v2_greeting($hour)
{
    $hour = (int) $hour;

    if (($hour < 5) || ($hour >= 23)) {
        return lang('Good night');
    }

    if ($hour < 12) {
        return lang('Good morning');
    }

    return ($hour < 18) ? lang('Good afternoon') : lang('Good evening');
}

/**
 * @param array $user
 * @param array|null $row
 * @return string
 */
function pg_dashboard_v2_greeting_card($user, $row)
{
    $now = time();

    $when = get_absolute_time(array(
        'timestamp' => $now,
        'type' => 'date',
        'size' => 'long',
        'format' => 'plain_text',
    )) . ' · ' . get_absolute_time(array(
        'timestamp' => $now,
        'type' => 'time',
        'format' => 'plain_text',
    ));

    // The hour as the current dashboard's greeting reads it (brief.php):
    // the formatted time above may be on the twelve-hour clock.
    $hour = (int) date('G', $now);

    $name = (string) ($_SESSION['sessionusername'] ?? ($user['username'] ?? ''));

    return '
    <section class="pg-dash-card pg-dash-greeting" aria-labelledby="pg_dash_greet">
        <p class="pg-dash-when">' . h($when) . '</p>
        <h2 class="pg-dash-greet" id="pg_dash_greet">' . h(pg_dashboard_v2_greeting($hour)) . ', <span class="pg-dash-greet-name">' . h($name) . '</span></h2>
    </section>';
}
