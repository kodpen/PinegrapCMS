<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Recovered carts card: the revenue the messages sent to left carts
 * brought back in the period, how many carts that was against how many were
 * reached, the change against the earlier period, and a row per channel.
 * The figures are pg_cart_recovery_period(); See all opens the section on
 * the Visitors overview.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/shop.php');

/**
 * The card's rows, a channel each: reached and came back under its name,
 * the carts recovered and their revenue at the end. Pure.
 *
 * @param array $channels pg_cart_recovery_figures()['channels']
 * @return array[] text, meta, money, note
 */
function pg_dashboard_v2_recovery_rows(array $channels)
{
    $names = array(
        'email'    => lang('E-mail'),
        'whatsapp' => lang('WhatsApp'),
        'sms'      => lang('SMS'),
        'page'     => lang('Offer on a page'),
    );

    $rows = array();

    foreach ($channels as $channel => $row) {
        $rows[] = array(
            'text'  => isset($names[$channel]) ? $names[$channel] : (string) $channel,
            'meta'  => lang(array('string' => '{var:1} reached · {var:2} came back', 'vars' => array(pg_format_number($row['reached'], 0), pg_format_number($row['returned'], 0)))),
            'money' => pg_dashboard_v2_money($row['revenue']),
            'note'  => lang(array('string' => '{var:1} recovered', 'vars' => array(pg_format_number($row['recovered'], 0)))),
        );
    }

    return $rows;
}

function pg_dashboard_v2_card_recovery($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop_summary', $user)) {
        return array('status' => 'denied');
    }

    $url = pg_dashboard_v2_gate('visitors', $user) ? 'visitors.php#cart_recovery' : '';

    if (!pg_cart_recovery_ready()) {
        return array('status' => 'success', 'data' => array('empty' => lang('Recovered carts are counted once the software update has run.'), 'rows' => array(), 'url' => ''));
    }

    $windows = pg_dashboard_v2_shop_windows($period, $compare, (int) strtotime(date('Y-m-d')));
    $now = pg_cart_recovery_period($windows['now'][0], $windows['now'][1]);
    $before = ($compare !== 2) ? pg_cart_recovery_period($windows['before'][0], $windows['before'][1]) : null;

    $change = ($before !== null) ? pg_dashboard_v2_change($now['revenue'], $before['revenue']) : null;

    $period_names = array(0 => lang('Today'), 1 => lang('Last 7 days'), 2 => lang('Last 30 days'));
    $period_name = isset($period_names[$period]) ? $period_names[$period] : $period_names[2];

    return array('status' => 'success', 'data' => array(
        'figure' => pg_dashboard_v2_money($now['revenue']),
        'sub'    => $period_name . ' · ' . lang(array('string' => '{var:1} of {var:2} reached carts recovered', 'vars' => array(pg_format_number($now['recovered'], 0), pg_format_number($now['reached'], 0)))),
        'change' => ($change === null) ? '' : $change['text'],
        'tone'   => ($change === null) ? '' : $change['tone'],
        'rows'   => pg_dashboard_v2_recovery_rows($now['channels']),
        'empty'  => ($now['left'] < 1) ? lang('No cart was left in this period.') : '',
        'url'    => $url,
    ));
}
