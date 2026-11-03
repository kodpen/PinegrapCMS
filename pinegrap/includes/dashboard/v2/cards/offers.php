<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Offers card: the running offers, the ones ending soonest first, each
 * with its code, its sentence and the days left, from the lists dashboard
 * widget 15 draws (pg_dashboard_offer_lists()).
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
 * The running offers as card rows, at most $limit: those ending within a
 * week first (three days or fewer in the bad tone, the rest warn), then the
 * others (good).
 *
 * @param array  $lists pg_dashboard_offer_lists()
 * @param string $today Y-m-d
 * @param int    $limit
 * @return array[] code, note, days, tone, url
 */
function pg_dashboard_v2_offer_rows($lists, $today, $limit = 5)
{
    $rows = array();

    foreach (array('expiring', 'active') as $kind) {
        foreach ((array) ($lists[$kind] ?? array()) as $offer) {
            if (count($rows) >= $limit) {
                break 2;
            }

            $left = _pg_offer_days_left($offer['end_date'], $today);

            if ($left === null) {
                $days = lang('Open-ended');
                $tone = 'good';
            } else {
                $days = lang(array('string' => '{var:1} day{suffix:1} left', 'vars' => pg_format_number($left, 0), 'suffix' => ($left == 1) ? '' : 's'));
                $tone = ($kind === 'expiring') ? (($left <= 3) ? 'bad' : 'warn') : 'good';
            }

            $rows[] = array(
                'code' => ((string) $offer['code'] !== '') ? (string) $offer['code'] : lang('(no code)'),
                'note' => (string) ($lists['sentences'][(int) $offer['id']] ?? ''),
                'days' => $days,
                'tone' => $tone,
                'url'  => 'edit_offer.php?id=' . (int) $offer['id'],
            );
        }
    }

    return $rows;
}

function pg_dashboard_v2_card_offers($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_15.php');
    require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');

    $today = date('Y-m-d');
    $rows = pg_dashboard_v2_offer_rows(pg_dashboard_offer_lists($today, date('Y-m-d', strtotime('+7 days'))), $today);

    return array('status' => 'success', 'data' => array(
        'rows'  => $rows,
        'empty' => empty($rows) ? lang('No offers right now.') : '',
        'url'   => 'view_offers.php',
    ));
}
