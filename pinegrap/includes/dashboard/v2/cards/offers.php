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
 * The coupon's figure for an offer's first action: the percentage or the
 * amount it takes off, free shipping as the currency's zero. Empty for an
 * action that gives no discount.
 *
 * @param array $action an offer_actions row
 * @return string
 */
function pg_dashboard_v2_offer_off($action)
{
    $prefix = array(
        'discount order'    => 'discount_order_',
        'discount product'  => 'discount_product_',
        'add product'       => 'add_product_discount_',
        'discount shipping' => 'discount_shipping_',
    );
    $type = (string) ($action['type'] ?? '');

    if (!isset($prefix[$type])) {
        return '';
    }

    $percent = (int) ($action[$prefix[$type] . 'percentage'] ?? 0);
    $amount = (int) ($action[$prefix[$type] . 'amount'] ?? 0);

    if ($type === 'discount shipping') {
        if ($percent >= 100) {
            return defined('BASE_CURRENCY_SYMBOL') ? html_entity_decode((string) BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '0' : '0';
        }
        $amount = 0;
    }

    if ($percent > 0) {
        return lang(array('string' => '{var:1}%', 'vars' => pg_format_number($percent, 0)));
    }

    return ($amount > 0) ? pg_dashboard_v2_money($amount) : '';
}

/**
 * The coupon figures of the offers, by offer id, from each one's first
 * action in one query.
 *
 * @param array $ids offer ids
 * @return array
 */
function pg_dashboard_v2_offer_offs($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

    if (!$ids) {
        return array();
    }

    $offs = array();
    $rows = db_items(
        "SELECT offers_offer_actions_xref.offer_id, offer_actions.*
         FROM offers_offer_actions_xref
         INNER JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id
         WHERE offers_offer_actions_xref.offer_id IN ('" . implode("', '", $ids) . "')
         ORDER BY offer_actions.id");

    foreach ((array) $rows as $row) {
        $id = (int) $row['offer_id'];
        if (!isset($offs[$id])) {
            $offs[$id] = pg_dashboard_v2_offer_off($row);
        }
    }

    return $offs;
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
                'off'  => (string) ($lists['offs'][(int) $offer['id']] ?? ''),
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
    $lists = pg_dashboard_offer_lists($today, date('Y-m-d', strtotime('+7 days')));
    $lists['offs'] = pg_dashboard_v2_offer_offs(array_merge(array_column($lists['expiring'], 'id'), array_column($lists['active'], 'id')));
    $rows = pg_dashboard_v2_offer_rows($lists, $today);

    return array('status' => 'success', 'data' => array(
        'rows'  => $rows,
        'empty' => empty($rows) ? lang('No offers right now.') : '',
        'url'   => 'view_offers.php',
    ));
}
