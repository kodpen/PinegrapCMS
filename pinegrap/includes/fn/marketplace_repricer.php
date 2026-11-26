<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The marketplace repricer's robot (2026.11.0): after each buybox read it
 * moves the price of the listings set to Win or Grow while winning, never
 * under the profit floor and never over the rules' price but by Grow while
 * winning's limit.
 *
 * Win: while another seller holds the buybox, its price less one step;
 * holding it against others, the price stays; alone, back to the rules'
 * price. Grow while winning: while ours, one step up a day at most, never to
 * a price that lost the buybox in the last 7 days; a lost buybox steps back
 * one step, not under the rules' price, and remembers the price it lost at.
 *
 * A listing's price changes at most 4 times and 10% a day, counted on the
 * log of the last 24 hours. With Try first on, the robot writes the log and
 * sends nothing; switched to sending, a change queues the listing and the
 * price goes out through mp_product_row() like any other.
 *
 * Everything above the "readers" line is pure; the pass below it reads and
 * writes one buybox batch of one account (mp_repricer_run(), called by the
 * marketplace job after the read).
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

/** marketplace_price_log.reason: another seller held the buybox (Win). */
define('MP_REPRICER_REASON_LOST', 'lost');

/** marketplace_price_log.reason: Grow while winning lost the buybox and stepped back. */
define('MP_REPRICER_REASON_BACK', 'back');

/** marketplace_price_log.reason: the price stopped at the floor. */
define('MP_REPRICER_REASON_FLOOR', 'floor');

/** marketplace_price_log.reason: the price went up. */
define('MP_REPRICER_REASON_RAISED', 'raised');

/** marketplace_price_state.state: no buybox read yet. */
define('MP_REPRICER_STATE_NONE', 0);

/** marketplace_price_state.state: the buybox is ours. */
define('MP_REPRICER_STATE_WINNING', 1);

/** marketplace_price_state.state: another seller holds the buybox. */
define('MP_REPRICER_STATE_LOSING', 2);

/** marketplace_price_state.state: another seller holds it and the price is at the floor. */
define('MP_REPRICER_STATE_FLOOR', 3);

/** marketplace_price_state.state: no floor (no cost and none typed), so no robot. */
define('MP_REPRICER_STATE_NO_FLOOR', 4);

/** Price changes a listing may have in 24 hours. */
define('MP_REPRICER_DAY_CHANGES', 4);

/** How far a listing's price may move in 24 hours, hundredths of a percent of the first. */
define('MP_REPRICER_DAY_PERCENT', 1000);

/** Seconds a price that lost the buybox is not tried again. */
define('MP_REPRICER_LOST_WAIT', 604800);

/** Buybox reads failing in a row before the alert. */
define('MP_REPRICER_FAILURES', 3);

/** Days the log is kept. */
define('MP_REPRICER_LOG_DAYS', 90);

/** pg_ops_alert_codes(): more than half the Win listings lost the buybox in one read. */
define('MP_REPRICER_ALERT_LOSING', 'repricer_losing');

/** pg_ops_alert_codes(): the buybox read failed three times in a row. */
define('MP_REPRICER_ALERT_FAILING', 'buybox_failing');

/** product_price_history.source of a price the robot set. */
define('MP_REPRICER_HISTORY_SOURCE', 'repricer');

/**
 * The reasons a log row can carry, the one place they are listed.
 *
 * @return string[]
 */
function mp_repricer_reasons()
{
    return array(MP_REPRICER_REASON_LOST, MP_REPRICER_REASON_FLOOR, MP_REPRICER_REASON_RAISED, MP_REPRICER_REASON_BACK);
}

/**
 * The highest price the robot may set: the rules' price, or for Grow while
 * winning that price plus its limit (hundredths of a percent).
 *
 * @param int $rule_price  mp_pricing_channel_price(), already at or over the floor
 * @param int $strategy    MP_PRICING_STRATEGY_*
 * @param int $grow_cap    marketplace_price_rules.grow_cap
 * @return int
 */
function mp_repricer_cap($rule_price, $strategy, $grow_cap)
{
    $rule_price = max(0, (int) $rule_price);

    if ((int) $strategy !== MP_PRICING_STRATEGY_GROW) {
        return $rule_price;
    }

    return $rule_price + (int) pg_percent_of($rule_price, max(0, (int) $grow_cap) / 100);
}

/**
 * A robot price held between the floor and the cap, or null when there is
 * no floor (no robot without one).
 *
 * @param int      $price
 * @param int      $rule_price
 * @param int|null $floor
 * @param int      $strategy
 * @param int      $grow_cap
 * @return int|null
 */
function mp_repricer_bound($price, $rule_price, $floor, $strategy, $grow_cap)
{
    if ($floor === null) {
        return null;
    }

    return max((int) $floor, min((int) $price, mp_repricer_cap($rule_price, $strategy, $grow_cap)));
}

/**
 * The price a strategy wants for one listing after a buybox read.
 *
 * @param array $in
 *   strategy      int, MP_PRICING_STRATEGY_*
 *   rank          int, our place in the buybox order (1 ours, 0 no read)
 *   others        bool, other sellers sell it
 *   buybox_price  int, minor units
 *   current       int, the price it sells at now (mp_repricer_bound())
 *   rule_price    int, the rules' price, at or over the floor
 *   floor         int|null
 *   step, step_unit, grow_cap  the account row's
 *   raised_today  bool, a raise is in the last 24 hours' log
 *   lost_price, lost_at  the price that last lost the buybox and when
 *   now           int
 * @return array ['price' => int, 'reason' => string, 'lost_price' => int, 'lost_at' => int]
 *   reason '' when the price stays; lost_at 0 when nothing is to be remembered
 */
function mp_repricer_target($in)
{
    $current = (int) $in['current'];
    $floor = $in['floor'];
    $strategy = (int) $in['strategy'];
    $rank = (int) $in['rank'];
    $hold = array('price' => $current, 'reason' => '', 'lost_price' => 0, 'lost_at' => 0);

    if (($floor === null) || ($rank < 1)) {
        return $hold;
    }

    $floor = (int) $floor;
    $rule_price = (int) $in['rule_price'];
    $cap = mp_repricer_cap($rule_price, $strategy, (int) $in['grow_cap']);

    if ($strategy === MP_PRICING_STRATEGY_WIN) {
        if ($rank === 1) {
            // Alone there is nobody to beat: back to the rules' price.
            if (empty($in['others']) && ($cap > $current)) {
                return array('price' => $cap, 'reason' => MP_REPRICER_REASON_RAISED, 'lost_price' => 0, 'lost_at' => 0);
            }

            return $hold;
        }

        $buybox = (int) $in['buybox_price'];

        if ($buybox <= 0) {
            return $hold;
        }

        $price = min($cap, $buybox - mp_pricing_step($buybox, (int) $in['step'], (int) $in['step_unit']));
        $reason = MP_REPRICER_REASON_LOST;

        if ($price <= $floor) {
            $price = $floor;
            $reason = MP_REPRICER_REASON_FLOOR;
        }

        // Losing while already cheaper than the buybox (the marketplace
        // weighs more than the price): the price is never raised for it.
        if ($price >= $current) {
            return $hold;
        }

        return array('price' => $price, 'reason' => $reason, 'lost_price' => 0, 'lost_at' => 0);
    }

    if ($strategy !== MP_PRICING_STRATEGY_GROW) {
        return $hold;
    }

    $step = mp_pricing_step($current, (int) $in['step'], (int) $in['step_unit']);

    if ($rank > 1) {
        // Probing stops where it lost: one step back, never under the
        // rules' price, and the price that lost waits 7 days.
        $price = max($rule_price, $floor, $current - $step);

        if ($price >= $current) {
            return $hold;
        }

        return array('price' => $price, 'reason' => MP_REPRICER_REASON_BACK,
            'lost_price' => $current, 'lost_at' => (int) $in['now']);
    }

    if (!empty($in['raised_today'])) {
        return $hold;
    }

    $price = min($cap, $current + $step);

    if (((int) $in['lost_price'] > 0) && ((int) $in['now'] - (int) $in['lost_at'] <= MP_REPRICER_LOST_WAIT)
        && ($price >= (int) $in['lost_price'])) {
        return $hold;
    }

    if ($price <= $current) {
        return $hold;
    }

    return array('price' => $price, 'reason' => MP_REPRICER_REASON_RAISED, 'lost_price' => 0, 'lost_at' => 0);
}

/**
 * A listing's day from its log rows of the last 24 hours, oldest first.
 *
 * @param array $rows     marketplace_price_log: old_price, new_price, reason, at
 * @param int   $current  the price now, the day's first when there is no row
 * @return array ['changes' => int, 'base' => int, 'raised' => bool]
 */
function mp_repricer_day($rows, $current)
{
    $rows = array_values((array) $rows);
    $raised = false;

    foreach ($rows as $row) {
        if ((string) $row['reason'] === MP_REPRICER_REASON_RAISED) {
            $raised = true;
        }
    }

    return array(
        'changes' => count($rows),
        'base' => $rows ? (int) $rows[0]['old_price'] : (int) $current,
        'raised' => $raised,
    );
}

/**
 * A wanted price after the day's limits: the old price once the day has had
 * its changes, else at most 10% from the day's first price. The limit only
 * holds a move short, never turns it round or past what was wanted: the old
 * price can sit outside the day's 10% when the rules' price or the floor
 * moved since the day's first change, and a cut taken back into the window
 * from over it would go under the floor.
 *
 * @param int   $old
 * @param int   $new
 * @param array $day  mp_repricer_day()
 * @return int
 */
function mp_repricer_limit($old, $new, $day)
{
    $old = (int) $old;
    $new = (int) $new;

    if ((int) $day['changes'] >= MP_REPRICER_DAY_CHANGES) {
        return $old;
    }

    $base = (int) $day['base'];
    $room = (int) pg_percent_of($base, MP_REPRICER_DAY_PERCENT / 100);

    if ($new < $old) {
        return min($old, max($new, $base - $room));
    }

    return max($old, min($new, $base + $room));
}

/**
 * What the pass does with one listing: the strategy's price within the
 * day's limits.
 *
 * @param array $in   mp_repricer_target()'s input
 * @param array $day  mp_repricer_day()
 * @return array ['price' => int, 'reason' => string, 'changed' => bool, 'lost_price' => int, 'lost_at' => int]
 */
function mp_repricer_decide($in, $day)
{
    $current = (int) $in['current'];
    $target = mp_repricer_target(array_merge($in, array('raised_today' => !empty($in['raised_today']) || !empty($day['raised']))));
    $price = ($target['reason'] === '') ? $current : mp_repricer_limit($current, $target['price'], $day);

    if ($price === $current) {
        return array('price' => $current, 'reason' => '', 'changed' => false, 'lost_price' => 0, 'lost_at' => 0);
    }

    $reason = $target['reason'];

    // Held short of the floor by the day's limit: the floor was not reached.
    if (($reason === MP_REPRICER_REASON_FLOOR) && ($in['floor'] !== null) && ($price > (int) $in['floor'])) {
        $reason = MP_REPRICER_REASON_LOST;
    }

    return array('price' => $price, 'reason' => $reason, 'changed' => true,
        'lost_price' => $target['lost_price'], 'lost_at' => $target['lost_at']);
}

/**
 * A listing's place for the Repricing screen: winning, losing, losing at the
 * floor, no floor, or not read.
 *
 * @param int      $rank
 * @param int      $price  the price it sells at after the pass
 * @param int|null $floor
 * @return int  MP_REPRICER_STATE_*
 */
function mp_repricer_state($rank, $price, $floor)
{
    if ($floor === null) {
        return MP_REPRICER_STATE_NO_FLOOR;
    }

    $rank = (int) $rank;

    if ($rank < 1) {
        return MP_REPRICER_STATE_NONE;
    }

    if ($rank === 1) {
        return MP_REPRICER_STATE_WINNING;
    }

    return ((int) $price <= (int) $floor) ? MP_REPRICER_STATE_FLOOR : MP_REPRICER_STATE_LOSING;
}

/**
 * The robot price mp_product_row() sends instead of the rules' price, or
 * null for the rules' price: the robot sending (Try first off), the listing
 * on Win or Grow while winning with a robot price, and a floor. Held within
 * the floor and the cap as they are now, so a site price or a cost that
 * moved since the pass moves it too.
 *
 * @param array|null $account_row  mp_pricing_account_row(): robot, grow_cap
 * @param array      $figures      mp_pricing_product_figures(): strategy, robot_price
 * @param int        $rule_price
 * @param int|null   $floor
 * @return int|null
 */
function mp_repricer_send_price($account_row, $figures, $rule_price, $floor)
{
    if (!is_array($account_row) || ((int) $account_row['robot'] !== MP_PRICING_ROBOT_ON)) {
        return null;
    }

    $strategy = (int) ($figures['strategy'] ?? MP_PRICING_STRATEGY_FIXED);
    $robot_price = (int) ($figures['robot_price'] ?? 0);

    if ((($strategy !== MP_PRICING_STRATEGY_WIN) && ($strategy !== MP_PRICING_STRATEGY_GROW)) || ($robot_price <= 0)) {
        return null;
    }

    return mp_repricer_bound($robot_price, $rule_price, $floor, $strategy, (int) $account_row['grow_cap']);
}

/**
 * Whether one read lost the buybox on more than half the Win listings.
 *
 * @param int $total  Win listings read
 * @param int $lost   of them, held by another seller
 * @return bool
 */
function mp_repricer_losing($total, $lost)
{
    return ((int) $total > 0) && ((int) $lost * 2 > (int) $total);
}

/**
 * The count of buybox reads failed in a row after one more.
 *
 * @param int  $count
 * @param bool $ok
 * @return int
 */
function mp_repricer_failures($count, $ok)
{
    return $ok ? 0 : max(0, (int) $count) + 1;
}

/**
 * One statement writing the log rows of a batch, or '' for none.
 *
 * @param int    $account_id
 * @param array  $changes   product id => old_price, new_price, floor, buybox_price, reason
 * @param string $currency
 * @param bool   $sent      the robot sends (Try first off)
 * @param int    $now
 * @return string
 */
function mp_repricer_log_sql($account_id, $changes, $currency, $sent, $now)
{
    if (!$changes) {
        return '';
    }

    $rows = array();

    foreach ($changes as $product_id => $change) {
        $rows[] = "('" . (int) $account_id . "', '" . (int) $product_id . "', '" . (int) $change['old_price'] . "', '"
            . (int) $change['new_price'] . "', '" . (int) $change['floor'] . "', '" . (int) $change['buybox_price'] . "', '"
            . preg_replace('/[^A-Z]/', '', strtoupper(substr((string) $currency, 0, 3))) . "', '"
            . preg_replace('/[^a-z]/', '', substr((string) $change['reason'], 0, 20)) . "', '" . ($sent ? 1 : 0) . "', '"
            . (int) $now . "')";
    }

    return "INSERT INTO marketplace_price_log
        (account_id, product_id, old_price, new_price, floor, buybox_price, currency, reason, sent, at)
        VALUES " . implode(",\n        ", $rows);
}

/* ------------------------------------------------------------------ readers */

/**
 * The robot pass over one buybox batch of an account, right after its read.
 *
 * Only for an account with a pricing row (the robot is then sending or on
 * Try first); every listing read gets its state, and those on Win or Grow
 * while winning without a typed price get the robot's price. A change
 * writes the log, and while sending, the listing's robot price and a queue
 * row, so the price leaves on the next pass.
 *
 * @param array $account  marketplace_accounts row
 * @param array $values   mp_sync_buybox_values(): product id => buybox_price, buybox_ours, buybox_at
 * @param int   $now
 * @return array ['changed' => int, 'win' => int, 'win_lost' => int]
 */
function mp_repricer_run($account, $values, $now)
{
    $summary = array('changed' => 0, 'win' => 0, 'win_lost' => 0);
    $account_id = (int) $account['id'];
    $rules = mp_pricing_account_rules($account_id);
    $own = mp_pricing_account_row($rules);

    if (!$values || !is_array($own) || ((int) $own['robot'] === MP_PRICING_ROBOT_OFF)
        || !pg_schema_has('marketplace_price_log', 'reason')) {
        return $summary;
    }

    $ids = array_map('intval', array_keys($values));
    $products = array();

    foreach ((array) db_items("SELECT id, price, taxable, tax_rate, brand FROM products
        WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $products[(int) $row['id']] = $row;
    }

    $days = array();

    foreach ((array) db_items("SELECT product_id, old_price, new_price, reason, at FROM marketplace_price_log
        WHERE (account_id = '" . $account_id . "') AND (product_id IN (" . implode(',', $ids) . "))
          AND (at > '" . ((int) $now - 86400) . "')
        ORDER BY at ASC, id ASC") as $row) {
        $days[(int) $row['product_id']][] = $row;
    }

    $sending = ((int) $own['robot'] === MP_PRICING_ROBOT_ON) && !empty($account['push_price']);
    $changes = array();
    $updates = array();

    foreach ($values as $product_id => $read) {
        $product_id = (int) $product_id;

        if (!isset($products[$product_id])) {
            continue;
        }

        $listing = mp_repricer_listing($account, $rules, $own, $products[$product_id]);
        $ours = mp_buybox_ours_read($read['buybox_ours']);
        $strategy = (int) $listing['figures']['strategy'];
        $robot = (($strategy === MP_PRICING_STRATEGY_WIN) || ($strategy === MP_PRICING_STRATEGY_GROW))
            && ((int) $listing['figures']['own_price'] <= 0);

        if ($strategy === MP_PRICING_STRATEGY_WIN) {
            $summary['win']++;
            $summary['win_lost'] += ($ours['rank'] > 1) ? 1 : 0;
        }

        $decision = array('price' => $listing['current'], 'changed' => false, 'lost_price' => 0, 'lost_at' => 0);

        if ($robot) {
            $decision = mp_repricer_decide(array(
                'strategy' => $strategy,
                'rank' => $ours['rank'],
                'others' => $ours['others'],
                'buybox_price' => (int) $read['buybox_price'],
                'current' => $listing['current'],
                'rule_price' => $listing['rule_price'],
                'floor' => $listing['floor'],
                'step' => (int) $own['step'],
                'step_unit' => (int) $own['step_unit'],
                'grow_cap' => (int) $own['grow_cap'],
                'raised_today' => false,
                'lost_price' => (int) $listing['figures']['lost_price'],
                'lost_at' => (int) $listing['figures']['lost_at'],
                'now' => (int) $now,
            ), mp_repricer_day($days[$product_id] ?? array(), $listing['current']));
        }

        $set = array("state = '" . mp_repricer_state($ours['rank'], $decision['price'], $listing['floor']) . "'");

        if ($decision['changed']) {
            $changes[$product_id] = array(
                'old_price' => $listing['current'],
                'new_price' => $decision['price'],
                'floor' => (int) $listing['floor'],
                'buybox_price' => (int) $read['buybox_price'],
                'reason' => $decision['reason'],
            );

            // Try first keeps its robot price too, so the log shows how the
            // strategy would walk; nothing reads it while Try first is on.
            $set[] = "robot_price = '" . (int) $decision['price'] . "'";
            $set[] = "changed_at = '" . (int) $now . "'";

            if ($decision['lost_at'] > 0) {
                $set[] = "lost_price = '" . (int) $decision['lost_price'] . "'";
                $set[] = "lost_at = '" . (int) $decision['lost_at'] . "'";
            }
        }

        $updates[$product_id] = $set;
    }

    foreach ($updates as $product_id => $set) {
        db("UPDATE marketplace_price_state SET " . implode(', ', $set) . ", updated_at = '" . (int) $now . "'
            WHERE (account_id = '" . $account_id . "') AND (product_id = '" . (int) $product_id . "')");
    }

    $currency = function_exists('pg_price_history_currency') ? pg_price_history_currency() : '';
    $sql = mp_repricer_log_sql($account_id, $changes, $currency, $sending, $now);

    if (($sql !== '') && (db($sql) !== false)) {
        $summary['changed'] = count($changes);
    }

    if ($sending && $changes) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/product_matches.php');
        mp_matches_send($account, array_keys($changes));
    }

    return $summary;
}

/**
 * One listing's figures for the pass: its rules' price, floor and the price
 * it sells at now.
 *
 * @param array $account   marketplace_accounts row
 * @param array $rules     mp_pricing_account_rules()
 * @param array $own       mp_pricing_account_row()
 * @param array $product   products row: id, price, taxable, tax_rate, brand
 * @return array ['rule_price' => int, 'floor' => int|null, 'current' => int, 'figures' => array]
 */
function mp_repricer_listing($account, $rules, $own, $product)
{
    $site_price = mp_product_price($product);
    $vat_rate = mp_product_vat_rate($product);
    $priced = mp_pricing_rule_price_of($account, $rules, $product, $site_price,
        ($vat_rate === null) ? mp_pricing_tax_off_rate($product) : $vat_rate);

    $strategy = (int) $priced['figures']['strategy'];
    $current = $priced['price'];
    $robot_price = (int) $priced['figures']['robot_price'];

    // While Try first is on, the robot walks from its own last price, as it
    // would have sent it.
    if (($robot_price > 0) && (($strategy === MP_PRICING_STRATEGY_WIN) || ($strategy === MP_PRICING_STRATEGY_GROW))) {
        $bound = mp_repricer_bound($robot_price, $priced['price'], $priced['floor'], $strategy, (int) $own['grow_cap']);
        $current = ($bound === null) ? $current : $bound;
    }

    return array(
        'rule_price' => $priced['price'],
        'floor' => $priced['floor'],
        'current' => $current,
        'figures' => $priced['figures'],
    );
}

/**
 * Raise the alerts of one account's pass: more than half its Win listings
 * lost the buybox, or the read failed three times in a row. The count of
 * failures is kept in the operations alerts' state file.
 *
 * @param array     $account  marketplace_accounts row
 * @param bool|null $ok       the read's outcome, null when no call was made
 * @param array     $summary  mp_repricer_run() summed over the pass
 * @return void
 */
function mp_repricer_alerts($account, $ok, $summary)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/ops_alerts.php');

    $account_id = (int) $account['id'];
    $subject = 'account_' . $account_id;
    $name = (string) ($account['name'] ?? '');

    if (mp_repricer_losing($summary['win'], $summary['win_lost'])) {
        pg_ops_alert(MP_REPRICER_ALERT_LOSING, lang(array(
            'string' => '{var:1}: {var:2} of {var:3} products set to Win lost the buybox in one read.',
            'vars'   => array($name, (int) $summary['win_lost'], (int) $summary['win']),
        )), $subject);
    }

    if ($ok === null) {
        return;
    }

    $known = pg_ops_alert_state();
    $before = (int) ($known['buybox_failures'][(string) $account_id] ?? 0);
    $count = mp_repricer_failures($before, $ok);

    if ($count === $before) {
        return;
    }

    pg_ops_alert_state(function ($state) use ($account_id, $count) {
        $failures = (isset($state['buybox_failures']) && is_array($state['buybox_failures'])) ? $state['buybox_failures'] : array();

        if ($count > 0) {
            $failures[(string) $account_id] = $count;
        } else {
            unset($failures[(string) $account_id]);
        }

        $state['buybox_failures'] = $failures;

        return $state;
    });

    if ($count >= MP_REPRICER_FAILURES) {
        pg_ops_alert(MP_REPRICER_ALERT_FAILING, lang(array(
            'string' => '{var:1}: the buybox could not be read {var:2} times in a row.',
            'vars'   => array($name, $count),
        )), $subject);
    }
}
