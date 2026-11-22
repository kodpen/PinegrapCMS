<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shelf addresses (product_bins): one address per product and stock
 * location ("A-03-2"), location 0 on a site without locations. Typed on the
 * product screen's stock card, by Modify Selected, by the product import
 * (shelf and shelf:<location name> columns) and by POST
 * /products/{id}/inventory; the pick list sorts by it
 * (pg_pick_bin_compare(), includes/fn/pick_pack.php). An empty address is
 * no row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest address product_bins.bin holds, in characters. */
define('PG_BIN_MAX', 40);

/** The import heading of the address (the default location with locations). */
define('PG_BIN_COLUMN', 'shelf');

/** The import heading prefix of one location's address. */
define('PG_BIN_COLUMN_PREFIX', 'shelf:');

/**
 * An address as it is stored: control characters and runs of spaces become
 * one space, the ends trimmed. '' clears it; false when it is longer than
 * the column holds or not text. Pure.
 *
 * @param mixed $typed
 * @return string|false
 */
function pg_bin_clean($typed)
{
    if (($typed === null) || is_bool($typed) || !is_scalar($typed)) {
        return ($typed === null) ? '' : false;
    }

    $value = preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', (string) $typed);

    // Not valid UTF-8: preg_replace() with /u answers null.
    if (!is_string($value)) {
        return false;
    }

    $value = trim($value);

    return (mb_strlen($value, 'UTF-8') > PG_BIN_MAX) ? false : $value;
}

/**
 * Whether the upgrade has added product_bins.
 *
 * @return bool
 */
function pg_bins_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('product_bins');
    }

    return $ready;
}

/**
 * The location an address is kept at: 0 without locations, else the one
 * named, or the default for 0.
 *
 * @param int $location_id 0 or a location id already checked
 * @return int
 */
function pg_bin_location_key($location_id)
{
    if (!function_exists('pg_stock_has_locations') || !pg_stock_has_locations()) {
        return 0;
    }

    return ((int) $location_id > 0) ? (int) $location_id : (int) pg_stock_default_location();
}

/**
 * One product's addresses, location id => address.
 *
 * @param int $product_id
 * @return array
 */
function pg_bins_of_product($product_id)
{
    if (!pg_bins_ready()) {
        return array();
    }

    $bins = array();

    foreach (db_items("SELECT location_id, bin FROM product_bins WHERE product_id = '" . (int) $product_id . "'") as $row) {
        $bins[(int) $row['location_id']] = (string) $row['bin'];
    }

    return $bins;
}

/**
 * Write a product's addresses, location id => cleaned address ('' removes
 * it). On a site with locations an address written at the default location
 * takes the place of one typed before the first location was added
 * (location 0), which the screen showed there.
 *
 * @param int   $product_id
 * @param array $bins location id => string from pg_bin_clean()
 * @return void
 */
function pg_bins_write($product_id, array $bins)
{
    $product_id = (int) $product_id;

    if (!pg_bins_ready() || ($product_id < 1) || !$bins) {
        return;
    }

    foreach ($bins as $location_id => $bin) {
        $location_id = (int) $location_id;

        if ((string) $bin === '') {
            db("DELETE FROM product_bins WHERE product_id = '$product_id' AND location_id = '$location_id'");
            continue;
        }

        db(
            "INSERT INTO product_bins (product_id, location_id, bin, updated_at)
            VALUES ('$product_id', '$location_id', '" . e($bin) . "', UNIX_TIMESTAMP())
            ON DUPLICATE KEY UPDATE bin = VALUES(bin), updated_at = VALUES(updated_at)");
    }

    $default_id = pg_bin_location_key(0);

    if ($default_id && array_key_exists($default_id, $bins)) {
        db("DELETE FROM product_bins WHERE product_id = '$product_id' AND location_id = '0'");
    }
}

/**
 * The addresses the stock card shows, location id => address, in the order
 * of $locations. The default location shows an address typed before the
 * first location was added (location 0) while it has none of its own. Pure.
 *
 * @param array $locations rows with id and is_default
 * @param array $bins      location id => address (pg_bins_of_product())
 * @return array
 */
function pg_bins_screen_values(array $locations, array $bins)
{
    $default_id = pg_stock_pick_default($locations);
    $values = array();

    foreach ($locations as $location) {
        $id = (int) $location['id'];
        $value = isset($bins[$id]) ? (string) $bins[$id] : '';

        if (($value === '') && ($id === $default_id) && isset($bins[0])) {
            $value = (string) $bins[0];
        }

        $values[$id] = $value;
    }

    return $values;
}

/**
 * The addresses the product screen posts, read for the locations it drew.
 * 'ok' is false when one is too long; an id the screen did not draw is
 * ignored. Pure.
 *
 * @param int[] $location_ids the ids drawn (0 without locations)
 * @param mixed $posted       $_POST['product_bin']: location id => typed address
 * @return array{ok: bool, bins: array}
 */
function pg_bins_posted(array $location_ids, $posted)
{
    $bins = array();

    if (!is_array($posted)) {
        return array('ok' => true, 'bins' => $bins);
    }

    foreach ($location_ids as $location_id) {
        $location_id = (int) $location_id;

        if (!array_key_exists($location_id, $posted)) {
            continue;
        }

        $bin = pg_bin_clean($posted[$location_id]);

        if ($bin === false) {
            return array('ok' => false, 'bins' => array());
        }

        $bins[$location_id] = $bin;
    }

    return array('ok' => true, 'bins' => $bins);
}

/**
 * One "Shelf" box of the stock card. Pure.
 *
 * @param int    $location_id
 * @param string $value
 * @param string $classes the grid column's classes
 * @param string $help    HTML under the box
 * @return string
 */
function pg_bin_field_html($location_id, $value, $classes, $help = '')
{
    $id = 'product_bin_' . (int) $location_id;

    return '<div class="' . h($classes) . '">
                <label for="' . $id . '" class="form-label">' . lang('Shelf') . '</label>
                <input type="text" name="product_bin[' . (int) $location_id . ']" id="' . $id . '" class="form-control" maxlength="' . PG_BIN_MAX . '" placeholder="A-03-2" autocomplete="off" value="' . h($value) . '" />
                ' . $help . '
            </div>';
}

/**
 * The stock card's rows on a site with locations: each location's count with
 * its "Shelf" box beside it, one row a location. Pure.
 *
 * @param array $locations  rows with id and name, the order drawn
 * @param array $quantities location id => count shown
 * @param array $bins       location id => address shown, or null before the upgrade
 * @return string
 */
function pg_bin_location_rows_html(array $locations, array $quantities, $bins)
{
    $output = '';

    foreach ($locations as $location) {
        $id = (int) $location['id'];
        $quantity = isset($quantities[$id]) ? (int) $quantities[$id] : 0;

        $output .= '<div class="col-12"><div class="row g-5">
                <div class="col-12 md:col-3">
                    <label for="stock_location_' . $id . '" class="form-label">' . h($location['name']) . '</label>
                    <input type="number" min="0" step="1" name="stock_location[' . $id . ']" id="stock_location_' . $id . '" class="form-control" value="' . $quantity . '" />
                    <input type="hidden" name="stock_location_was[' . $id . ']" value="' . $quantity . '" />
                </div>
                ' . (is_array($bins) ? pg_bin_field_html($id, isset($bins[$id]) ? $bins[$id] : '', 'col-12 md:col-3') : '') . '
            </div></div>';
    }

    return $output;
}

/**
 * The import's shelf columns by position, location id each: "shelf" is the
 * address without locations, the default location's with them;
 * "shelf:<location name>" one location's, its name matched without case or
 * outer spaces. A heading naming no location, a second one for the same
 * location, or "shelf:" on a site without locations is unknown. Pure.
 *
 * @param array $headings   the file's first row
 * @param array $locations  rows with id and name (all, enabled or not); empty without locations
 * @param int   $default_id the default location, 0 without locations
 * @return array{positions: array, unknown: array}
 */
function pg_bin_import_columns(array $headings, array $locations, $default_id)
{
    $by_name = array();

    foreach ($locations as $location) {
        $by_name[mb_strtolower(trim((string) $location['name']), 'UTF-8')] = (int) $location['id'];
    }

    $positions = array();
    $unknown = array();
    $prefix_length = strlen(PG_BIN_COLUMN_PREFIX);

    foreach (array_values($headings) as $position => $heading) {
        $heading = trim((string) $heading);

        if (!pg_bin_is_column_heading($heading)) {
            continue;
        }

        if (strtolower($heading) === PG_BIN_COLUMN) {
            $location_id = $locations ? (int) $default_id : 0;
        } else {
            $name = mb_strtolower(trim(substr($heading, $prefix_length)), 'UTF-8');
            $location_id = isset($by_name[$name]) ? $by_name[$name] : null;
        }

        if (($location_id === null) || ($locations && ($location_id < 1)) || in_array($location_id, $positions, true)) {
            $unknown[] = $heading;
            continue;
        }

        $positions[$position] = $location_id;
    }

    return array('positions' => $positions, 'unknown' => $unknown);
}

/**
 * Whether a heading is a shelf column, known location or not: the import
 * leaves it out of the products columns either way. Pure.
 *
 * @param string $heading
 * @return bool
 */
function pg_bin_is_column_heading($heading)
{
    $heading = strtolower(trim((string) $heading));

    return ($heading === PG_BIN_COLUMN)
        || (substr($heading, 0, strlen(PG_BIN_COLUMN_PREFIX)) === PG_BIN_COLUMN_PREFIX);
}

/**
 * One import row's addresses, location id => cleaned address; an empty cell
 * clears the address, as the file's other text columns do. False when one
 * is too long, which leaves the row out. Pure.
 *
 * @param array $row       the row's cells
 * @param array $positions position => location id (pg_bin_import_columns())
 * @return array|false
 */
function pg_bin_import_row(array $row, array $positions)
{
    $bins = array();

    foreach ($positions as $position => $location_id) {
        $bin = pg_bin_clean(isset($row[$position]) ? $row[$position] : '');

        if ($bin === false) {
            return false;
        }

        $bins[(int) $location_id] = $bin;
    }

    return $bins;
}

/**
 * The address Modify Selected asks for: null for no change, '' to clear,
 * the cleaned address to set, false when "Set" holds none or one too long.
 * Pure.
 *
 * @param array $post
 * @return string|false|null
 */
function pg_products_mass_shelf_value($post)
{
    $method = isset($post['edit_shelf_method']) ? $post['edit_shelf_method'] : '';

    if ($method === 'clear') {
        return '';
    }

    if ($method !== 'set') {
        return null;
    }

    $bin = pg_bin_clean(isset($post['edit_shelf']) ? $post['edit_shelf'] : '');

    return ($bin === '') ? false : $bin;
}
