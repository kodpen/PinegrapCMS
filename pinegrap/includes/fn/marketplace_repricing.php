<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Repricing view of a marketplace account (2026.11.0): each listed
 * product with the price it goes out at, its floor and its last buybox read,
 * the four figure cards (winning, losing, at the floor, no cost) and the
 * filter behind each, the robot's log and the names of its reasons.
 *
 * The functions above the "readers" line are pure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_pricing_settings.php');

/** marketplace_price_log.reason: Win went one step under the seller holding the buybox. */
define('MP_REPRICING_REASON_LOST', 'lost');

/** marketplace_price_log.reason: the price stopped at the floor. */
define('MP_REPRICING_REASON_FLOOR', 'floor');

/** marketplace_price_log.reason: Grow while winning went one step up. */
define('MP_REPRICING_REASON_RAISED', 'raised');

/** marketplace_price_log.reason: Grow while winning stepped back after losing the buybox. */
define('MP_REPRICING_REASON_BACK', 'back');

/** The rank packed in marketplace_price_state.buybox_ours (its low seven bits). */
define('MP_REPRICING_RANK_MASK', 127);

/** The other-sellers bit of marketplace_price_state.buybox_ours. */
define('MP_REPRICING_OTHERS_BIT', 128);

/**
 * The log's reasons and their names, in the order the robot meets them.
 *
 * @return array code => name
 */
function mp_repricing_reasons()
{
    return array(
        MP_REPRICING_REASON_LOST => lang('Buybox lost'),
        MP_REPRICING_REASON_FLOOR => lang('Floor reached'),
        MP_REPRICING_REASON_RAISED => lang('Raised while winning'),
        MP_REPRICING_REASON_BACK => lang('Stepped back'),
    );
}

/**
 * A reason's name; a code this version does not know is shown as stored.
 *
 * @param string $code
 * @return string
 */
function mp_repricing_reason_label($code)
{
    $reasons = mp_repricing_reasons();

    return isset($reasons[(string) $code]) ? $reasons[(string) $code] : (string) $code;
}

/**
 * The pill tone of a reason: a lost buybox and the floor ask for a look.
 *
 * @param string $code
 * @return string  a look A tone name
 */
function mp_repricing_reason_tone($code)
{
    $tones = array(
        MP_REPRICING_REASON_LOST => 'amber',
        MP_REPRICING_REASON_FLOOR => 'rose',
        MP_REPRICING_REASON_RAISED => 'teal',
        MP_REPRICING_REASON_BACK => 'blue',
    );

    return isset($tones[(string) $code]) ? $tones[(string) $code] : 'gray';
}

/**
 * A listing's buybox and floor state.
 *
 * Winning is rank 1 of the last read, losing a rank over 1; a listing never
 * read is neither. At the floor: the price is at or under the lowest price
 * the rounding can reach from the floor (mp_pricing_round_up()). No floor
 * (no cost known and none typed) is "No cost".
 *
 * @param array $listing  price, floor (int|null), rounding, buybox_ours
 * @return array ['rank' => int, 'others' => bool, 'winning' => bool, 'losing' => bool, 'at_floor' => bool, 'no_cost' => bool]
 */
function mp_repricing_state($listing)
{
    $ours = (int) ($listing['buybox_ours'] ?? 0);
    $rank = $ours & MP_REPRICING_RANK_MASK;
    $floor = $listing['floor'] ?? null;
    $lowest = ($floor === null) ? null : mp_pricing_round_up((int) $floor, (string) ($listing['rounding'] ?? 'none'));

    return array(
        'rank' => $rank,
        'others' => ($ours & MP_REPRICING_OTHERS_BIT) !== 0,
        'winning' => $rank === 1,
        'losing' => $rank > 1,
        'at_floor' => ($lowest !== null) && ((int) ($listing['price'] ?? 0) <= $lowest),
        'no_cost' => $floor === null,
    );
}

/**
 * The figure cards and the filter keys behind them.
 *
 * @return array key => name
 */
function mp_repricing_cards()
{
    return array(
        'winning' => lang('Winning'),
        'losing' => lang('Losing'),
        'floor' => lang('At the floor'),
        'no_cost' => lang('No cost'),
    );
}

/**
 * Whether a listing belongs under a card; 'all' and an unknown key hold all.
 *
 * @param array  $state  mp_repricing_state()
 * @param string $card
 * @return bool
 */
function mp_repricing_in_card($state, $card)
{
    switch ((string) $card) {
        case 'winning':
            return $state['winning'];
        case 'losing':
            return $state['losing'];
        case 'floor':
            return $state['at_floor'];
        case 'no_cost':
            return $state['no_cost'];
    }

    return true;
}

/**
 * How many listings each card holds, and all of them.
 *
 * @param array $listings
 * @return array ['all', 'winning', 'losing', 'floor', 'no_cost' => int]
 */
function mp_repricing_counts($listings)
{
    $counts = array('all' => 0, 'winning' => 0, 'losing' => 0, 'floor' => 0, 'no_cost' => 0);

    foreach ((array) $listings as $listing) {
        $state = mp_repricing_state($listing);
        $counts['all']++;

        foreach (array_keys(mp_repricing_cards()) as $card) {
            if (mp_repricing_in_card($state, $card)) {
                $counts[$card]++;
            }
        }
    }

    return $counts;
}

/**
 * The listings under a card whose title or code holds the search, in order.
 *
 * @param array  $listings
 * @param string $card
 * @param string $search
 * @return array
 */
function mp_repricing_filter($listings, $card, $search)
{
    $search = mb_strtolower(trim((string) $search));
    $kept = array();

    foreach ((array) $listings as $listing) {
        if (!mp_repricing_in_card(mp_repricing_state($listing), $card)) {
            continue;
        }

        if (($search !== '') && (mb_strpos(mb_strtolower((string) ($listing['title'] ?? '')), $search) === false)
            && (mb_strpos(mb_strtolower((string) ($listing['code'] ?? '')), $search) === false)) {
            continue;
        }

        $kept[] = $listing;
    }

    return $kept;
}

/**
 * Whether the account's robot only writes the log: Try first is on, or the
 * account has no Pricing row yet (the Pricing tab starts with it on).
 *
 * @param array $rules  mp_pricing_account_rules()
 * @return bool
 */
function mp_repricing_try_first($rules)
{
    $own = mp_pricing_account_row($rules);

    return !is_array($own) || ((int) $own['robot'] !== MP_PRICING_ROBOT_ON);
}

/**
 * The buybox rank in words.
 *
 * @param int $rank  0 when never read
 * @return string
 */
function mp_repricing_rank_text($rank)
{
    $rank = (int) $rank;

    if ($rank === 1) {
        return lang('Ours');
    }

    if ($rank > 1) {
        return lang(array('string' => 'Rank {var:1}', 'vars' => array($rank)));
    }

    return lang('Not read yet');
}

/* ---------------------------------------------------------------- readers */

/**
 * Every matched listing of an account with what the view shows: the price
 * it goes out at (its own price, else the rules', else the site price), the
 * floor, the rounding the floor is reached with, the strategy, the last
 * buybox read and the last change in the log. Read in four statements
 * whatever the number of listings: the rules once, the listings, their
 * groups when a category rule exists, and the last log rows.
 *
 * @param array $account  marketplace_accounts row
 * @return array  list of listings, by title
 */
function mp_repricing_listings($account)
{
    $account_id = (int) $account['id'];

    if (($account_id <= 0) || !mp_pricing_ready()) {
        return array();
    }

    $cost = pg_schema_has('erp_product_costs', 'avg_cost');
    $commission = pg_schema_has('marketplace_product_map', 'commission');

    $rows = (array) db_items("SELECT products.id, products.name, products.short_description, products.title,
            products.price, products.taxable, products.tax_rate, products.brand,
            map.remote_code, map.status AS map_status,
            " . ($cost ? "COALESCE(costs.avg_cost, 0)" : "0") . " AS cost,
            " . ($commission ? "map.commission" : "NULL") . " AS commission,
            COALESCE(state.own_floor, 0) AS own_floor, COALESCE(state.own_price, 0) AS own_price,
            COALESCE(state.strategy, 0) AS strategy, COALESCE(state.buybox_price, 0) AS buybox_price,
            COALESCE(state.buybox_ours, 0) AS buybox_ours, COALESCE(state.buybox_at, 0) AS buybox_at
        FROM marketplace_product_map AS map
        INNER JOIN products ON products.id = map.product_id
        " . ($cost ? "LEFT JOIN erp_product_costs AS costs ON costs.product_id = map.product_id" : "") . "
        LEFT JOIN marketplace_price_state AS state
            ON (state.account_id = map.account_id) AND (state.product_id = map.product_id)
        WHERE (map.account_id = '" . $account_id . "')" . pg_catalog_bin_live_sql('products') . "
        ORDER BY products.short_description ASC, products.name ASC, products.id ASC");

    if (!$rows) {
        return array();
    }

    $rules = mp_pricing_account_rules($account_id);
    $groups = mp_repricing_groups($account_id, $rules);
    $parents = $groups ? mp_pricing_group_parents() : array();
    $changes = mp_repricing_last_changes($account_id);
    $listings = array();

    foreach ($rows as $row) {
        $product_id = (int) $row['id'];
        $site_price = mp_product_price($row);
        $vat_rate = mp_product_vat_rate($row);
        $vat_rate = ($vat_rate === null) ? mp_pricing_tax_off_rate($row) : $vat_rate;
        $figures = array(
            'cost' => (int) $row['cost'],
            'commission' => ($row['commission'] !== null) ? (float) $row['commission'] : null,
            'own_floor' => (int) $row['own_floor'],
            'own_price' => (int) $row['own_price'],
            'strategy' => (int) $row['strategy'],
        );
        $floor = $rules ? mp_pricing_product_floor($account_id, $rules, $figures, $vat_rate) : mp_pricing_floor_final(null, $figures['own_floor']);
        $price = $site_price;
        $rounding = 'none';
        $by = 'site';

        if ($figures['own_price'] > 0) {
            $price = $figures['own_price'];
            $by = 'own';
        } elseif ($rules) {
            $chains = isset($groups[$product_id]) ? mp_pricing_group_chains($groups[$product_id], $parents) : array();
            $rule = mp_pricing_pick_rule($rules, $chains, (string) $row['brand']);

            if ($rule !== null) {
                $price = mp_pricing_channel_price($site_price, $rule, $floor);
                $rounding = mp_pricing_rounding($rule['rounding']);
                $by = 'rule';
            }
        }

        $listings[] = array(
            'product_id' => $product_id,
            'title' => mp_remote_products_shop_name($row),
            'code' => (string) $row['remote_code'],
            'price' => (int) $price,
            'by' => $by,
            'floor' => $floor,
            'rounding' => $rounding,
            'strategy' => $figures['strategy'],
            'buybox_price' => (int) $row['buybox_price'],
            'buybox_ours' => (int) $row['buybox_ours'],
            'buybox_at' => (int) $row['buybox_at'],
            'change' => isset($changes[$product_id]) ? $changes[$product_id] : null,
        );
    }

    return $listings;
}

/**
 * The groups of an account's listed products, read only when the account has
 * a category rule.
 *
 * @param int   $account_id
 * @param array $rules  mp_pricing_account_rules()
 * @return array  product id => list of group ids
 */
function mp_repricing_groups($account_id, $rules)
{
    $needed = false;

    foreach ((array) $rules as $rule) {
        if ((int) $rule['scope'] === MP_PRICING_SCOPE_CATEGORY) {
            $needed = true;
            break;
        }
    }

    if (!$needed) {
        return array();
    }

    $groups = array();

    foreach ((array) db_items("SELECT xref.product, xref.product_group
        FROM products_groups_xref AS xref
        INNER JOIN marketplace_product_map AS map ON map.product_id = xref.product
        WHERE map.account_id = '" . (int) $account_id . "'") as $row) {
        $groups[(int) $row['product']][] = (int) $row['product_group'];
    }

    return $groups;
}

/**
 * The newest log row of each listing of an account.
 *
 * @param int $account_id
 * @return array  product id => ['old_price', 'new_price', 'reason', 'sent', 'at']
 */
function mp_repricing_last_changes($account_id)
{
    $changes = array();

    foreach ((array) db_items("SELECT log.product_id, log.old_price, log.new_price, log.reason, log.sent, log.at
        FROM marketplace_price_log AS log
        INNER JOIN (SELECT MAX(id) AS id FROM marketplace_price_log
            WHERE account_id = '" . (int) $account_id . "' GROUP BY product_id) AS newest ON newest.id = log.id") as $row) {
        $changes[(int) $row['product_id']] = array(
            'old_price' => (int) $row['old_price'],
            'new_price' => (int) $row['new_price'],
            'reason' => (string) $row['reason'],
            'sent' => (int) $row['sent'] === 1,
            'at' => (int) $row['at'],
        );
    }

    return $changes;
}

/**
 * The newest change of one listing, or null.
 *
 * @param int $account_id
 * @param int $product_id
 * @return array|null  as mp_repricing_last_changes()
 */
function mp_repricing_last_change($account_id, $product_id)
{
    if (!mp_pricing_ready()) {
        return null;
    }

    $row = db_item("SELECT old_price, new_price, reason, sent, at FROM marketplace_price_log
        WHERE (account_id = '" . (int) $account_id . "') AND (product_id = '" . (int) $product_id . "')
        ORDER BY at DESC, id DESC LIMIT 1");

    return is_array($row) ? array(
        'old_price' => (int) $row['old_price'],
        'new_price' => (int) $row['new_price'],
        'reason' => (string) $row['reason'],
        'sent' => (int) $row['sent'] === 1,
        'at' => (int) $row['at'],
    ) : null;
}

/**
 * One listing's last buybox read, or zeros.
 *
 * @param int $account_id
 * @param int $product_id
 * @return array ['buybox_price' => int, 'buybox_ours' => int, 'buybox_at' => int]
 */
function mp_repricing_buybox($account_id, $product_id)
{
    $row = mp_pricing_ready() ? db_item("SELECT buybox_price, buybox_ours, buybox_at FROM marketplace_price_state
        WHERE (account_id = '" . (int) $account_id . "') AND (product_id = '" . (int) $product_id . "')") : null;

    return array(
        'buybox_price' => is_array($row) ? (int) $row['buybox_price'] : 0,
        'buybox_ours' => is_array($row) ? (int) $row['buybox_ours'] : 0,
        'buybox_at' => is_array($row) ? (int) $row['buybox_at'] : 0,
    );
}

/**
 * One page of an account's log, newest first, with each product's name.
 *
 * @param int $account_id
 * @param int $page      from 1
 * @param int $per_page
 * @return array ['rows' => array, 'total' => int]
 */
function mp_repricing_log_page($account_id, $page, $per_page)
{
    if (!mp_pricing_ready()) {
        return array('rows' => array(), 'total' => 0);
    }

    $where = "WHERE log.account_id = '" . (int) $account_id . "'";
    $total = (int) db_value("SELECT COUNT(*) FROM marketplace_price_log AS log " . $where);
    $rows = (array) db_items("SELECT log.id, log.product_id, log.old_price, log.new_price, log.reason, log.sent, log.at,
            products.name, products.short_description, products.title
        FROM marketplace_price_log AS log
        LEFT JOIN products ON products.id = log.product_id
        " . $where . "
        ORDER BY log.at DESC, log.id DESC
        LIMIT " . (max(0, (int) $page - 1) * (int) $per_page) . ", " . (int) $per_page);

    return array('rows' => $rows, 'total' => $total);
}
