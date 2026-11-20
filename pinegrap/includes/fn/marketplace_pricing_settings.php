<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A marketplace account's Pricing tab and a listing's own price
 * (2026.11.0): the tab's fields read into marketplace_price_rules rows,
 * whether a save moves the prices, and the one-time requeue a price-moving
 * save asks for, done by the general job in batches instead of in the
 * request; a listing's own price and strategy in marketplace_price_state.
 *
 * The functions above the "readers and writers" line are pure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_pricing.php');

/** marketplace_price_state.strategy: the rules' price only (the default). */
define('MP_PRICING_STRATEGY_FIXED', 0);

/** marketplace_price_state.strategy: one step under the seller holding the buybox, never under the floor. */
define('MP_PRICING_STRATEGY_WIN', 1);

/** marketplace_price_state.strategy: a step up a day while the buybox is ours. */
define('MP_PRICING_STRATEGY_GROW', 2);

/** The listings one requeue statement covers. */
define('MP_PRICING_REQUEUE_BATCH', 500);

/** The general job's seconds a tick for the requeue. */
define('MP_PRICING_REQUEUE_BUDGET', 5);

/**
 * A typed figure in hundredths (a percent in hundredths of a percent, money
 * in minor units), or null when the box is empty.
 *
 * @param mixed $value
 * @return int|null
 */
function mp_pricing_settings_hundredths($value)
{
    if (!is_scalar($value) || (trim((string) $value) === '')) {
        return null;
    }

    return (int) round(pg_parse_amount($value) * 100);
}

/**
 * The posted value at an index of a posted list, or ''.
 *
 * @param array  $input
 * @param string $key
 * @param int    $index
 * @return string
 */
function mp_pricing_settings_list_value($input, $key, $index)
{
    $list = (isset($input[$key]) && is_array($input[$key])) ? array_values($input[$key]) : array();

    return (isset($list[$index]) && is_scalar($list[$index])) ? (string) $list[$index] : '';
}

/**
 * The Pricing tab as posted, read into the account's rule rows: its own row
 * first, then a row per category or brand rule. An empty rule line is
 * dropped; an empty box takes its default.
 *
 * Fields: pr_percent, pr_fixed, pr_rounding, pr_try (the Try first switch),
 * pr_step, pr_margin, pr_grow, and the lists pr_rule_scope ("c:<group id>"
 * or "b:<brand>"), pr_rule_percent, pr_rule_fixed, pr_rule_rounding.
 *
 * @param array $input         $_POST
 * @param int[] $category_ids  the product groups that exist
 * @return array ['rules' => rows, 'errors' => field => message]
 */
function mp_pricing_settings_parse($input, $category_ids)
{
    $input = is_array($input) ? $input : array();
    $errors = array();
    $categories = array_flip(array_map('intval', (array) $category_ids));

    $figure = function ($key, $default, $min, $max, $message) use ($input, &$errors) {
        $value = mp_pricing_settings_hundredths($input[$key] ?? null);

        if ($value === null) {
            return $default;
        }

        if (($value < $min) || ($value > $max)) {
            $errors[$key] = $message;
        }

        return $value;
    };

    $percent_message = lang('A percent from -90 to 1000.');
    $amount_message = lang('An amount up to 1,000,000.');

    $account = array(
        'scope' => MP_PRICING_SCOPE_ACCOUNT,
        'scope_id' => 0,
        'scope_key' => '',
        'percent' => $figure('pr_percent', 0, -9000, 100000, $percent_message),
        'fixed' => $figure('pr_fixed', 0, -100000000, 100000000, $amount_message),
        'rounding' => mp_pricing_rounding($input['pr_rounding'] ?? 'none'),
        // The switch posts nothing when it is off; a form that never drew
        // it (no pr_step either) keeps try first on.
        'robot' => (!empty($input['pr_try']) || !array_key_exists('pr_step', $input))
            ? MP_PRICING_ROBOT_TRY : MP_PRICING_ROBOT_ON,
        'step' => $figure('pr_step', 100, 1, 100000000, lang('A step of more than zero.')),
        'min_margin' => $figure('pr_margin', MP_PRICING_DEFAULT_MARGIN, 0, 9000, lang('A margin from 0 to 90 percent.')),
        'grow_cap' => $figure('pr_grow', 1000, 0, 10000, lang('A limit from 0 to 100 percent.')),
    );

    $rules = array($account);
    $seen = array();
    $count = (isset($input['pr_rule_scope']) && is_array($input['pr_rule_scope'])) ? count($input['pr_rule_scope']) : 0;

    for ($i = 0; $i < $count; $i++) {
        $scope = trim(mp_pricing_settings_list_value($input, 'pr_rule_scope', $i));

        if ($scope === '') {
            continue;
        }

        $field = 'pr_rule_' . $i;
        $row = array('scope' => MP_PRICING_SCOPE_BRAND, 'scope_id' => 0, 'scope_key' => '');

        if (strpos($scope, 'c:') === 0) {
            $row['scope'] = MP_PRICING_SCOPE_CATEGORY;
            $row['scope_id'] = (int) substr($scope, 2);

            if (!isset($categories[$row['scope_id']])) {
                $errors[$field] = lang('That category no longer exists.');
            }
        } else {
            $row['scope_key'] = trim((strpos($scope, 'b:') === 0) ? substr($scope, 2) : $scope);

            if (($row['scope_key'] === '') || (mb_strlen($row['scope_key']) > 100)) {
                $errors[$field] = lang('A brand of 1 to 100 characters.');
            }
        }

        $key = $row['scope'] . '|' . $row['scope_id'] . '|' . mp_pricing_brand_key($row['scope_key']);

        if (isset($seen[$key]) && !isset($errors[$field])) {
            $errors[$field] = lang('This category or brand has a rule already.');
        }

        $seen[$key] = true;

        $percent = mp_pricing_settings_hundredths(mp_pricing_settings_list_value($input, 'pr_rule_percent', $i));
        $fixed = mp_pricing_settings_hundredths(mp_pricing_settings_list_value($input, 'pr_rule_fixed', $i));
        $row['percent'] = (int) $percent;
        $row['fixed'] = (int) $fixed;
        $row['rounding'] = mp_pricing_rounding(mp_pricing_settings_list_value($input, 'pr_rule_rounding', $i));

        if (($row['percent'] < -9000) || ($row['percent'] > 100000)) {
            $errors[$field] = $percent_message;
        } elseif (abs($row['fixed']) > 100000000) {
            $errors[$field] = $amount_message;
        }

        $rules[] = $row;
    }

    return array('rules' => $rules, 'errors' => $errors);
}

/**
 * Whether parsed rows are what an account with no rows means: no rule, the
 * account's row at its defaults. A save of the drawer that left the tab
 * alone then writes nothing, so the site price goes out as before.
 *
 * @param array $rules  mp_pricing_settings_parse()['rules']
 * @return bool
 */
function mp_pricing_settings_is_default($rules)
{
    $rules = array_values((array) $rules);

    if (count($rules) !== 1) {
        return false;
    }

    $defaults = mp_pricing_settings_parse(array(), array())['rules'][0];

    foreach ($defaults as $column => $value) {
        if (!isset($rules[0][$column]) || ((string) $rules[0][$column] !== (string) $value)) {
            return false;
        }
    }

    return true;
}

/**
 * The figures of an account's rows that move a channel price, keyed by
 * scope: percent, fixed and rounding of every row, and the margin of the
 * account's row (the floor).
 *
 * @param array $rules
 * @return array
 */
function mp_pricing_settings_price_keys($rules)
{
    $keys = array();

    foreach ((array) $rules as $rule) {
        $scope = (int) ($rule['scope'] ?? 0);
        $key = $scope . '|' . (int) ($rule['scope_id'] ?? 0) . '|' . mp_pricing_brand_key((string) ($rule['scope_key'] ?? ''));

        $keys[$key] = (int) ($rule['percent'] ?? 0) . '|' . (int) ($rule['fixed'] ?? 0) . '|'
            . mp_pricing_rounding($rule['rounding'] ?? 'none')
            . (($scope === MP_PRICING_SCOPE_ACCOUNT) ? '|' . (int) ($rule['min_margin'] ?? MP_PRICING_DEFAULT_MARGIN) : '');
    }

    ksort($keys);

    return $keys;
}

/**
 * Whether going from one set of an account's rows to another moves its
 * channel prices, so they are sent again.
 *
 * @param array $before  stored rows
 * @param array $after   rows to store
 * @return bool
 */
function mp_pricing_settings_changed($before, $after)
{
    return mp_pricing_settings_price_keys($before) !== mp_pricing_settings_price_keys($after);
}

/**
 * The requeue state with an account marked from its first listing.
 *
 * @param array $state       account id => ['after' => product id, 'at' => time]
 * @param int   $account_id
 * @param int   $now
 * @return array
 */
function mp_pricing_requeue_mark($state, $account_id, $now)
{
    $state = is_array($state) ? $state : array();
    $state[(string) (int) $account_id] = array('after' => 0, 'at' => (int) $now);

    return $state;
}

/**
 * The requeue state after a batch of an account: moved on to the last
 * listing queued, or dropped when the pass is through. A mark newer than
 * the pass ('at' changed: the rules changed again meanwhile) is left as it
 * is, so the listings are queued once more from the start.
 *
 * @param array $state
 * @param int   $account_id
 * @param int   $at       the mark the pass started from
 * @param int   $last_id  the last product id queued
 * @param bool  $through
 * @return array
 */
function mp_pricing_requeue_step($state, $account_id, $at, $last_id, $through)
{
    $state = is_array($state) ? $state : array();
    $key = (string) (int) $account_id;

    if (!isset($state[$key]) || ((int) $state[$key]['at'] !== (int) $at)) {
        return $state;
    }

    if ($through) {
        unset($state[$key]);
    } else {
        $state[$key]['after'] = (int) $last_id;
    }

    return $state;
}

/**
 * The strategies a marketplace offers, strategy => label. Win and Grow
 * while winning work from a buybox read, which Trendyol answers; every
 * other marketplace (n11) keeps the rules' price.
 *
 * @param string $provider
 * @return array
 */
function mp_pricing_strategies($provider)
{
    $strategies = array(MP_PRICING_STRATEGY_FIXED => lang('Fixed'));

    if ((string) $provider === 'trendyol') {
        $strategies[MP_PRICING_STRATEGY_WIN] = lang('Win');
        $strategies[MP_PRICING_STRATEGY_GROW] = lang('Grow while winning');
    }

    return $strategies;
}

/**
 * A listing's own price and strategy as typed in its row: an empty price is
 * by the rules (0); a strategy the marketplace does not offer is Fixed.
 *
 * @param mixed  $own_price
 * @param mixed  $strategy
 * @param string $provider
 * @return array ['own_price' => int, 'strategy' => int, 'error' => string]
 */
function mp_pricing_listing_parse($own_price, $strategy, $provider)
{
    $strategy = (is_scalar($strategy) && ctype_digit((string) $strategy)) ? (int) $strategy : MP_PRICING_STRATEGY_FIXED;
    $answer = array(
        'own_price' => 0,
        'strategy' => array_key_exists($strategy, mp_pricing_strategies($provider)) ? $strategy : MP_PRICING_STRATEGY_FIXED,
        'error' => '',
    );

    $price = mp_pricing_settings_hundredths($own_price);

    if ($price === null) {
        return $answer;
    }

    if (($price <= 0) || ($price > 100000000)) {
        $answer['error'] = lang('Own price: an amount over zero and up to 1,000,000, or empty to price by the rules.');
        return $answer;
    }

    $answer['own_price'] = $price;

    return $answer;
}

/* ------------------------------------------------------ readers and writers */

/**
 * Store an account's rows: each one written over the row of its scope, and
 * the account's rows the tab no longer has removed.
 *
 * @param int   $account_id
 * @param array $rules    mp_pricing_settings_parse()['rules']
 * @param int   $user_id
 * @return bool  false when a statement failed
 */
function mp_pricing_settings_save($account_id, $rules, $user_id)
{
    $account_id = (int) $account_id;
    $keep = array();
    $ok = true;

    foreach ((array) $rules as $rule) {
        $own = ((int) $rule['scope'] === MP_PRICING_SCOPE_ACCOUNT);

        $ok = (db_q("INSERT INTO marketplace_price_rules
                (account_id, scope, scope_id, scope_key, percent, fixed, rounding, robot, step, min_margin, grow_cap, updated_at, updated_by)
            VALUES ('" . $account_id . "', '" . (int) $rule['scope'] . "', '" . (int) $rule['scope_id'] . "',
                '" . e((string) $rule['scope_key']) . "', '" . (int) $rule['percent'] . "', '" . (int) $rule['fixed'] . "',
                '" . e(mp_pricing_rounding($rule['rounding'])) . "',
                '" . ($own ? (int) $rule['robot'] : 0) . "', '" . ($own ? (int) $rule['step'] : 100) . "',
                '" . ($own ? (int) $rule['min_margin'] : MP_PRICING_DEFAULT_MARGIN) . "', '" . ($own ? (int) $rule['grow_cap'] : 1000) . "',
                UNIX_TIMESTAMP(), '" . (int) $user_id . "')
            ON DUPLICATE KEY UPDATE scope_key = VALUES(scope_key), percent = VALUES(percent), fixed = VALUES(fixed),
                rounding = VALUES(rounding), robot = VALUES(robot), step = VALUES(step), min_margin = VALUES(min_margin),
                grow_cap = VALUES(grow_cap), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)") !== false) && $ok;

        $keep[] = "((scope = '" . (int) $rule['scope'] . "') AND (scope_id = '" . (int) $rule['scope_id'] . "')
            AND (scope_key = '" . e((string) $rule['scope_key']) . "'))";
    }

    // Only once every row is written, so a failed write leaves the old
    // rules standing instead of fewer.
    if ($ok) {
        $ok = db_q("DELETE FROM marketplace_price_rules WHERE (account_id = '" . $account_id . "')"
            . ($keep ? " AND NOT (" . implode(' OR ', $keep) . ")" : '')) !== false;
    }

    return $ok;
}

/**
 * The categories a rule can name: id => name with its parents, in name
 * order.
 *
 * @return array
 */
function mp_pricing_settings_categories()
{
    $rows = (array) db_items("SELECT id, name, parent_id FROM product_groups ORDER BY name");
    $by_id = array();

    foreach ($rows as $row) {
        $by_id[(int) $row['id']] = $row;
    }

    $names = array();

    foreach ($by_id as $id => $row) {
        $path = array((string) $row['name']);
        $seen = array($id => true);
        $parent = (int) $row['parent_id'];

        while (($parent > 0) && isset($by_id[$parent]) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            array_unshift($path, (string) $by_id[$parent]['name']);
            $parent = (int) $by_id[$parent]['parent_id'];
        }

        $names[$id] = implode(' › ', $path);
    }

    asort($names, SORT_NATURAL | SORT_FLAG_CASE);

    return $names;
}

/**
 * The brands the shop's products carry, for the rule's picker.
 *
 * @return string[]
 */
function mp_pricing_settings_brands()
{
    return array_map('strval', (array) db_values("SELECT DISTINCT TRIM(brand) AS brand FROM products
        WHERE (brand IS NOT NULL) AND (TRIM(brand) <> '') ORDER BY brand LIMIT 1000"));
}

/**
 * Write a listing's own price and strategy.
 *
 * @param int $account_id
 * @param int $product_id
 * @param int $own_price  minor units, 0 by the rules
 * @param int $strategy
 * @return bool
 */
function mp_pricing_listing_save($account_id, $product_id, $own_price, $strategy)
{
    return db_q("INSERT INTO marketplace_price_state (account_id, product_id, strategy, own_price, updated_at)
        VALUES ('" . (int) $account_id . "', '" . (int) $product_id . "', '" . (int) $strategy . "',
            '" . max(0, (int) $own_price) . "', UNIX_TIMESTAMP())
        ON DUPLICATE KEY UPDATE strategy = VALUES(strategy), own_price = VALUES(own_price),
            updated_at = VALUES(updated_at)") !== false;
}

/**
 * The file the requeue state is kept in.
 *
 * @return string
 */
function mp_pricing_requeue_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/mp_pricing_requeue.json';
}

/**
 * Change the requeue state under a lock.
 *
 * @param callable $change  state => state
 * @return bool
 */
function mp_pricing_requeue_update($change)
{
    $handle = @fopen(mp_pricing_requeue_file(), 'c+');

    if (!$handle) {
        return false;
    }

    $ok = false;

    if (@flock($handle, LOCK_EX)) {
        $state = @json_decode((string) stream_get_contents($handle), true);
        $state = $change(is_array($state) ? $state : array());

        ftruncate($handle, 0);
        rewind($handle);
        $ok = fwrite($handle, (string) json_encode($state)) !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);

    return $ok;
}

/**
 * Ask for an account's listings to be sent again, by the general job.
 *
 * @param int $account_id
 * @return bool
 */
function mp_pricing_requeue_request($account_id)
{
    return mp_pricing_requeue_update(function ($state) use ($account_id) {
        return mp_pricing_requeue_mark($state, $account_id, time());
    });
}

/**
 * Whether an account waits for its requeue. A tick with nothing owed reads
 * one small file.
 *
 * @return bool
 */
function mp_pricing_requeue_due()
{
    $state = @json_decode((string) @file_get_contents(mp_pricing_requeue_file()), true);

    return is_array($state) && (count($state) > 0);
}

/**
 * The requeue pass: per marked account, its active listings in product id
 * order, a batch a statement, each queued unless a row waits for it that no
 * send has taken yet. An account that is paused, sends no prices or cannot
 * send them is dropped: nothing would go.
 *
 * @param int $seconds
 * @return int  the rows queued
 */
function mp_pricing_requeue_run($seconds = MP_PRICING_REQUEUE_BUDGET)
{
    $state = @json_decode((string) @file_get_contents(mp_pricing_requeue_file()), true);

    if (!is_array($state) || !$state || !mp_pricing_ready()) {
        return 0;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');

    $deadline = microtime(true) + max(1, (int) $seconds);
    $queued = 0;

    foreach ($state as $key => $mark) {
        $account_id = (int) $key;
        $at = (int) ($mark['at'] ?? 0);
        $after = (int) ($mark['after'] ?? 0);
        $account = db_item("SELECT id, provider, status, push_price FROM marketplace_accounts
            WHERE id = '" . $account_id . "'");

        if (!$account || ($account['status'] !== 'active') || empty($account['push_price'])
            || !mp_can((string) $account['provider'], 'push_stock_price')) {
            mp_pricing_requeue_update(function ($state) use ($account_id, $at) {
                return mp_pricing_requeue_step($state, $account_id, $at, 0, true);
            });
            continue;
        }

        while (microtime(true) < $deadline) {
            $ids = array_map('intval', (array) db_values("SELECT product_id FROM marketplace_product_map
                WHERE (account_id = '" . $account_id . "') AND (status = 'active') AND (product_id > '" . $after . "')
                ORDER BY product_id LIMIT " . MP_PRICING_REQUEUE_BATCH));

            if ($ids && (db_q("INSERT INTO marketplace_sync_queue
                    (account_id, product_id, operation, priority, status, run_after, created_timestamp, updated_timestamp)
                SELECT map.account_id, map.product_id, 'stock_price', 5, 'waiting', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
                FROM marketplace_product_map AS map
                WHERE (map.account_id = '" . $account_id . "') AND (map.product_id IN (" . implode(',', $ids) . "))
                  AND NOT EXISTS (SELECT 1 FROM marketplace_sync_queue AS queue
                    WHERE (queue.account_id = map.account_id) AND (queue.product_id = map.product_id)
                      AND (queue.operation = 'stock_price') AND (queue.status = 'waiting')
                      AND (queue.remote_task_id = ''))") === false)) {
                // Asked again on the next tick from where it stood.
                return $queued;
            }

            $through = (count($ids) < MP_PRICING_REQUEUE_BATCH);
            $after = $ids ? end($ids) : $after;
            $queued += count($ids);

            $last = $after;
            mp_pricing_requeue_update(function ($state) use ($account_id, $at, $last, $through) {
                return mp_pricing_requeue_step($state, $account_id, $at, $last, $through);
            });

            if ($through) {
                break;
            }
        }

        if (microtime(true) >= $deadline) {
            break;
        }
    }

    return $queued;
}
