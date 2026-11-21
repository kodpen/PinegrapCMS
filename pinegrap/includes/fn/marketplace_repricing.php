<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Repricing view of a marketplace account (2026.11.0): each listed
 * product with the price it goes out at, its floor and its last buybox read,
 * the four figure cards (winning, losing, at the floor, no cost) and the
 * filter behind each, the robot's price log and the names of its reasons;
 * both lists as list table driver sources read in keyset batches.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_repricer.php');

/** The rank packed in marketplace_price_state.buybox_ours (its low seven bits). */
define('MP_REPRICING_RANK_MASK', 127);

/** The other-sellers bit of marketplace_price_state.buybox_ours. */
define('MP_REPRICING_OTHERS_BIT', 128);

/**
 * The price log's reasons and their names. The robot writes these four
 * strings into marketplace_price_log.reason: lost (Win went one step under
 * the seller holding the buybox), floor (the price stopped at the floor),
 * raised (Grow while winning went one step up), back (Grow while winning
 * stepped back after losing the buybox).
 *
 * @return array code => name
 */
function mp_repricing_reasons()
{
    return array(
        'lost' => lang('Buybox lost'),
        'floor' => lang('Floor reached'),
        'raised' => lang('Raised while winning'),
        'back' => lang('Stepped back'),
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
    $tones = array('lost' => 'amber', 'floor' => 'rose', 'raised' => 'teal', 'back' => 'blue');

    return isset($tones[(string) $code]) ? $tones[(string) $code] : 'gray';
}

/**
 * A listing's buybox and floor state.
 *
 * Winning is rank 1 of the last read, losing a rank over 1; a listing never
 * read is neither. At the floor: the price going out is not above the floor
 * (an own price typed under it counts too). No floor (no cost known and
 * none typed) is "No cost".
 *
 * @param array $listing  price, floor (int|null), buybox_ours
 * @return array ['rank' => int, 'others' => bool, 'winning' => bool, 'losing' => bool, 'at_floor' => bool, 'no_cost' => bool]
 */
function mp_repricing_state($listing)
{
    $ours = (int) ($listing['buybox_ours'] ?? 0);
    $rank = $ours & MP_REPRICING_RANK_MASK;
    $floor = $listing['floor'] ?? null;

    return array(
        'rank' => $rank,
        'others' => ($ours & MP_REPRICING_OTHERS_BIT) !== 0,
        'winning' => $rank === 1,
        'losing' => $rank > 1,
        'at_floor' => ($floor !== null) && ((int) ($listing['price'] ?? 0) <= (int) $floor),
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
 * The buybox rank as a product's row shows it: "Winning" as a green pill,
 * "Buybox rank: N" otherwise, "Not read yet" as a grey pill.
 *
 * @param int $rank  0 when never read
 * @return string  HTML
 */
function mp_repricing_rank_html($rank)
{
    $rank = (int) $rank;

    if ($rank === 1) {
        return '<span class="pg-pill pg-tone-teal">' . h(lang('Winning')) . '</span>';
    }

    if ($rank > 1) {
        return h(lang(array('string' => 'Buybox rank: {var:1}', 'vars' => array($rank))));
    }

    return '<span class="pg-pill pg-tone-gray">' . h(lang('Not read yet')) . '</span>';
}

/**
 * The rank in the products list: the number, or a dash before any read.
 *
 * @param int $rank
 * @return string  HTML
 */
function mp_repricing_rank_cell($rank)
{
    $rank = (int) $rank;

    return ($rank > 0) ? '<span class="pg-tabular">' . $rank . '</span>' : '&mdash;';
}

/**
 * The view the segment row asked for: a card's key, 'log' or 'all'.
 *
 * @param mixed $value
 * @return string
 */
function mp_repricing_view_key($value)
{
    $value = is_string($value) ? $value : '';

    return (($value === 'log') || isset(mp_repricing_cards()[$value])) ? $value : 'all';
}

/**
 * A card's condition on a listing in the products list's SQL: winning and
 * losing by the stored rank; at the floor and no cost by the product ids
 * worked out in PHP (the floor comes from cost, rates and rules).
 *
 * @param string $view
 * @param array  $ids   the listings under the card, for floor and no_cost
 * @return string
 */
function mp_repricing_view_condition($view, $ids)
{
    if ($view === 'winning') {
        return '(COALESCE(state.buybox_ours, 0) & ' . MP_REPRICING_RANK_MASK . ') = 1';
    }

    if ($view === 'losing') {
        return '(COALESCE(state.buybox_ours, 0) & ' . MP_REPRICING_RANK_MASK . ') > 1';
    }

    if (($view === 'floor') || ($view === 'no_cost')) {
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));

        return $ids ? 'map.product_id IN (' . implode(', ', $ids) . ')' : '1 = 0';
    }

    return '1 = 1';
}

/* ---------------------------------------------------------------- readers */

/**
 * A price of the view in the base currency, or a dash for none.
 *
 * @param int $amount  minor units
 * @return string  HTML
 */
function mp_repricing_money($amount)
{
    return ((int) $amount > 0) ? '<span class="pg-tabular">' . pg_format_money($amount / 100, BASE_CURRENCY_SYMBOL) . '</span>' : '&mdash;';
}

/**
 * A change of the price log: old and new price, the reason as a pill, and
 * when.
 *
 * @param array $change  old_price, new_price, reason, sent, at
 * @return string  HTML
 */
function mp_repricing_change_html($change)
{
    return '<span class="pg-tabular">' . pg_format_money($change['old_price'] / 100, BASE_CURRENCY_SYMBOL) . ' → '
        . pg_format_money($change['new_price'] / 100, BASE_CURRENCY_SYMBOL) . '</span> '
        . '<span class="pg-pill pg-tone-' . mp_repricing_reason_tone($change['reason']) . '">' . h(mp_repricing_reason_label($change['reason'])) . '</span> '
        . '<span class="pg-text-meta">' . get_relative_time(array('timestamp' => (int) $change['at'])) . '</span>';
}

/**
 * The marketplace layer the readers lean on: the account, the site price,
 * the product's name.
 */
function mp_repricing_load()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/listing_articles.php');
}

/**
 * The matched listings of an account, or of some of its products, with what
 * the view shows: the price it goes out at (its own price, else the rules',
 * else the site price), the floor, the strategy, the last buybox read and
 * the last change in the log. Read in four statements whatever the number
 * of listings: the rules once, the listings, their groups when a category
 * rule exists, and the last log rows. It works the price out as
 * mp_pricing_product_price() does, the robot's price included, without its
 * query per product.
 *
 * @param array      $account      marketplace_accounts row
 * @param array|null $product_ids  null for every listing
 * @return array  product id => listing, by title
 */
function mp_repricing_listings($account, $product_ids = null)
{
    $account_id = (int) $account['id'];

    if (($account_id <= 0) || !mp_pricing_ready()) {
        return array();
    }

    $only = '';

    if ($product_ids !== null) {
        $product_ids = array_values(array_unique(array_map('intval', (array) $product_ids)));

        if (!$product_ids) {
            return array();
        }

        $only = " AND (map.product_id IN (" . implode(', ', $product_ids) . "))";
    }

    $cost = pg_schema_has('erp_product_costs', 'avg_cost');
    $commission = pg_schema_has('marketplace_product_map', 'commission');

    $rows = (array) db_items("SELECT products.id, products.name, products.short_description, products.title,
            products.price, products.taxable, products.tax_rate, products.brand, map.remote_code,
            " . ($cost ? "COALESCE(costs.avg_cost, 0)" : "0") . " AS cost,
            " . ($commission ? "map.commission" : "NULL") . " AS commission,
            COALESCE(state.own_floor, 0) AS own_floor, COALESCE(state.own_price, 0) AS own_price,
            COALESCE(state.strategy, 0) AS strategy, COALESCE(state.robot_price, 0) AS robot_price,
            COALESCE(state.buybox_price, 0) AS buybox_price,
            COALESCE(state.buybox_ours, 0) AS buybox_ours, COALESCE(state.buybox_at, 0) AS buybox_at
        FROM marketplace_product_map AS map
        INNER JOIN products ON products.id = map.product_id
        " . ($cost ? "LEFT JOIN erp_product_costs AS costs ON costs.product_id = map.product_id" : "") . "
        LEFT JOIN marketplace_price_state AS state
            ON (state.account_id = map.account_id) AND (state.product_id = map.product_id)
        WHERE (map.account_id = '" . $account_id . "')" . $only . pg_catalog_bin_live_sql('products') . "
        ORDER BY products.short_description ASC, products.name ASC, products.id ASC");

    if (!$rows) {
        return array();
    }

    $rules = mp_pricing_account_rules($account_id);
    $groups = mp_repricing_groups($account_id, $rules, $product_ids);
    $parents = $groups ? mp_pricing_group_parents() : array();
    $changes = mp_repricing_last_changes($account_id, $product_ids);
    $own = mp_pricing_account_row($rules);
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
            'robot_price' => (int) $row['robot_price'],
        );
        $floor = mp_pricing_product_floor($account_id, $rules, $figures, $vat_rate);
        $price = $site_price;
        $by = 'site';

        if ($figures['own_price'] > 0) {
            $price = $figures['own_price'];
            $by = 'own';
        } elseif ($rules) {
            $chains = isset($groups[$product_id]) ? mp_pricing_group_chains($groups[$product_id], $parents) : array();
            $rule = mp_pricing_pick_rule($rules, $chains, (string) $row['brand']);

            if ($rule !== null) {
                $price = mp_pricing_channel_price($site_price, $rule, $floor);
                $by = 'rule';
                $robot = mp_repricer_send_price($own, $figures, $price, $floor);

                if ($robot !== null) {
                    $price = $robot;
                    $by = 'robot';
                }
            }
        }

        $listings[$product_id] = array(
            'product_id' => $product_id,
            'title' => mp_remote_products_shop_name($row),
            'code' => (string) $row['remote_code'],
            'price' => (int) $price,
            'by' => $by,
            'own_price' => $figures['own_price'],
            'floor' => $floor,
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
 * The product ids of an account's listings under a card, worked out in PHP.
 *
 * @param array  $listings  mp_repricing_listings()
 * @param string $card
 * @return array
 */
function mp_repricing_card_ids($listings, $card)
{
    $ids = array();

    foreach ((array) $listings as $listing) {
        if (mp_repricing_in_card(mp_repricing_state($listing), $card)) {
            $ids[] = (int) $listing['product_id'];
        }
    }

    return $ids;
}

/**
 * The groups of an account's listed products, read only when the account has
 * a category rule.
 *
 * @param int        $account_id
 * @param array      $rules        mp_pricing_account_rules()
 * @param array|null $product_ids  null for every listing
 * @return array  product id => list of group ids
 */
function mp_repricing_groups($account_id, $rules, $product_ids = null)
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
        WHERE (map.account_id = '" . (int) $account_id . "')"
        . (($product_ids !== null) ? " AND (map.product_id IN (" . implode(', ', array_map('intval', $product_ids)) . "))" : "")) as $row) {
        $groups[(int) $row['product']][] = (int) $row['product_group'];
    }

    return $groups;
}

/**
 * The newest log row of each listing of an account, or of some products.
 *
 * @param int        $account_id
 * @param array|null $product_ids  null for every listing
 * @return array  product id => ['old_price', 'new_price', 'reason', 'sent', 'at']
 */
function mp_repricing_last_changes($account_id, $product_ids = null)
{
    $changes = array();
    $only = ($product_ids !== null) ? " AND (product_id IN (" . implode(', ', array_map('intval', $product_ids)) . "))" : "";

    foreach ((array) db_items("SELECT log.product_id, log.old_price, log.new_price, log.reason, log.sent, log.at
        FROM marketplace_price_log AS log
        INNER JOIN (SELECT MAX(id) AS id FROM marketplace_price_log
            WHERE (account_id = '" . (int) $account_id . "')" . $only . " GROUP BY product_id) AS newest ON newest.id = log.id") as $row) {
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

/* ------------------------------------------------------------- the lists */

/**
 * The account and view a list's parameters name, checked: null when the
 * user may not open the Repricing view or the account is gone.
 *
 * @param array $user
 * @param mixed $params  array('account' => id, 'view' => key)
 * @return array|null ['account' => row, 'view' => key]
 */
function mp_repricing_list_context($user, $params)
{
    // Marketplace Products is a Manager screen; a Manager sees commerce.
    if (!isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2) || !mp_pricing_ready()) {
        return null;
    }

    mp_repricing_load();

    $params = is_array($params) ? $params : array();
    $account_id = (isset($params['account']) && is_scalar($params['account'])) ? (int) $params['account'] : 0;
    $account = ($account_id > 0) ? mp_account($account_id) : null;

    return $account ? array('account' => $account, 'view' => mp_repricing_view_key($params['view'] ?? '')) : null;
}

/**
 * The products list's sort: product, rank and last change.
 *
 * @return array ['sort_levels', 'sort_keys']
 */
function mp_repricing_product_list_sort()
{
    $sort_keys = array(
        'product' => 'products.short_description',
        'rank' => '(COALESCE(state.buybox_ours, 0) & ' . MP_REPRICING_RANK_MASK . ')',
        'change' => '(SELECT COALESCE(MAX(change_log.at), 0) FROM marketplace_price_log AS change_log
            WHERE (change_log.account_id = map.account_id) AND (change_log.product_id = map.product_id))',
    );
    $columns = array('product' => lang('Product'), 'rank' => lang('Rank'), 'change' => lang('Last change'));

    return array(
        'sort_levels' => pg_list_state('marketplace_repricing', $columns, array('sort' => 'product', 'order' => 'asc'))['sorts'],
        'sort_keys' => $sort_keys,
    );
}

/**
 * The products list's columns.
 *
 * @return array
 */
function mp_repricing_product_list_columns()
{
    $sort_keys = mp_repricing_product_list_sort()['sort_keys'];

    return array(
        'product' => array('heading' => lang('Product'), 'width' => '18rem', 'sort' => $sort_keys['product']),
        'price' => array('heading' => lang('Our price'), 'width' => '8rem', 'kind' => 'money'),
        'buybox' => array('heading' => lang('Buybox price'), 'width' => '8rem', 'kind' => 'money'),
        'rank' => array('heading' => lang('Rank'), 'width' => '6rem', 'kind' => 'number', 'sort' => $sort_keys['rank']),
        'strategy' => array('heading' => lang('Strategy'), 'width' => '10rem'),
        'floor' => array('heading' => lang('Floor price'), 'width' => '8rem', 'kind' => 'money'),
        'change' => array('heading' => lang('Last change'), 'width' => '22rem', 'kind' => 'date', 'sort' => $sort_keys['change']),
    );
}

/**
 * One listing as a row of the products list.
 *
 * @param array $account
 * @param array $listing  mp_repricing_listings()
 * @return array
 */
function mp_repricing_product_list_row($account, $listing)
{
    $strategies = mp_pricing_strategies($account['provider']);
    $state = mp_repricing_state($listing);
    $by = array('own' => lang('Own price'), 'robot' => lang('by the robot'), 'rule' => lang('by the rules'), 'site' => lang('the site price'));
    $product = '<a href="marketplace_products.php?account=' . (int) $account['id'] . '&amp;mode=shop&amp;q='
        . h(rawurlencode(($listing['code'] !== '') ? $listing['code'] : $listing['title'])) . '">' . h($listing['title']) . '</a>'
        . ' <span class="pg-text-meta">' . h($listing['code']) . '</span>';

    return array('id' => (string) (int) $listing['product_id'], 'class' => 'unselectable', 'cells' => array(
        'product' => pg_list_cell($product, ''),
        'price' => pg_list_cell(mp_repricing_money($listing['price']) . ' <span class="pg-text-meta">' . h($by[$listing['by']]) . '</span>', 'text-end'),
        'buybox' => pg_list_cell(mp_repricing_money($listing['buybox_price']), 'text-end'),
        'rank' => pg_list_cell(mp_repricing_rank_cell($state['rank']), 'text-end'),
        // A strategy the marketplace lacks is sent as Fixed.
        'strategy' => pg_list_cell(h(isset($strategies[$listing['strategy']]) ? $strategies[$listing['strategy']] : $strategies[MP_PRICING_STRATEGY_FIXED]), ''),
        'floor' => pg_list_cell(($listing['floor'] !== null) ? mp_repricing_money($listing['floor'])
            : '<span class="pg-text-meta">' . h(lang('No cost')) . '</span>', 'text-end'),
        'change' => pg_list_cell(($listing['change'] !== null) ? mp_repricing_change_html($listing['change'])
            : '<span class="pg-text-meta">' . h(lang('No change yet')) . '</span>', ''),
    ));
}

/**
 * The products list as the list table driver reads it: the account's
 * matched listings under the chosen card, searched by name, code on the
 * marketplace or product code. The figures of each batch are worked out
 * for its listings only; the floor and no-cost cards first work out which
 * listings they hold.
 *
 * @param array $user
 * @param array $params  array('account' => id, 'view' => key)
 * @return array|null  pg_list_table_source(), or null when refused
 */
function mp_repricing_product_list_source($user, $params)
{
    $context = mp_repricing_list_context($user, $params);

    if ($context === null) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $account = $context['account'];
    $view = ($context['view'] === 'log') ? 'all' : $context['view'];
    $ids = (($view === 'floor') || ($view === 'no_cost')) ? mp_repricing_card_ids(mp_repricing_listings($account), $view) : array();

    return pg_list_table_source(array(
        'list' => 'marketplace_repricing',
        'columns' => mp_repricing_product_list_columns(),
        'from' => 'marketplace_product_map AS map
            INNER JOIN products ON products.id = map.product_id
            LEFT JOIN marketplace_price_state AS state
                ON (state.account_id = map.account_id) AND (state.product_id = map.product_id)',
        'where' => '(map.account_id = ?) AND ' . mp_repricing_view_condition($view, $ids) . pg_catalog_bin_live_sql('products'),
        'params' => array((string) (int) $account['id']),
        'id' => 'map.id',
        'id_order' => 'asc',
        'select' => 'map.id, map.product_id',
        'search' => array('columns' => array('products.short_description', 'products.name', 'map.remote_code')),
        'levels' => mp_repricing_product_list_sort()['sort_levels'],
        'rows' => function ($records) use ($account) {
            $listings = mp_repricing_listings($account, array_column($records, 'product_id'));
            $rows = array();

            foreach ($records as $record) {
                if (isset($listings[(int) $record['product_id']])) {
                    $rows[] = mp_repricing_product_list_row($account, $listings[(int) $record['product_id']]);
                }
            }

            return $rows;
        },
        'params_out' => array('account' => (int) $account['id'], 'view' => $view),
    ));
}

/**
 * The price log's columns; it is read newest first.
 *
 * @return array
 */
function mp_repricing_log_list_columns()
{
    return array(
        'time' => array('heading' => lang('Time'), 'width' => '10rem', 'kind' => 'date', 'sort' => 'log.at'),
        'product' => array('heading' => lang('Product'), 'width' => '18rem'),
        'old' => array('heading' => lang('Old price'), 'width' => '8rem', 'kind' => 'money'),
        'new' => array('heading' => lang('New price'), 'width' => '8rem', 'kind' => 'money'),
        'reason' => array('heading' => lang('Reason'), 'width' => '12rem', 'kind' => 'badge'),
        'sent' => array('heading' => lang('Sent'), 'width' => '8rem', 'kind' => 'badge'),
    );
}

/**
 * One price log row as a row of the list.
 *
 * @param array $entry  marketplace_price_log row with the product's names
 * @return array
 */
function mp_repricing_log_list_row($entry)
{
    // A product deleted since keeps its log rows; its number names it.
    $name = ($entry['name'] !== null) ? mp_remote_products_shop_name($entry)
        : lang(array('string' => 'Product {var:1}', 'vars' => array((int) $entry['product_id'])));

    return array('id' => (string) (int) $entry['id'], 'class' => 'unselectable', 'cells' => array(
        'time' => pg_list_cell(get_relative_time(array('timestamp' => (int) $entry['at'])), 'text-nowrap', array('data-pg-sort-value' => (int) $entry['at'])),
        'product' => pg_list_cell(h($name), ''),
        'old' => pg_list_cell(mp_repricing_money($entry['old_price']), 'text-end'),
        'new' => pg_list_cell(mp_repricing_money($entry['new_price']), 'text-end'),
        'reason' => pg_list_cell('<span class="pg-pill pg-tone-' . mp_repricing_reason_tone($entry['reason']) . '">' . h(mp_repricing_reason_label($entry['reason'])) . '</span>', ''),
        'sent' => pg_list_cell(((int) $entry['sent'] === 1)
            ? '<span class="pg-pill pg-tone-teal">' . h(lang('Sent')) . '</span>'
            : '<span class="pg-pill pg-tone-gray">' . h(lang('Try first')) . '</span>', ''),
    ));
}

/**
 * The price log as the list table driver reads it, newest first, searched
 * by the product's name or code.
 *
 * @param array $user
 * @param array $params  array('account' => id)
 * @return array|null  pg_list_table_source(), or null when refused
 */
function mp_repricing_log_list_source($user, $params)
{
    $context = mp_repricing_list_context($user, $params);

    if ($context === null) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    return pg_list_table_source(array(
        'list' => 'marketplace_repricing_log',
        'columns' => mp_repricing_log_list_columns(),
        'from' => 'marketplace_price_log AS log LEFT JOIN products ON products.id = log.product_id',
        'where' => 'log.account_id = ?',
        'params' => array((string) (int) $context['account']['id']),
        'id' => 'log.id',
        'id_order' => 'desc',
        'select' => 'log.id, log.product_id, log.old_price, log.new_price, log.reason, log.sent, log.at,
            products.name, products.short_description, products.title',
        'search' => array('columns' => array('products.short_description', 'products.name')),
        'levels' => array(array('sort' => 'time', 'order' => 'desc')),
        'rows' => function ($entries) {
            return array_map('mp_repricing_log_list_row', $entries);
        },
        'params_out' => array('account' => (int) $context['account']['id'], 'view' => 'log'),
    ));
}
