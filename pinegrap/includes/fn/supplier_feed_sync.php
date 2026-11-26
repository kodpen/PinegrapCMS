<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The supplier feed sync: every feed that is on is pulled every N hours from
 * the general job (job.php), a short budget a tick, one lock per feed, the
 * place in the file kept in supplier_feeds.state between ticks.
 *
 * A pull has three phases over one downloaded file:
 *   - scan: every record read once with nothing written to the catalogue;
 *     the items seen get this pull's seen_at. The brakes are judged here: an
 *     unreadable or empty feed, a mapped field no record has, more than half
 *     the matched products dropped (missing, or stock 0 where they had stock).
 *     A brake writes nothing, pauses the feed (status 2) and sends the
 *     supplier_feed operations alert.
 *   - apply: the records again, matched to products by the code to
 *     products.name, else by barcode; the price through the API's product
 *     writer (api_products_set_price()), stock through api_inventory_apply(),
 *     closing and reopening. A buy price under half or over ten times its
 *     last value is held on the item and the old price stays.
 *   - close: the matched products missing from the pull are closed.
 *   - drafts: the records no product matched become products switched off,
 *     records sharing a model code one variant set (supplier_feed_drafts.php).
 *
 * Only the item a product follows (active) writes to it. Each pull is one
 * supplier_feed_runs row; rows older than 90 days are swept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_drafts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_choice.php');

// Records read and written in one step of a pull.
define('PG_SUPPLIER_FEED_CHUNK', 200);

// Days a run row is kept.
define('PG_SUPPLIER_FEED_RUN_DAYS', 90);

// Failed lines a run keeps.
define('PG_SUPPLIER_FEED_FAILED_LINES', 500);

/**
 * A record's values for the sync, by the mapping's fields.
 *
 * @param array $record a flat record
 * @param array $fields product field => the feed's field
 * @return array key, barcode, buy (minor units, null for none or 0), stock, vat (percent
 *               or null), name, category, brand, model, description, images
 */
function pg_supplier_feed_record_values($record, $fields)
{
    $get = function ($name) use ($record, $fields) {
        $key = isset($fields[$name]) ? (string) $fields[$name] : '';

        return (($key !== '') && isset($record[$key])) ? trim((string) $record[$key]) : '';
    };

    $vat = null;

    if ($get('vat') !== '') {
        $vat_minor = pg_supplier_feed_parse_amount($get('vat'), 2);
        $vat = ($vat_minor === null) ? null : $vat_minor / 100.0;
    }

    // A price of 0 is a supplier's placeholder, not a buy price: it would
    // replace the last one, or count as a held price when there was one.
    $buy = ($get('buy_price') === '') ? null : pg_supplier_feed_parse_amount($get('buy_price'), 2);

    return array(
        'key'      => $get('supplier_key'),
        'barcode'  => $get('barcode'),
        'buy'      => ($buy === 0) ? null : $buy,
        'stock'    => pg_supplier_feed_parse_stock($get('stock')),
        'vat'      => $vat,
        'name'     => $get('name'),
        'category' => $get('category'),
        'brand'    => $get('brand'),
        'model'    => $get('model_code'),
        'description' => $get('description'),
        'images'   => pg_supplier_feed_record_images($record, isset($fields['image']) ? $fields['image'] : ''),
    );
}

/**
 * The mapped fields no record has (a brake): a renamed or cut feed.
 *
 * @param array $records
 * @param array $fields product field => the feed's field, '' for unmapped
 * @return array product fields, in the mapping's order; empty for no records
 */
function pg_supplier_feed_missing_fields($records, $fields)
{
    $missing = array();

    if ($records === array()) {
        return $missing;
    }

    foreach ((array) $fields as $field => $key) {
        if ((string) $key === '') {
            continue;
        }

        $found = false;

        foreach ($records as $record) {
            if (array_key_exists((string) $key, $record)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $missing[] = $field;
        }
    }

    return $missing;
}

/**
 * Whether a pull drops more than half the products the feed matched (a brake).
 *
 * @param int $matched products the feed followed before the pull
 * @param int $dropped of them, missing from the pull or at stock 0 now
 * @return bool
 */
function pg_supplier_feed_drop_brake($matched, $dropped)
{
    return ((int) $matched > 0) && ((int) $dropped * 2 > (int) $matched);
}

/**
 * The buy price a record gives its item, followed or not: net of VAT when
 * the feed's prices include it (the record's VAT, else the product's own
 * rate), held under half or over ten times the item's last one.
 *
 * @param array      $item    buy_price
 * @param array|null $product tax_rate; null when the item has no live product
 * @param array      $values  buy (null when not read), vat
 * @param array      $context vat_included
 * @return array buy (net, null for none), item (columns to write), held (bool)
 */
function pg_supplier_feed_item_buy($item, $product, $values, $context)
{
    $out = array('buy' => null, 'item' => array(), 'held' => false);
    $buy = isset($values['buy']) ? $values['buy'] : null;

    if ($buy === null) {
        return $out;
    }

    if (!empty($context['vat_included'])) {
        $vat = isset($values['vat']) ? $values['vat'] : null;

        if (($vat === null) && is_array($product) && isset($product['tax_rate']) && ((string) $product['tax_rate'] !== '')) {
            $vat = (float) $product['tax_rate'];
        }

        if ($vat !== null) {
            $buy = pg_supplier_feed_net_of_vat($buy, $vat);
        }
    }

    $out['buy'] = (int) $buy;

    if (pg_supplier_feed_price_held((int) $item['buy_price'], $buy)) {
        $out['held'] = true;
        $out['item']['held_price'] = (int) $buy;
    } else {
        $out['item']['buy_price'] = (int) $buy;
        $out['item']['held_price'] = 0;
    }

    return $out;
}

/**
 * What one record does to its product, nothing written (F6, F7, F13).
 *
 * The buy price is made net of VAT when the feed's prices include it (the
 * record's VAT, else the product's own rate). A held buy price leaves the
 * last one and the product's price alone. The price follows only with a saved
 * rule, a rate and "Price from the feed" on. Stock is set only on a tracked
 * product. Stock 0 closes: a tracked product gets stock 0, an untracked one
 * is turned off. Stock back reopens only what the feed itself closed, so a
 * person's off stays off.
 *
 * @param array $item    buy_price, held_price, stock, follow_price, closed_by_feed
 * @param array $product price, inventory, inventory_quantity, enabled, tax_rate
 * @param array $values  buy (null when not read), stock, vat
 * @param array $context rule (null for none), rate, vat_included,
 *                       home_decimals, feed_decimals
 * @return array price (int or null), stock (int or null), enable (0, 1 or
 *               null), item (columns to write), outcome ('', 'changed',
 *               'closed', 'reopened'), held (bool)
 */
function pg_supplier_feed_plan_item($item, $product, $values, $context)
{
    $kept = pg_supplier_feed_item_buy($item, $product, $values, $context);
    $buy = $kept['buy'];
    $out = array('price' => null, 'stock' => null, 'enable' => null, 'item' => $kept['item'], 'outcome' => '', 'held' => $kept['held']);

    if (($buy !== null) && !$kept['held'] && !empty($item['follow_price']) && is_array($context['rule'])) {
        $price = pg_supplier_feed_price($buy, $context['rule'], $context['rate'], $context['home_decimals'], $context['feed_decimals']);

        if (($price > 0) && ($price !== (int) $product['price'])) {
            $out['price'] = $price;
        }
    }

    $stock = max(0, (int) $values['stock']);
    $closed_by_feed = !empty($item['closed_by_feed']);
    $out['item']['stock'] = $stock;

    if (!empty($product['inventory'])) {
        $quantity = (int) $product['inventory_quantity'];

        if ($stock !== $quantity) {
            $out['stock'] = $stock;
        }

        if (($stock === 0) && ($quantity > 0)) {
            $out['outcome'] = 'closed';
            $out['item']['closed_by_feed'] = 1;
        } elseif (($stock > 0) && $closed_by_feed) {
            $out['item']['closed_by_feed'] = 0;
            $out['outcome'] = ($quantity === 0) ? 'reopened' : '';
        }
    } else {
        $enabled = !empty($product['enabled']);

        if (($stock === 0) && $enabled) {
            $out['enable'] = 0;
            $out['outcome'] = 'closed';
            $out['item']['closed_by_feed'] = 1;
        } elseif (($stock > 0) && $closed_by_feed) {
            $out['item']['closed_by_feed'] = 0;

            if (!$enabled) {
                $out['enable'] = 1;
                $out['outcome'] = 'reopened';
            }
        }
    }

    if (($out['outcome'] === '') && (($out['price'] !== null) || ($out['stock'] !== null))) {
        $out['outcome'] = 'changed';
    }

    return $out;
}

/**
 * The file a pull reads, apart from the screen's first read.
 *
 * @param int $id
 * @return string
 */
function pg_supplier_feed_pull_file($id)
{
    return PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_' . (int) $id . '_pull.dat';
}

/**
 * The general job's tick: the feeds whose pull is running or due, within
 * $budget seconds, then the run rows past 90 days.
 *
 * @param float $budget seconds
 * @return array pulled (feeds worked), swept (run rows deleted)
 */
function pg_supplier_feed_job_tick($budget)
{
    $answer = array('pulled' => 0, 'swept' => 0);

    if (!pg_supplier_feed_ready()) {
        return $answer;
    }

    $now = time();
    $deadline = microtime(true) + max(0, (float) $budget);

    // A feed switched off or read again while a pull ran: the pull ends.
    foreach ((array) db_items("SELECT id, state FROM supplier_feeds WHERE status <> 1 AND state IS NOT NULL LIMIT 20") as $feed) {
        pg_supplier_feed_pull_drop($feed, lang('The supplier feed was switched off during the pull.'));
    }

    $due = (array) db_items(
        "SELECT id FROM supplier_feeds
        WHERE status = 1 AND (state IS NOT NULL OR next_run_at <= '" . $now . "')
        ORDER BY (state IS NULL), next_run_at, id
        LIMIT 20");

    foreach ($due as $feed) {
        if (microtime(true) >= $deadline) {
            break;
        }

        pg_supplier_feed_pull((int) $feed['id'], $deadline);
        $answer['pulled']++;
    }

    // Prices after Update Exchange Rates wrote a rate (F10).
    pg_supplier_feed_reprice_tick($deadline);

    if (db("DELETE FROM supplier_feed_runs WHERE started_at < '" . ($now - PG_SUPPLIER_FEED_RUN_DAYS * 86400) . "' LIMIT 1000") !== false) {
        $answer['swept'] = (int) mysqli_affected_rows(db::$con);
    }

    return $answer;
}

/**
 * Works one feed's pull until $deadline, under the feed's lock: starts it
 * when none runs, goes on from the kept place otherwise.
 *
 * @param int   $feed_id
 * @param float $deadline microtime
 * @param int   $kind     0 scheduled, 1 by hand or the API
 * @param bool  $drop_ok  a person said the products may drop: no drop brake
 * @return string 'locked', 'off', 'running', 'done', 'braked'
 */
function pg_supplier_feed_pull($feed_id, $deadline, $kind = 0, $drop_ok = false)
{
    $lock = 'pg_supplier_feed_' . (int) $feed_id;

    if ((int) db_value("SELECT GET_LOCK('" . escape($lock) . "', 0)") !== 1) {
        return 'locked';
    }

    $result = pg_supplier_feed_pull_locked((int) $feed_id, (float) $deadline, (int) $kind, (bool) $drop_ok);

    db("SELECT RELEASE_LOCK('" . escape($lock) . "')");

    return $result;
}

/**
 * pg_supplier_feed_pull()'s work, the lock held.
 *
 * @param int   $feed_id
 * @param float $deadline
 * @param int   $kind
 * @param bool  $drop_ok
 * @return string
 */
function pg_supplier_feed_pull_locked($feed_id, $deadline, $kind, $drop_ok)
{
    $feed = pg_supplier_feed_get($feed_id);

    if (($feed === null) || ((int) $feed['status'] !== 1)) {
        return 'off';
    }

    $mapping = pg_supplier_feed_mapping_decode($feed['mapping']);

    if (empty($mapping['confirmed']) || !pg_supplier_feed_writers()) {
        return 'off';
    }

    $state = json_decode((string) $feed['state'], true);

    if (is_array($state) && (!isset($state['file'], $state['run_id'], $state['count']) || !is_file((string) $state['file']))) {
        // The file went (data/temp emptied): this pull ends, the next starts over.
        pg_supplier_feed_pull_drop($feed, lang('The pull was cut off and starts again.'));
        $state = null;
    }

    if (!is_array($state)) {
        $state = pg_supplier_feed_pull_start($feed, $kind, $drop_ok);

        if ($state === null) {
            return 'braked';
        }
    }

    $context = pg_supplier_feed_context($feed);
    $lines = array();

    while (microtime(true) < $deadline) {
        if ($state['phase'] === 'close') {
            if (!pg_supplier_feed_close_chunk($feed, $state, $context, $lines)) {
                $state['phase'] = 'drafts';
                $state['bucket'] = 0;
            }

            continue;
        }

        if ($state['phase'] === 'drafts') {
            if (!pg_supplier_feed_drafts_chunk($feed, $state, $context, $mapping, $deadline, $lines)) {
                pg_supplier_feed_pull_finish($feed, $state, $lines);

                return 'done';
            }

            pg_supplier_feed_pull_save($feed, $state, $lines);
            $lines = array();

            continue;
        }

        $read = pg_supplier_feed_read($state['file'], $mapping['format'], $mapping['record_path'], $state['cursor'], PG_SUPPLIER_FEED_CHUNK);

        if ($read['error'] !== '') {
            pg_supplier_feed_brake($feed, $state, pg_supplier_feed_error_text($read['error']), $lines);

            return 'braked';
        }

        if ($state['phase'] === 'scan') {
            $reason = pg_supplier_feed_scan_chunk($feed, $mapping, $state, $read['records']);

            if ($reason !== '') {
                pg_supplier_feed_brake($feed, $state, $reason, $lines);

                return 'braked';
            }
        } else {
            pg_supplier_feed_apply_chunk($feed, $mapping, $state, $context, $read['records'], $lines);
        }

        $state['cursor'] = $read['cursor'];

        if ($read['cursor'] === null) {
            if ($state['phase'] === 'scan') {
                $reason = pg_supplier_feed_scan_end($feed, $state);

                if ($reason !== '') {
                    pg_supplier_feed_brake($feed, $state, $reason, $lines);

                    return 'braked';
                }

                $state['phase'] = 'apply';
                $state['cursor'] = array();
            } else {
                $state['phase'] = 'close';
            }
        }

        pg_supplier_feed_pull_save($feed, $state, $lines);
        $lines = array();
    }

    pg_supplier_feed_pull_save($feed, $state, $lines);

    return 'running';
}

/**
 * Starts a pull: a run row, the download into the pull's own file.
 * A failed download is a brake.
 *
 * @param array $feed
 * @param int   $kind
 * @param bool  $drop_ok
 * @return array|null the state, null after a brake
 */
function pg_supplier_feed_pull_start($feed, $kind, $drop_ok)
{
    $now = time();

    db("INSERT INTO supplier_feed_runs SET feed_id = '" . (int) $feed['id'] . "', kind = '" . (int) $kind . "', status = 0, started_at = '" . $now . "'");

    $state = array(
        'run_id'   => (int) mysqli_insert_id(db::$con),
        'started'  => $now,
        'file'     => pg_supplier_feed_pull_file($feed['id']),
        'phase'    => 'scan',
        'cursor'   => array(),
        'checked'  => 0,
        'drop_ok'  => $drop_ok ? 1 : 0,
        'dropping' => 0,
        'after'    => 0,
        'count'    => array('records' => 0, 'added' => 0, 'changed' => 0, 'closed' => 0, 'reopened' => 0, 'held' => 0, 'failed' => 0),
    );

    // A pull dropped before its drafts were made leaves its spool behind.
    pg_supplier_feed_spool_clear($feed['id']);

    // Its own file, not the screen's: a Test pull meanwhile does not cut it.
    $download = pg_supplier_feed_download($feed['id'], pg_supplier_feed_secret_decode($feed['secret']), !empty($feed['allow_http']), $state['file']);

    if (!$download['ok']) {
        pg_supplier_feed_brake($feed, $state, $download['error'], array());

        return null;
    }

    pg_supplier_feed_pull_save($feed, $state, array());

    return $state;
}

/**
 * What prices are worked out with: the saved rule (null until one is saved,
 * so no price is written before), the rate of the feed's currency.
 *
 * @param array $feed
 * @return array rules, rate, vat_included, home_decimals, feed_decimals, currency
 */
function pg_supplier_feed_context($feed)
{
    $rules = json_decode((string) $feed['rules'], true);
    $home = defined('BASE_CURRENCY_CODE') ? strtoupper((string) BASE_CURRENCY_CODE) : '';
    $currency = strtoupper(trim((string) $feed['currency']));
    $rate = 1.0;

    if (($currency !== '') && ($currency !== $home)) {
        // currencies.exchange_rate is units of that currency per home unit.
        $per_home = (float) db_value("SELECT exchange_rate FROM currencies WHERE code = '" . escape($currency) . "' LIMIT 1");
        $rate = ($per_home > 0) ? 1 / $per_home : 0.0;
    }

    return array(
        'rules'         => (is_array($rules) && isset($rules['margin'])) ? $rules : null,
        'rule'          => null,
        'rate'          => $rate,
        'vat_included'  => !empty($feed['vat_included']),
        'home_decimals' => 2,
        'feed_decimals' => 2,
        'currency'      => ($currency !== '') ? $currency : $home,
    );
}

/**
 * A key as the items keep it: trimmed, 100 letters at most.
 *
 * @param string $key
 * @return string
 */
function pg_supplier_feed_item_key($key)
{
    $key = trim((string) $key);

    return function_exists('mb_substr') ? mb_substr($key, 0, 100, 'UTF-8') : substr($key, 0, 100);
}

/**
 * The items of these keys, by the key in lower case.
 *
 * @param int   $feed_id
 * @param array $keys
 * @return array
 */
function pg_supplier_feed_items_of($feed_id, $keys)
{
    $items = array();

    if ($keys === array()) {
        return $items;
    }

    foreach ((array) db_items("SELECT * FROM supplier_feed_items WHERE feed_id = '" . (int) $feed_id . "'
        AND supplier_key IN ('" . implode("', '", array_map('escape', $keys)) . "')") as $item) {
        $items[pg_supplier_feed_lower($item['supplier_key'])] = $item;
    }

    return $items;
}

/**
 * The scan of one step's records: the first records with records checks the
 * mapped fields; the items seen get this pull's seen_at; a product with
 * stock now at 0 counts as dropping.
 *
 * @param array $feed
 * @param array $mapping
 * @param array $state   changed in place
 * @param array $records
 * @return string a brake's reason, '' for none
 */
function pg_supplier_feed_scan_chunk($feed, $mapping, &$state, $records)
{
    if ($records === array()) {
        return '';
    }

    if (empty($state['checked'])) {
        $missing = pg_supplier_feed_missing_fields($records, $mapping['fields']);

        if ($missing !== array()) {
            $labels = pg_supplier_feed_field_labels();
            $names = array();

            foreach ($missing as $field) {
                $names[] = isset($labels[$field]) ? $labels[$field] : $field;
            }

            return lang(array('string' => 'The feed no longer has these fields: {var:1}', 'vars' => array(implode(', ', $names))));
        }

        $state['checked'] = 1;
    }

    $stock = array();

    foreach ($records as $record) {
        $state['count']['records']++;
        $values = pg_supplier_feed_record_values($record, $mapping['fields']);
        $key = pg_supplier_feed_item_key($values['key']);

        if ($key !== '') {
            $stock[$key] = $values['stock'];
        }
    }

    $items = pg_supplier_feed_items_of($feed['id'], array_keys($stock));

    foreach ($stock as $key => $quantity) {
        $item = isset($items[pg_supplier_feed_lower($key)]) ? $items[pg_supplier_feed_lower($key)] : null;

        if (($item !== null) && ((int) $item['product_id'] > 0) && !empty($item['active']) && empty($item['dismissed'])
            && ($quantity === 0) && ((int) $item['stock'] > 0)) {
            $state['dropping']++;
        }
    }

    if ($items !== array()) {
        db("UPDATE supplier_feed_items SET seen_at = '" . (int) $state['started'] . "'
            WHERE feed_id = '" . (int) $feed['id'] . "' AND supplier_key IN ('" . implode("', '", array_map('escape', array_keys($stock))) . "')");
    }

    return '';
}

/**
 * The end of the scan: the empty-feed and the mass-drop brakes.
 *
 * @param array $feed
 * @param array $state
 * @return string a brake's reason, '' for none
 */
function pg_supplier_feed_scan_end($feed, $state)
{
    if ((int) $state['count']['records'] === 0) {
        return pg_supplier_feed_error_text('no_records');
    }

    if (!empty($state['drop_ok'])) {
        return '';
    }

    $where = "feed_id = '" . (int) $feed['id'] . "' AND product_id > 0 AND active = 1 AND dismissed = 0";
    $matched = (int) db_value("SELECT COUNT(*) FROM supplier_feed_items WHERE " . $where . " AND created_at < '" . (int) $state['started'] . "'");
    $missing = (int) db_value("SELECT COUNT(*) FROM supplier_feed_items WHERE " . $where . " AND created_at < '" . (int) $state['started'] . "' AND seen_at < '" . (int) $state['started'] . "'");
    $dropped = $missing + (int) $state['dropping'];

    if (pg_supplier_feed_drop_brake($matched, $dropped)) {
        return lang(array('string' => '{var:1} of the {var:2} products the feed follows would be closed in one pull.', 'vars' => array($dropped, $matched)));
    }

    return '';
}

/**
 * The live products of these ids.
 *
 * @param array $ids
 * @return array id => price, inventory, inventory_quantity, enabled, tax_rate, bundle
 */
function pg_supplier_feed_products_of($ids)
{
    $products = array();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if ($ids === array()) {
        return $products;
    }

    $bundle = (function_exists('pg_bundles_ready') && pg_bundles_ready()) ? 'bundle' : '0 AS bundle';

    foreach ((array) db_items("SELECT id, name, price, inventory, inventory_quantity, enabled, tax_rate, " . $bundle . "
        FROM products WHERE id IN ('" . implode("', '", $ids) . "')" . pg_catalog_bin_live_sql('products')) as $product) {
        $products[(int) $product['id']] = $product;
    }

    return $products;
}

/**
 * Product ids by code, the name folded as the match folds it, pure.
 *
 * @param array $rows id, name
 * @return array lower name => list of ids
 */
function pg_supplier_feed_match_group($rows)
{
    $grouped = array();

    foreach ((array) $rows as $row) {
        $grouped[pg_supplier_feed_lower($row['name'])][] = (int) $row['id'];
    }

    return $grouped;
}

/**
 * The product a record matches, pure: the one product whose name is the
 * code; with two or more, the one the record's barcode belongs to when it
 * belongs to exactly one of them, else none (ambiguous); with none by name,
 * the first product of the barcode.
 *
 * @param array $code_ids    products whose name is the code
 * @param array $barcode_ids products holding the record's barcode
 * @return array id (0 for none), ambiguous (bool)
 */
function pg_supplier_feed_match_pick($code_ids, $barcode_ids)
{
    $code_ids = array_values(array_unique(array_map('intval', (array) $code_ids)));
    $barcode_ids = array_values(array_unique(array_map('intval', (array) $barcode_ids)));

    if (count($code_ids) === 1) {
        return array('id' => $code_ids[0], 'ambiguous' => false);
    }

    if (count($code_ids) > 1) {
        if ((count($barcode_ids) === 1) && in_array($barcode_ids[0], $code_ids, true)) {
            return array('id' => $barcode_ids[0], 'ambiguous' => false);
        }

        return array('id' => 0, 'ambiguous' => true);
    }

    return array('id' => ($barcode_ids !== array()) ? $barcode_ids[0] : 0, 'ambiguous' => false);
}

/**
 * Products found by code (products.name) or barcode for unmatched keys.
 * Architect (2026-11-26): a code that is the name of two live products
 * matches none unless the barcode settles it; such keys go to $ambiguous.
 *
 * @param array $values    lower key => record values
 * @param array $ambiguous lower keys two products hold, set
 * @return array lower key => product id
 */
function pg_supplier_feed_match($values, &$ambiguous = array())
{
    $found = array();
    $ambiguous = array();
    $codes = array();
    $barcodes = array();
    $by_name = array();
    $by_barcode = array();

    foreach ($values as $value) {
        $codes[] = $value['key'];

        if ($value['barcode'] !== '') {
            $barcodes[] = $value['barcode'];
        }
    }

    if ($codes !== array()) {
        $by_name = pg_supplier_feed_match_group((array) db_items("SELECT id, name FROM products WHERE name IN ('" . implode("', '", array_map('escape', $codes)) . "')" . pg_catalog_bin_live_sql('products') . " ORDER BY id"));
    }

    if ($barcodes !== array()) {
        foreach ((array) db_items("SELECT barcode, product_id FROM product_barcodes WHERE barcode IN ('" . implode("', '", array_map('escape', array_unique($barcodes))) . "') ORDER BY id") as $row) {
            $by_barcode[(string) $row['barcode']][] = (int) $row['product_id'];
        }
    }

    foreach ($values as $lower => $value) {
        $pick = pg_supplier_feed_match_pick(
            isset($by_name[$lower]) ? $by_name[$lower] : array(),
            (($value['barcode'] !== '') && isset($by_barcode[$value['barcode']])) ? $by_barcode[$value['barcode']] : array()
        );

        if ($pick['id'] > 0) {
            $found[$lower] = $pick['id'];
        } elseif ($pick['ambiguous']) {
            $ambiguous[$lower] = true;
        }
    }

    return $found;
}

/**
 * The apply of one step's records: items made or matched, then each
 * followed product written as pg_supplier_feed_plan_item() says.
 *
 * @param array $feed
 * @param array $mapping
 * @param array $state   changed in place
 * @param array $context
 * @param array $records
 * @param array $lines   failed lines, added to
 * @return void
 */
function pg_supplier_feed_apply_chunk($feed, $mapping, &$state, &$context, $records, &$lines)
{
    $values = array();
    $now = time();

    foreach ($records as $record) {
        $value = pg_supplier_feed_record_values($record, $mapping['fields']);
        $value['key'] = pg_supplier_feed_item_key($value['key']);

        if ($value['key'] === '') {
            $state['count']['failed']++;
            $lines[] = array('line' => ($value['name'] !== '') ? $value['name'] : '-', 'reason' => lang('No supplier code'));
            continue;
        }

        $values[pg_supplier_feed_lower($value['key'])] = $value;
    }

    $keys = array();

    foreach ($values as $value) {
        $keys[] = $value['key'];
    }

    $items = pg_supplier_feed_items_of($feed['id'], $keys);
    $unmatched = array();

    foreach ($values as $lower => $value) {
        if (!isset($items[$lower]) || (((int) $items[$lower]['product_id'] === 0) && empty($items[$lower]['dismissed']))) {
            $unmatched[$lower] = $value;
        }
    }

    $found = pg_supplier_feed_match($unmatched, $ambiguous);

    foreach ($values as $lower => $value) {
        if (!isset($items[$lower])) {
            db("INSERT INTO supplier_feed_items SET feed_id = '" . (int) $feed['id'] . "', supplier_key = '" . escape($value['key']) . "',
                currency = '" . escape($context['currency']) . "', seen_at = '" . (int) $state['started'] . "', created_at = '" . $now . "'");

            $items[$lower] = array(
                'id' => (int) mysqli_insert_id(db::$con), 'product_id' => 0, 'buy_price' => 0, 'held_price' => 0, 'stock' => 0,
                'follow_price' => 1, 'closed_by_feed' => 0, 'active' => 0, 'dismissed' => 0,
            );
        }

        if (isset($found[$lower]) && ((int) $items[$lower]['product_id'] === 0)) {
            $items[$lower]['product_id'] = $found[$lower];
            $items[$lower]['active'] = 0;
            $items[$lower]['matched'] = 1;
        }
    }

    $product_ids = array();

    foreach ($items as $item) {
        $product_ids[] = (int) $item['product_id'];
    }

    $products = pg_supplier_feed_products_of($product_ids);
    $context['rule'] = null;
    $spool = array();
    pg_supplier_feed_choose($feed, $state, $context, $items, $values, $products);

    foreach ($values as $lower => $value) {
        if ($context['rules'] !== null) {
            $context['rule'] = pg_supplier_feed_rule_pick($context['rules'], $value['category'], $value['brand']);
        }

        if (($value['buy'] === null) && ((string) $mapping['fields']['buy_price'] !== '')) {
            $lines[] = array('line' => $value['key'], 'reason' => lang('No buy price'));
            $state['count']['failed']++;
        }

        pg_supplier_feed_item_write($feed, $state, $context, $items[$lower], $products, $value, $lines);

        if (isset($ambiguous[$lower])) {
            // Not a draft: one named after the code would be a third product
            // of that name. Not dismissed: the next pull tries again.
            $lines[] = array('line' => $value['key'], 'reason' => lang('Two products have this code'));
            $state['count']['failed']++;
            continue;
        }

        $spool_line = pg_supplier_feed_spool_line($value, $items[$lower]);

        if ($spool_line !== null) {
            $spool[] = $spool_line;
        }
    }

    pg_supplier_feed_spool_add($feed['id'], $spool);
}

/**
 * The close of the matched products missing from the pull, one step.
 *
 * @param array $feed
 * @param array $state   changed in place
 * @param array $context
 * @param array $lines
 * @return bool whether another step may find more
 */
function pg_supplier_feed_close_chunk($feed, &$state, $context, &$lines)
{
    $items = (array) db_items("SELECT * FROM supplier_feed_items
        WHERE feed_id = '" . (int) $feed['id'] . "' AND id > '" . (int) $state['after'] . "'
            AND product_id > 0 AND active = 1 AND dismissed = 0 AND seen_at < '" . (int) $state['started'] . "'
        ORDER BY id LIMIT " . PG_SUPPLIER_FEED_CHUNK);

    if ($items === array()) {
        return false;
    }

    $ids = array();

    foreach ($items as $item) {
        $ids[] = (int) $item['product_id'];
    }

    $products = pg_supplier_feed_products_of($ids);
    $values = array();

    foreach ($items as $key => $item) {
        $values[$key] = array('key' => $item['supplier_key'], 'buy' => null, 'stock' => 0, 'vat' => null, 'barcode' => '');
    }

    pg_supplier_feed_choose($feed, $state, $context, $items, $values, $products);

    foreach ($items as $key => $item) {
        $state['after'] = (int) $item['id'];
        pg_supplier_feed_item_write($feed, $state, $context, $item, $products, $values[$key], $lines, false);
    }

    return true;
}

/**
 * One item's writes: its product's price, stock and on/off when the item is
 * followed, then the item's own row.
 *
 * @param array $feed
 * @param array $state    the counts change in place
 * @param array $context
 * @param array $item
 * @param array $products id => live product
 * @param array $value    the record's values
 * @param array $lines    failed lines, added to
 * @param bool  $seen     the record was in the pull
 * @return void
 */
function pg_supplier_feed_item_write($feed, &$state, $context, $item, $products, $value, &$lines, $seen = true)
{
    $product_id = (int) $item['product_id'];
    $columns = array();
    $now = time();

    if ($seen) {
        $columns['seen_at'] = (int) $state['started'];

        if ($value['barcode'] !== '') {
            $columns['barcode'] = function_exists('mb_substr') ? mb_substr($value['barcode'], 0, 50, 'UTF-8') : substr($value['barcode'], 0, 50);
        }
    }

    if (!empty($item['matched'])) {
        $columns['product_id'] = $product_id;
        $columns['active'] = (int) $item['active'];
    }

    if (($product_id > 0) && !isset($products[$product_id])
        && ((int) db_value("SELECT COUNT(*) FROM products WHERE id = '" . $product_id . "'") === 0)) {
        // A person deleted the product: the record makes no new draft.
        $columns['product_id'] = 0;
        $columns['active'] = 0;
        $columns['dismissed'] = 1;
        $product_id = 0;
    }

    $product = isset($products[$product_id]) ? $products[$product_id] : null;

    if (($product === null) || empty($item['active']) || !empty($item['dismissed']) || !empty($product['bundle'])) {
        // Not followed: only the record's values are kept, the buy price as
        // a followed item keeps it, so the choice between feeds compares net
        // prices and a matched product's price out of range waits for a
        // person here too. An item with no product holds nothing.
        $kept = pg_supplier_feed_item_buy($item, $product, $value, $context);

        if ($kept['held'] && ($product === null)) {
            $kept['item'] = array('buy_price' => $kept['buy'], 'held_price' => 0);
        } elseif ($kept['held']) {
            $state['count']['held']++;
        }

        $columns = array_merge($columns, $kept['item']);
        $columns['stock'] = max(0, (int) $value['stock']);
    } else {
        $plan = pg_supplier_feed_plan_item($item, $product, $value, $context);
        $columns = array_merge($columns, $plan['item']);
        $failed = array();

        if (($plan['price'] !== null) && !api_products_set_price($product_id, $plan['price'], 'supplier')) {
            $failed[] = lang('The price could not be written.');
        }

        if ($plan['stock'] !== null) {
            $result = api_inventory_apply($product_id, '', 'set', $plan['stock'], false, false, 0, 'supplier_feed', 'feed:' . (int) $feed['id']);

            if ($result['status'] !== 'ok') {
                $failed[] = lang('The stock could not be written.');
            }
        }

        if ($plan['enable'] !== null) {
            api_products_set_enabled($product_id, $plan['enable'], lang(array('string' => 'the supplier feed {var:1}', 'vars' => array($feed['name']))));
        }

        if ($failed !== array()) {
            $state['count']['failed']++;
            $lines[] = array('line' => $value['key'], 'reason' => implode(' ', $failed));
        }

        if ($plan['outcome'] !== '') {
            $state['count'][$plan['outcome']]++;
            $columns['changed_at'] = $now;
            $state['wrote'] = 1;
        }

        if ($plan['held']) {
            $state['count']['held']++;
        }
    }

    $set = array();

    foreach ($columns as $column => $content) {
        $set[] = $column . " = '" . escape((string) $content) . "'";
    }

    if (($set !== array()) && ((int) $item['id'] > 0)) {
        db("UPDATE supplier_feed_items SET " . implode(', ', $set) . " WHERE id = '" . (int) $item['id'] . "'");
    }
}

/**
 * Keeps the pull's place and its run row's counts and failed lines.
 *
 * @param array $feed
 * @param array $state
 * @param array $lines
 * @return void
 */
function pg_supplier_feed_pull_save($feed, $state, $lines)
{
    if (function_exists('pg_price_history_flush')) {
        pg_price_history_flush();
    }

    db("UPDATE supplier_feeds SET state = '" . escape((string) json_encode($state)) . "' WHERE id = '" . (int) $feed['id'] . "'");
    pg_supplier_feed_run_write($state, $lines, 0);
}

/**
 * Writes the run row: counts, status, the failed lines added.
 *
 * @param array       $state
 * @param array       $lines
 * @param int         $status 0 running, 1 done, 2 stopped by a brake, 3 failed
 * @param string|null $reason
 * @return void
 */
function pg_supplier_feed_run_write($state, $lines, $status, $reason = null)
{
    $run = db_item("SELECT detail FROM supplier_feed_runs WHERE id = '" . (int) $state['run_id'] . "' LIMIT 1");

    if (!is_array($run)) {
        return;
    }

    $set = array("status = '" . (int) $status . "'");

    foreach ($state['count'] as $column => $count) {
        $set[] = $column . " = '" . (int) $count . "'";
    }

    if ($status > 0) {
        $set[] = "ended_at = '" . time() . "'";
    }

    if (($lines !== array()) || ($reason !== null)) {
        $set[] = "detail = '" . escape(pg_supplier_feed_detail_add((string) $run['detail'], $lines, PG_SUPPLIER_FEED_FAILED_LINES, $reason)) . "'";
    }

    db("UPDATE supplier_feed_runs SET " . implode(', ', $set) . " WHERE id = '" . (int) $state['run_id'] . "'");
}

/**
 * Ends a pull that went through: the run done, the next pull in N hours.
 *
 * @param array $feed
 * @param array $state
 * @param array $lines
 * @return void
 */
function pg_supplier_feed_pull_finish($feed, $state, $lines)
{
    $now = time();

    if (function_exists('pg_price_history_flush')) {
        pg_price_history_flush();
    }

    pg_supplier_feed_run_write($state, $lines, 1);

    db("UPDATE supplier_feeds SET state = NULL, last_run_at = '" . $now . "',
        next_run_at = '" . ($now + max(1, (int) $feed['interval_hours']) * 3600) . "'
        WHERE id = '" . (int) $feed['id'] . "'");

    @unlink($state['file']);
    pg_supplier_feed_spool_clear($feed['id']);

    if (!empty($state['wrote']) && function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }
}

/**
 * A brake (F13): the feed paused with the reason, the run stopped, the
 * supplier_feed alert. Whatever the pull wrote before stays.
 *
 * @param array  $feed
 * @param array  $state
 * @param string $reason
 * @param array  $lines
 * @return void
 */
function pg_supplier_feed_brake($feed, $state, $reason, $lines)
{
    $now = time();
    $reason = trim((string) $reason);
    $short = function_exists('mb_substr') ? mb_substr($reason, 0, 500, 'UTF-8') : substr($reason, 0, 500);

    pg_supplier_feed_run_write($state, $lines, 2, $reason);

    db("UPDATE supplier_feeds SET status = 2, pause_reason = '" . escape($short) . "', state = NULL, last_run_at = '" . $now . "'
        WHERE id = '" . (int) $feed['id'] . "'");

    @unlink($state['file']);
    pg_supplier_feed_spool_clear($feed['id']);

    $detail = lang(array('string' => 'The supplier feed {var:1} was paused: {var:2}', 'vars' => array($feed['name'], $reason)));

    log_activity($detail, 'SYSTEM');

    if (function_exists('pg_ops_alert')) {
        pg_ops_alert('supplier_feed', $detail, 'feed:' . (int) $feed['id']);
    }
}

/**
 * Ends a running pull that cannot go on (the feed switched off, the file
 * gone): its run failed, its place and file dropped.
 *
 * @param array  $feed id, state
 * @param string $reason
 * @return void
 */
function pg_supplier_feed_pull_drop($feed, $reason)
{
    $state = json_decode((string) $feed['state'], true);

    if (is_array($state) && isset($state['run_id'], $state['count'])) {
        pg_supplier_feed_run_write($state, array(), 3, $reason);

        if (isset($state['file'])) {
            @unlink((string) $state['file']);
        }
    }

    pg_supplier_feed_spool_clear($feed['id']);
    db("UPDATE supplier_feeds SET state = NULL WHERE id = '" . (int) $feed['id'] . "'");
}

/**
 * Loads the API's product and stock writers in a process that is not an API
 * request.
 *
 * @return bool
 */
function pg_supplier_feed_writers()
{
    if (!function_exists('api_inventory_apply')) {
        if (!defined('PG_API_PANEL')) {
            define('PG_API_PANEL', true);
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
        require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/inventory.php');
    }

    return function_exists('api_inventory_apply') && function_exists('api_products_set_price');
}
