<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "First week" card: for seven days from the go-live time ("Go live" on
 * the set-up card), whether the first order or form entry came, visitors
 * came, the first e-mail went out and whether an operations alert was sent;
 * each row Waiting, Seen or Problem (pg_launch_journey_week_card()). The
 * card ends by itself after the week: its gate closes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

/**
 * What happened since going live. A source that cannot be read leaves its
 * key out, and its row says "Could not be checked".
 *
 * @param array $user
 * @param int   $live_at
 * @return array the facts of pg_launch_journey_week_card()
 */
function pg_dashboard_v2_firstweek_facts($user, $live_at)
{
    $live_at = (int) $live_at;
    $shop = defined('ECOMMERCE') && (ECOMMERCE === true);
    $facts = array('shop' => $shop);

    $facts['entries'] = pg_dashboard_v2_chip_count('forms', $user, $live_at);
    $facts['visits'] = pg_dashboard_v2_chip_count('visits', $user, $live_at);
    if ($shop) {
        $facts['orders'] = pg_dashboard_v2_chip_count('orders', $user, $live_at);
    }

    // The mail queue's own rows; a mail sent at once (no scheduled tasks)
    // leaves none, so the row then keeps waiting.
    $mail = db_item(
        "SELECT
            SUM(status = 'sent' AND sent_at >= " . $live_at . ") AS sent,
            SUM(status = 'failed' AND created_at >= " . $live_at . ") AS failed
        FROM mail_outbox
        WHERE created_at >= " . $live_at . " OR sent_at >= " . $live_at);
    if (is_array($mail)) {
        $facts['mail_sent'] = (int) $mail['sent'];
        $facts['mail_failed'] = (int) $mail['failed'];
    }

    // The operations alerts sent since going live, from their state file.
    if (function_exists('pg_ops_alert_state')) {
        $state = pg_ops_alert_state();
        if (is_array($state)) {
            $facts['alerts'] = count(array_filter($state['sent'], function ($stamp) use ($live_at) {
                return (int) $stamp >= $live_at;
            }));
        }
    }

    return $facts;
}

/**
 * @param array $user
 * @param int   $period
 * @param int   $compare
 * @return array
 */
function pg_dashboard_v2_card_firstweek($user, $period, $compare)
{
    $live_at = pg_launch_journey_stored()['live_at'];
    $week = pg_launch_journey_first_week($live_at, time());

    if (!pg_dashboard_v2_firstweek_gate($user['role'], $week['open'])) {
        return array('status' => 'denied', 'data' => array());
    }

    $card = pg_launch_journey_week_card($week, pg_dashboard_v2_firstweek_facts($user, $live_at));

    return array('status' => 'success', 'data' => array(
        'head'   => $card['head'],
        'rows'   => $card['rows'],
    ));
}
