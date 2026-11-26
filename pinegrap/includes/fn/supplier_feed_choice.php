<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Several supplier feeds on one product, and prices after a rate change.
 *
 * When two or more feeds carry the same product (the same code or barcode),
 * the product follows one of them for price and stock: the one that has
 * stock with the lowest buy price in the home currency. The choice is made
 * again each time one of the feeds pulls the product, and kept in
 * supplier_feed_items.active.
 *
 * When Update Exchange Rates writes a new rate, the feeds priced in that
 * currency are worked out again from the buy prices their items keep, with
 * no pull: the refresh notes the feeds in a small file and the general job
 * reprices them within its budget.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The item a product follows among the feeds that carry it, pure (F9).
 *
 * Of the items with stock, the lowest buy price wins (an item with no buy
 * price comes last); on a tie the one followed now stays, then the oldest
 * item. With no stock anywhere the one followed now stays, so its feed
 * closes the product; with none followed, the same order as above.
 *
 * @param array $candidates each id, stock, buy (home minor units, null for none)
 * @param int   $current    the item followed now, 0 for none
 * @return int the item's id, 0 for no candidate
 */
function pg_supplier_feed_pick_item($candidates, $current)
{
    $current = (int) $current;
    $pool = array();
    $ids = array();

    foreach ((array) $candidates as $candidate) {
        $ids[] = (int) $candidate['id'];

        if ((int) $candidate['stock'] > 0) {
            $pool[] = $candidate;
        }
    }

    if ($pool === array()) {
        if (in_array($current, $ids, true)) {
            return $current;
        }

        $pool = (array) $candidates;
    }

    $best = 0;
    $best_rank = null;

    foreach ($pool as $candidate) {
        $buy = isset($candidate['buy']) ? $candidate['buy'] : null;
        $rank = array(($buy === null) ? 1 : 0, ($buy === null) ? 0 : (int) $buy, ((int) $candidate['id'] === $current) ? 0 : 1, (int) $candidate['id']);

        if (($best_rank === null) || ($rank < $best_rank)) {
            $best = (int) $candidate['id'];
            $best_rank = $rank;
        }
    }

    return $best;
}

/**
 * Home currency per unit of a currency, by the store's rate.
 *
 * @param string $currency '' for the home currency
 * @return float 0 when the currency has no rate
 */
function pg_supplier_feed_rate_of($currency)
{
    static $rates = array();

    $home = defined('BASE_CURRENCY_CODE') ? strtoupper((string) BASE_CURRENCY_CODE) : '';
    $currency = strtoupper(trim((string) $currency));

    if (($currency === '') || ($currency === $home)) {
        return 1.0;
    }

    if (!isset($rates[$currency])) {
        // currencies.exchange_rate is units of that currency per home unit.
        $per_home = (float) db_value("SELECT exchange_rate FROM currencies WHERE code = '" . escape($currency) . "' LIMIT 1");
        $rates[$currency] = ($per_home > 0) ? 1 / $per_home : 0.0;
    }

    return $rates[$currency];
}

/**
 * A buy price in home minor units for comparing feeds, null for none.
 *
 * @param int    $buy
 * @param string $currency
 * @return int|null
 */
function pg_supplier_feed_buy_home($buy, $currency)
{
    $rate = pg_supplier_feed_rate_of($currency);

    return (((int) $buy > 0) && ($rate > 0)) ? (int) round((int) $buy * $rate) : null;
}

/**
 * Chooses the item each product of these items follows, before they write
 * (F9). This pull's own values count for the step's items; other items (other
 * feeds', and this feed's outside the step) count with what was kept; only
 * feeds that are on take part. When the choice moves to another feed's item, the product is written
 * from that item's kept stock at once and its feed is pulled next, so its
 * price follows too. The item that closed the product hands that over, so the
 * new one may reopen it.
 *
 * @param array $feed     the feed pulling
 * @param array $state    the pull's state; 'wrote' set in place
 * @param array $context  the pull's prices context
 * @param array $items    this feed's items, changed in place (active, closed_by_feed)
 * @param array $values   the record values of each item, same keys as $items
 * @param array $products id => live product
 * @return void
 */
function pg_supplier_feed_choose($feed, &$state, $context, &$items, $values, $products)
{
    $mine = array();

    foreach ($items as $key => $item) {
        $product_id = (int) $item['product_id'];

        if (($product_id > 0) && empty($item['dismissed']) && isset($products[$product_id], $values[$key])) {
            $mine[$product_id][] = $key;
        }
    }

    if ($mine === array()) {
        return;
    }

    $current = array();
    $own = array();

    foreach ($items as $item) {
        $own[] = (int) $item['id'];
    }

    $rows = (array) db_items("SELECT supplier_feed_items.*, supplier_feeds.status AS feed_status
        FROM supplier_feed_items INNER JOIN supplier_feeds ON supplier_feeds.id = supplier_feed_items.feed_id
        WHERE supplier_feed_items.product_id IN ('" . implode("', '", array_keys($mine)) . "') AND supplier_feed_items.dismissed = 0
            AND (supplier_feeds.status = 1 OR supplier_feed_items.active = 1)");

    foreach ($rows as $row) {
        if (!empty($row['active'])) {
            $current[(int) $row['product_id']] = $row;
        }
    }

    $others = pg_supplier_feed_choice_others($rows, $feed['id'], $own);

    foreach ($mine as $product_id => $keys) {
        $candidates = array();

        foreach ($keys as $key) {
            // This pull's stock and buy price, as the item would keep them.
            $plan = pg_supplier_feed_plan_item($items[$key], $products[$product_id], $values[$key], $context);
            $buy = isset($plan['item']['buy_price']) ? $plan['item']['buy_price'] : $items[$key]['buy_price'];
            $candidates[] = array('id' => (int) $items[$key]['id'], 'stock' => (int) $plan['item']['stock'], 'buy' => pg_supplier_feed_buy_home($buy, $context['currency']));
        }

        foreach (isset($others[$product_id]) ? $others[$product_id] : array() as $row) {
            $candidates[] = array('id' => (int) $row['id'], 'stock' => (int) $row['stock'], 'buy' => pg_supplier_feed_buy_home($row['buy_price'], $row['currency']));
        }

        $was = isset($current[$product_id]) ? $current[$product_id] : null;
        $was_id = ($was === null) ? 0 : (int) $was['id'];
        $winner = pg_supplier_feed_pick_item($candidates, $was_id);
        $handed = ($was !== null) && ($winner !== $was_id) && !empty($was['closed_by_feed']);

        foreach ($keys as $key) {
            $items[$key]['active'] = ((int) $items[$key]['id'] === $winner) ? 1 : 0;

            if ($handed && ((int) $items[$key]['id'] === $winner)) {
                $items[$key]['closed_by_feed'] = 1;
            }
        }

        if ($winner === $was_id) {
            continue;
        }

        // Items of this feed matched in this pull keep product 0 until they
        // write, so their own row takes active from $items.
        db("UPDATE supplier_feed_items SET active = IF(id = '" . $winner . "', 1, 0) WHERE product_id = '" . (int) $product_id . "'");

        if ($handed) {
            db("UPDATE supplier_feed_items SET closed_by_feed = IF(id = '" . $winner . "', 1, 0) WHERE id IN ('" . $winner . "', '" . $was_id . "')");
        }

        if (isset($others[$product_id][$winner])) {
            $row = $others[$product_id][$winner];
            $row['active'] = 1;
            $row['closed_by_feed'] = ($handed || !empty($row['closed_by_feed'])) ? 1 : 0;

            if (pg_supplier_feed_follow_other($row, $products)) {
                $state['wrote'] = 1;
            }
        }
    }
}

/**
 * The kept items a product's choice weighs beside the step's own, pure: the
 * items of feeds that are on, this feed's included when the step does not
 * bring them (two records of one feed on one product, in different steps);
 * without them each step would take the product from the other, closing and
 * reopening it on every pull.
 *
 * @param array $rows    items with feed_id, product_id, feed_status
 * @param int   $feed_id the feed pulling
 * @param array $own     ids of the items the step brings
 * @return array product id => item id => row
 */
function pg_supplier_feed_choice_others($rows, $feed_id, $own)
{
    $others = array();
    $own = array_map('intval', (array) $own);

    foreach ((array) $rows as $row) {
        if (((int) $row['feed_status'] !== 1)
            || (((int) $row['feed_id'] === (int) $feed_id) && in_array((int) $row['id'], $own, true))) {
            continue;
        }

        $others[(int) $row['product_id']][(int) $row['id']] = $row;
    }

    return $others;
}

/**
 * Writes a product from another feed's item it now follows: its kept stock,
 * closing or reopening as that feed would; the feed is pulled next so the
 * price follows from a fresh buy price.
 *
 * @param array $item     the item, active
 * @param array $products id => live product
 * @return bool whether the product moved
 */
function pg_supplier_feed_follow_other($item, $products)
{
    static $feeds = array();

    $feed_id = (int) $item['feed_id'];

    if (!isset($feeds[$feed_id])) {
        $feeds[$feed_id] = pg_supplier_feed_get($feed_id);
    }

    if ($feeds[$feed_id] === null) {
        return false;
    }

    $state = array('started' => 0, 'count' => array('records' => 0, 'added' => 0, 'changed' => 0, 'closed' => 0, 'reopened' => 0, 'held' => 0, 'failed' => 0));
    $lines = array();
    $value = array('key' => $item['supplier_key'], 'buy' => null, 'stock' => (int) $item['stock'], 'vat' => null, 'barcode' => '');

    pg_supplier_feed_item_write($feeds[$feed_id], $state, pg_supplier_feed_context($feeds[$feed_id]), $item, $products, $value, $lines, false);

    db("UPDATE supplier_feeds SET next_run_at = '" . time() . "' WHERE id = '" . $feed_id . "' AND status = 1 AND state IS NULL AND next_run_at > '" . time() . "'");

    return !empty($state['wrote']);
}

/**
 * Every rule a feed's records can take: the feed's own and each specific
 * rule with what it does not set taken from the feed's own.
 *
 * @param array $rules the feed's rules JSON decoded
 * @return array list of margin, fixed, rounding, floor_margin
 */
function pg_supplier_feed_rule_candidates($rules)
{
    $list = array(pg_supplier_feed_rule_pick($rules, '', ''));

    foreach ((isset($rules['specific']) && is_array($rules['specific'])) ? $rules['specific'] : array() as $rule) {
        if (is_array($rule)) {
            $list[] = pg_supplier_feed_rule_pick($rules, isset($rule['category']) ? $rule['category'] : '', isset($rule['brand']) ? $rule['brand'] : '');
        }
    }

    return $list;
}

/**
 * A product's selling price at a new rate, from its item's buy price, pure
 * (F10). The item keeps no category or brand, so the rule is the one that
 * gave the product its price at the old rate; when the rules disagree and
 * none or several gave it, the price is left to the next pull.
 *
 * @param int   $buy      the item's buy price, net of VAT
 * @param array $rules    the feed's rules JSON decoded
 * @param float $old_rate home currency per unit before
 * @param float $new_rate home currency per unit now
 * @param int   $current  the product's price
 * @return int|null the new price, null to leave it
 */
function pg_supplier_feed_reprice($buy, $rules, $old_rate, $new_rate, $current)
{
    if (((int) $buy <= 0) || ((float) $new_rate <= 0)) {
        return null;
    }

    $all = array();
    $matching = array();

    foreach (pg_supplier_feed_rule_candidates($rules) as $rule) {
        $price = pg_supplier_feed_price($buy, $rule, $new_rate);
        $all[$price] = true;

        if (((float) $old_rate > 0) && (pg_supplier_feed_price($buy, $rule, $old_rate) === (int) $current)) {
            $matching[$price] = true;
        }
    }

    if (count($all) === 1) {
        $price = (int) key($all);
    } elseif (count($matching) === 1) {
        $price = (int) key($matching);
    } else {
        return null;
    }

    return (($price > 0) && ($price !== (int) $current)) ? $price : null;
}

/**
 * The file that keeps the feeds waiting to be repriced.
 *
 * @return string
 */
function pg_supplier_feed_rates_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_rates.json';
}

/**
 * Notes the feeds priced in currencies whose rate the refresh changed, for the
 * job to reprice. A feed already waiting keeps its first old rate and starts
 * over.
 *
 * @param array $changes currency code => array(old, new), units per home unit
 * @return int feeds noted
 */
function pg_supplier_feed_rates_note($changes)
{
    if (($changes === array()) || !pg_schema_has('supplier_feeds') || !pg_schema_has('supplier_feed_items')) {
        return 0;
    }

    $codes = array_map('escape', array_map('strtoupper', array_keys($changes)));
    $feeds = (array) db_items("SELECT id, currency FROM supplier_feeds WHERE status = 1 AND currency IN ('" . implode("', '", $codes) . "')");

    if ($feeds === array()) {
        return 0;
    }

    $file = pg_supplier_feed_rates_file();
    $waiting = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    $waiting = is_array($waiting) ? $waiting : array();

    foreach ($feeds as $feed) {
        $old = (float) $changes[strtoupper((string) $feed['currency'])][0];
        $id = (string) (int) $feed['id'];

        $waiting[$id] = array(
            'old'   => isset($waiting[$id]['old']) ? (float) $waiting[$id]['old'] : (($old > 0) ? 1 / $old : 0.0),
            'after' => 0,
        );
    }

    @file_put_contents($file, json_encode($waiting), LOCK_EX);

    return count($feeds);
}

/**
 * The job's reprice of the noted feeds within the deadline (F10): each
 * followed item with "Price from the feed" on gets its product's price worked
 * out again at today's rate. A feed in a pull waits for its end; a missing
 * file means the next pull does it instead.
 *
 * @param float $deadline microtime
 * @return int products repriced
 */
function pg_supplier_feed_reprice_tick($deadline)
{
    $file = pg_supplier_feed_rates_file();

    if (!is_file($file)) {
        return 0;
    }

    $waiting = json_decode((string) @file_get_contents($file), true);
    $waiting = is_array($waiting) ? $waiting : array();
    $repriced = 0;

    foreach ($waiting as $id => $note) {
        if (microtime(true) >= $deadline) {
            break;
        }

        $lock = 'pg_supplier_feed_' . (int) $id;

        if ((int) db_value("SELECT GET_LOCK('" . escape($lock) . "', 0)") !== 1) {
            continue;
        }

        $feed = pg_supplier_feed_get((int) $id);

        if (($feed === null) || ((int) $feed['status'] !== 1)) {
            unset($waiting[$id]);
        } elseif ($feed['state'] === null) {
            $after = pg_supplier_feed_reprice_feed($feed, (float) $note['old'], (int) $note['after'], $deadline, $repriced);

            if ($after === null) {
                unset($waiting[$id]);
            } else {
                $waiting[$id]['after'] = $after;
            }
        }

        db("SELECT RELEASE_LOCK('" . escape($lock) . "')");
    }

    if ($waiting === array()) {
        @unlink($file);
    } else {
        @file_put_contents($file, json_encode($waiting), LOCK_EX);
    }

    if (function_exists('pg_price_history_flush')) {
        pg_price_history_flush();
    }

    if (($repriced > 0) && function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return $repriced;
}

/**
 * One feed's reprice, a step at a time until the deadline.
 *
 * @param array $feed
 * @param float $old_rate home currency per unit before the refresh
 * @param int   $after    the last item done
 * @param float $deadline microtime
 * @param int   $repriced counted in place
 * @return int|null the last item done, null when the feed is finished
 */
function pg_supplier_feed_reprice_feed($feed, $old_rate, $after, $deadline, &$repriced)
{
    $context = pg_supplier_feed_context($feed);

    if (($context['rules'] === null) || ($context['rate'] <= 0) || !pg_supplier_feed_writers()) {
        return null;
    }

    while (microtime(true) < $deadline) {
        $items = (array) db_items("SELECT id, product_id, buy_price FROM supplier_feed_items
            WHERE feed_id = '" . (int) $feed['id'] . "' AND id > '" . (int) $after . "'
                AND product_id > 0 AND active = 1 AND dismissed = 0 AND follow_price = 1 AND buy_price > 0
            ORDER BY id LIMIT " . PG_SUPPLIER_FEED_CHUNK);

        if ($items === array()) {
            return null;
        }

        $ids = array();

        foreach ($items as $item) {
            $ids[] = (int) $item['product_id'];
        }

        $products = pg_supplier_feed_products_of($ids);

        foreach ($items as $item) {
            $after = (int) $item['id'];
            $product = isset($products[(int) $item['product_id']]) ? $products[(int) $item['product_id']] : null;

            if (($product === null) || !empty($product['bundle'])) {
                continue;
            }

            $price = pg_supplier_feed_reprice((int) $item['buy_price'], $context['rules'], $old_rate, $context['rate'], (int) $product['price']);

            if (($price !== null) && api_products_set_price((int) $item['product_id'], $price, 'supplier')) {
                $repriced++;
            }
        }
    }

    return $after;
}
