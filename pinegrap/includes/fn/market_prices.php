<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A product's fixed price in a market (`market_prices`): one row per market
 * and product, the price in integer minor units of the market's currency.
 * A product with a row costs that in the market's cart; one without is sold
 * at its base price converted at the day's rate and rounded by the market's
 * rule (pg_market_price_minor()).
 *
 * Set on the product form ("Prices by market") and by the product import,
 * written into the product export as one price:<market name> column per
 * market. A site without the table or a market reads no row.
 *
 * Loaded by markets.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The heading prefix of a market's price column in the product export and
// import, followed by the market's name.
define('PG_MARKET_PRICE_COLUMN_PREFIX', 'price:');

/**
 * Whether fixed prices can be kept: the markets' tables and market_prices
 * are there.
 *
 * @return bool
 */
function pg_market_prices_ready()
{
    return pg_markets_ready() && pg_schema_has('market_prices');
}

/**
 * A product's fixed prices, market id => minor units.
 *
 * @param int $product_id
 * @return array
 */
function pg_market_prices_for_product($product_id)
{
    if (((int) $product_id < 1) || !pg_market_prices_ready()) {
        return array();
    }

    $prices = array();

    foreach ((array) db_items("SELECT market_id, price FROM market_prices WHERE product_id = '" . (int) $product_id . "'") as $row) {
        $prices[(int) $row['market_id']] = (int) $row['price'];
    }

    return $prices;
}

/**
 * The fixed price of a product in a market, null when it has none (the
 * base price converted and rounded is used). Read once a request per
 * market and product.
 *
 * @param int $market_id
 * @param int $product_id
 * @return int|null  minor units of the market's currency
 */
function pg_market_fixed_price($market_id, $product_id)
{
    static $kept = array();

    $market_id = (int) $market_id;
    $product_id = (int) $product_id;

    if (($market_id < 1) || ($product_id < 1)) {
        return null;
    }

    $key = $market_id . ':' . $product_id;

    if (!array_key_exists($key, $kept)) {
        $kept[$key] = null;

        if (pg_market_prices_ready()) {
            $price = db_value("SELECT price FROM market_prices WHERE market_id = '" . $market_id . "' AND product_id = '" . $product_id . "' LIMIT 1");

            if (($price !== null) && ($price !== '') && ($price !== false)) {
                $kept[$key] = (int) $price;
            }
        }
    }

    return $kept[$key];
}

/**
 * What a write changes, from the prices stored and the prices asked for.
 * A price of null or not above 0 takes the market's fixed price off; a
 * market not named is left as it is. Pure.
 *
 * @param array $stored market id => minor units
 * @param array $asked  market id => minor units or null
 * @return array  'set' (market id => minor units), 'remove' (market ids)
 */
function pg_market_prices_changes(array $stored, array $asked)
{
    $set = array();
    $remove = array();

    foreach ($asked as $market_id => $price) {
        $market_id = (int) $market_id;

        if ($market_id < 1) {
            continue;
        }

        if (($price === null) || ((int) $price <= 0)) {
            if (isset($stored[$market_id])) {
                $remove[] = $market_id;
            }

            continue;
        }

        if (!isset($stored[$market_id]) || ((int) $stored[$market_id] !== (int) $price)) {
            $set[$market_id] = (int) $price;
        }
    }

    return array('set' => $set, 'remove' => $remove);
}

/**
 * Writes a product's fixed prices (pg_market_prices_changes()). A
 * marketplace is sent the base price, so nothing is queued for one.
 *
 * @param int   $product_id
 * @param array $asked market id => minor units or null
 * @return bool  whether anything was written
 */
function pg_market_prices_save($product_id, array $asked)
{
    $product_id = (int) $product_id;

    if (($product_id < 1) || !pg_market_prices_ready()) {
        return false;
    }

    $changes = pg_market_prices_changes(pg_market_prices_for_product($product_id), $asked);

    foreach ($changes['set'] as $market_id => $price) {
        db_q("INSERT INTO market_prices (market_id, product_id, price) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE price = VALUES(price)", array((int) $market_id, $product_id, (int) $price));
    }

    if ($changes['remove']) {
        db("DELETE FROM market_prices WHERE product_id = '" . $product_id . "' AND market_id IN (" . implode(',', array_map('intval', $changes['remove'])) . ")");
    }

    return ($changes['set'] !== array()) || ($changes['remove'] !== array());
}

/**
 * Drops every fixed price of a product (it is deleted for good).
 *
 * @param int $product_id
 */
function pg_market_prices_delete_product($product_id)
{
    if (((int) $product_id > 0) && pg_market_prices_ready()) {
        db("DELETE FROM market_prices WHERE product_id = '" . (int) $product_id . "'");
    }
}

/**
 * Drops every fixed price of a market (it is deleted).
 *
 * @param int $market_id
 */
function pg_market_prices_delete_market($market_id)
{
    if (((int) $market_id > 0) && pg_schema_has('market_prices')) {
        db("DELETE FROM market_prices WHERE market_id = '" . (int) $market_id . "'");
    }
}

/**
 * Reads one typed price: '' is none (null), an amount is minor units,
 * anything else is refused (false). Pure but for the import's reader.
 *
 * @param string $value
 * @return int|null|false
 */
function pg_market_price_cell($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (strpos($value, '-') === 0) {
        return false;
    }

    $read = pg_products_import_price_read($value);

    return ($read['cents'] === null) ? false : (int) $read['cents'];
}

/**
 * The product form's posted fixed prices, read for the markets the card
 * drew (market_price_drawn_<id>): market id => minor units or null, and
 * the names of the markets whose price is not an amount. Pure but for the
 * reader.
 *
 * @param array $post
 * @param array $markets id => row with name (pg_market_list())
 * @return array  'prices', 'refused'
 */
function pg_market_prices_posted(array $post, array $markets)
{
    $prices = array();
    $refused = array();

    foreach ($markets as $market_id => $market) {
        $market_id = (int) $market_id;

        if (!isset($post['market_price_drawn_' . $market_id])) {
            continue;
        }

        $cell = isset($post['market_price_' . $market_id]) && is_scalar($post['market_price_' . $market_id]) ? (string) $post['market_price_' . $market_id] : '';
        $price = pg_market_price_cell($cell);

        if ($price === false) {
            $refused[] = (string) $market['name'];
            continue;
        }

        $prices[$market_id] = $price;
    }

    return array('prices' => $prices, 'refused' => $refused);
}

/**
 * Saves the fixed prices the product form posted; a market whose price is
 * not an amount keeps its stored price and is named in the returned list.
 *
 * @param int   $product_id
 * @param array $post
 * @return array  names of the markets left as they were
 */
function pg_market_prices_save_posted($product_id, array $post)
{
    if (!pg_market_prices_ready()) {
        return array();
    }

    $read = pg_market_prices_posted($post, pg_market_list());

    if ($read['prices']) {
        pg_market_prices_save($product_id, $read['prices']);
    }

    return $read['refused'];
}

/**
 * The "Prices by market" card of the product form; '' on a site without a
 * market.
 *
 * @param int $product_id 0 for a new product
 * @return string HTML
 */
function pg_market_prices_card($product_id)
{
    if (!pg_market_prices_ready()) {
        return '';
    }

    $markets = pg_market_list();

    if ($markets === array()) {
        return '';
    }

    $symbols = array();

    foreach ((array) db_items("SELECT code, symbol FROM currencies") as $row) {
        $symbols[strtoupper(trim((string) $row['code']))] = (string) $row['symbol'];
    }

    return pg_market_prices_card_html($markets, pg_market_prices_for_product($product_id), $symbols);
}

/**
 * pg_market_prices_card()'s markup. A currency symbol is stored HTML and is
 * printed raw.
 *
 * @param array $markets id => row with name, currency_code, enabled
 * @param array $prices  market id => minor units
 * @param array $symbols currency code => symbol (HTML)
 * @return string HTML
 */
function pg_market_prices_card_html(array $markets, array $prices, array $symbols)
{
    $rows = '';

    foreach ($markets as $market_id => $market) {
        $market_id = (int) $market_id;
        $code = strtoupper(trim((string) $market['currency_code']));
        $symbol = isset($symbols[$code]) ? trim($symbols[$code]) : '';
        $value = isset($prices[$market_id]) ? sprintf('%01.2f', $prices[$market_id] / 100) : '';
        $field = 'market_price_' . $market_id;

        $rows .= '
                                    <div class="col-12 md:col-4 my-3">
                                        <label for="' . $field . '" class="form-label">' . h($market['name']) . ' <span class="pg-text-2">' . h($code) . '</span>'
            . (empty($market['enabled']) ? ' <span class="pg-pill pg-tone-gray">' . h(lang('Off')) . '</span>' : '') . '</label>
                                        <input type="hidden" name="market_price_drawn_' . $market_id . '" value="1" />
                                        <label class="form-control form-adorn form-adorn-end">
                                            <span class="form-adorn-text" aria-hidden="true">' . (($symbol !== '') ? $symbol : h($code)) . '</span>
                                            <input value="' . h($value) . '" type="text" name="' . $field . '" id="' . $field . '" class="form-ghost" maxlength="13" inputmode="decimal" placeholder="' . h(lang('Converted')) . '" style="text-align:right;" />
                                        </label>
                                    </div>';
    }

    return '
                    <div class="col-12" id="pg_pb_sec_market_prices">
                        <div class="card mb-7">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
                                <i class="bi bi-globe-americas fg-primary"></i>
                                <span class="h5 mb-0 fg-primary fw-bold">' . h(lang('Prices by market')) . '</span>
                            </div>
                            <div class="card-body">
                                <div class="pg-field-help">' . h(lang('A fixed price in the market\'s currency. Left empty, the unit price is converted at the day\'s rate and rounded by the market\'s rule.')) . '</div>
                                <div class="row">' . $rows . '
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * The export's price headings, one per market in the list's order. Pure.
 *
 * @param array $markets rows with name
 * @return array
 */
function pg_market_price_export_headings(array $markets)
{
    $headings = array();

    foreach ($markets as $market) {
        $headings[] = PG_MARKET_PRICE_COLUMN_PREFIX . (string) $market['name'];
    }

    return $headings;
}

/**
 * The export's price cells of one product, in the headings' order: the
 * fixed price as a decimal, '' for none. Pure.
 *
 * @param array $markets id => row
 * @param array $prices  market id => minor units
 * @return array
 */
function pg_market_price_export_cells(array $markets, array $prices)
{
    $cells = array();

    foreach ($markets as $market_id => $market) {
        $cells[] = isset($prices[(int) $market_id]) ? sprintf('%01.2f', $prices[(int) $market_id] / 100) : '';
    }

    return $cells;
}

/**
 * Every fixed price, product id => (market id => minor units), for the
 * export.
 *
 * @return array
 */
function pg_market_prices_by_product()
{
    if (!pg_market_prices_ready()) {
        return array();
    }

    $by_product = array();

    foreach ((array) db_items("SELECT market_id, product_id, price FROM market_prices") as $row) {
        $by_product[(int) $row['product_id']][(int) $row['market_id']] = (int) $row['price'];
    }

    return $by_product;
}

/**
 * Whether a heading is a market's price column (price:<name>). Pure.
 *
 * @param string $heading
 * @return bool
 */
function pg_market_price_is_column_heading($heading)
{
    $prefix = PG_MARKET_PRICE_COLUMN_PREFIX;

    return strtolower(substr(trim((string) $heading), 0, strlen($prefix))) === $prefix;
}

/**
 * The import's price columns: position => market id for each heading that
 * names a market (case and spaces aside), and the price headings that name
 * none. Pure.
 *
 * @param array $headings the file's first row
 * @param array $markets  id => row with name
 * @return array  'positions', 'unknown'
 */
function pg_market_price_import_columns(array $headings, array $markets)
{
    $by_name = array();

    foreach ($markets as $market_id => $market) {
        $by_name[mb_strtolower(trim((string) $market['name']), 'UTF-8')] = (int) $market_id;
    }

    $positions = array();
    $unknown = array();
    $prefix_length = strlen(PG_MARKET_PRICE_COLUMN_PREFIX);

    foreach (array_values($headings) as $position => $heading) {
        if (!pg_market_price_is_column_heading($heading)) {
            continue;
        }

        $heading = trim((string) $heading);
        $name = mb_strtolower(trim(substr($heading, $prefix_length)), 'UTF-8');

        if (isset($by_name[$name]) && !in_array($by_name[$name], $positions, true)) {
            $positions[$position] = $by_name[$name];
        } else {
            $unknown[] = $heading;
        }
    }

    return array('positions' => $positions, 'unknown' => $unknown);
}

/**
 * One import row's fixed prices: market id => minor units (0 takes the
 * price off); an empty cell leaves its market as it is. False when a cell
 * is not an amount, which leaves the row out. Pure but for the reader.
 *
 * @param array $row
 * @param array $positions position => market id
 * @return array|false
 */
function pg_market_price_import_row(array $row, array $positions)
{
    $prices = array();

    foreach ($positions as $position => $market_id) {
        $price = pg_market_price_cell(isset($row[$position]) ? (string) $row[$position] : '');

        if ($price === false) {
            return false;
        }

        if ($price !== null) {
            $prices[(int) $market_id] = $price;
        }
    }

    return $prices;
}
