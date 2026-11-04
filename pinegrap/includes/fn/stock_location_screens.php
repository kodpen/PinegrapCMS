<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock per location on the panel's product screens (2026.11.0): the
 * quantities the product screen shows and posts, the bulk change at one
 * location, and the stock:<location name> columns of the product import and
 * export. Every write goes through pg_stock_change() / pg_stock_set()
 * (includes/fn/stock_locations.php), so products.inventory_quantity stays the
 * sum of the rows. A site without locations gets empty lists here and every
 * screen keeps its one quantity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The heading prefix of a stock column in the product import and export. */
define('PG_STOCK_COLUMN_PREFIX', 'stock:');

/**
 * The locations the screens show a quantity for: the enabled ones, default
 * first. Empty when the site has no locations, so a screen keeps its one
 * quantity box.
 *
 * @return array rows of pg_stock_locations()
 */
function pg_stock_screen_locations()
{
    if (!function_exists('pg_stock_has_locations') || !pg_stock_has_locations()) {
        return array();
    }

    return pg_stock_locations(true);
}

/**
 * A product's quantity at each location as the screens show it. A product
 * with no rows yet (tracking switched on after the first location was added)
 * holds its whole total at the default location, which is where the writer
 * seeds it on its first change. Pure.
 *
 * @param array $locations rows with id and is_default
 * @param array $rows      location id => quantity (pg_stock_rows())
 * @param int   $total     products.inventory_quantity
 * @return array location id => quantity, in the order of $locations
 */
function pg_stock_screen_quantities(array $locations, array $rows, $total)
{
    $default_id = $rows ? 0 : pg_stock_pick_default($locations);
    $quantities = array();

    foreach ($locations as $location) {
        $id = (int) $location['id'];

        if (isset($rows[$id])) {
            $quantities[$id] = (int) $rows[$id];
        } elseif ($id === $default_id) {
            $quantities[$id] = max(0, (int) $total);
        } else {
            $quantities[$id] = 0;
        }
    }

    return $quantities;
}

/**
 * The changes the product screen asks for: each location whose posted
 * quantity differs from the one the screen opened with, as the delta to
 * apply. A delta, not the typed figure, so units sold while the screen was
 * open are not put back. Pure.
 *
 * @param array $locations rows with id
 * @param mixed $posted    $_POST['stock_location']: location id => typed quantity
 * @param mixed $was       $_POST['stock_location_was']: location id => quantity shown
 * @return array ok, error ('' | 'quantity'), changes (location id => delta)
 */
function pg_stock_posted_changes(array $locations, $posted, $was)
{
    $posted = is_array($posted) ? $posted : array();
    $was = is_array($was) ? $was : array();
    $changes = array();

    foreach ($locations as $location) {
        $id = (int) $location['id'];

        if (!array_key_exists($id, $posted)) {
            continue;
        }

        $typed = is_scalar($posted[$id]) ? trim((string) $posted[$id]) : null;
        $typed = ($typed === null) ? null : pg_stock_clean_quantity(($typed === '') ? 0 : $typed);

        if ($typed === null) {
            return array('ok' => false, 'error' => 'quantity', 'changes' => array());
        }

        $shown = (isset($was[$id]) && is_scalar($was[$id])) ? pg_stock_clean_quantity(trim((string) $was[$id])) : null;

        // A form posted without the opened figure sets the quantity: the
        // delta is then worked out by the caller from the stored row.
        if ($shown === null) {
            $changes[$id] = array('set' => $typed);
            continue;
        }

        if ($typed !== $shown) {
            $changes[$id] = array('delta' => $typed - $shown);
        }
    }

    return array('ok' => true, 'error' => '', 'changes' => $changes);
}

/**
 * The delta a bulk change makes at one location: set to the value, raised
 * by it or lowered by it, never below 0 (the bulk edits' rule,
 * pg_explorer_bulk_product_new_quantity()). Pure.
 *
 * @param int    $current the location's quantity
 * @param string $mode    'value', 'increase' or 'decrease'
 * @param int    $value
 * @return int
 */
function pg_stock_bulk_delta($current, $mode, $value)
{
    $current = max(0, (int) $current);
    $value = max(0, (int) $value);

    if ($mode === 'value') {
        return $value - $current;
    }

    if ($mode === 'increase') {
        return $value;
    }

    return -min($value, $current);
}

/**
 * Apply the product screen's or an import's changes to one product. Each
 * entry is array('delta' => n) or array('set' => n); a set is turned into
 * the delta from the stored row inside pg_stock_set().
 *
 * @param int    $product_id
 * @param array  $changes location id => array('delta' => n) | array('set' => n)
 * @param string $reason  a key of pg_stock_reasons()
 * @return array ok (every change written), total_before, total (null when
 *               nothing was written), errors (location id => writer error)
 */
function pg_stock_apply_changes($product_id, array $changes, $reason)
{
    $done = array('ok' => true, 'total_before' => null, 'total' => null, 'errors' => array());

    foreach ($changes as $location_id => $change) {
        $result = isset($change['set'])
            ? pg_stock_set($product_id, (int) $location_id, $change['set'], $reason)
            : pg_stock_change($product_id, (int) $location_id, (int) $change['delta'], $reason);

        if (!$result['ok']) {
            $done['ok'] = false;
            $done['errors'][(int) $location_id] = $result['error'];
            continue;
        }

        if ($done['total_before'] === null) {
            $done['total_before'] = $result['total_before'];
        }

        $done['total'] = $result['total'];
    }

    return $done;
}

/**
 * One bulk change at one location for one product: the location's stored
 * quantity, the delta by the bulk rule, then the writer. A product that does
 * not track stock is left alone ('untracked'), as it has no rows.
 *
 * @param int    $product_id
 * @param int    $location_id 0 = the default location
 * @param string $mode
 * @param int    $value
 * @param string $reason
 * @return array see pg_stock_change()
 */
function pg_stock_bulk_write($product_id, $location_id, $mode, $value, $reason)
{
    $product_id = (int) $product_id;
    $locations = pg_stock_locations();
    $resolved = pg_stock_resolve_location($locations, $location_id);
    $total = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . $product_id . "'");
    $quantities = pg_stock_screen_quantities($locations, pg_stock_rows($product_id), $total);
    $current = ($resolved && isset($quantities[$resolved])) ? $quantities[$resolved] : 0;

    return pg_stock_change($product_id, $resolved ? $resolved : (int) $location_id, pg_stock_bulk_delta($current, $mode, $value), $reason);
}

/**
 * Set a product's out-of-stock mark from its stored total, after a write by
 * location: tracked with nothing left is marked (stamped on the way in),
 * anything else is not.
 *
 * @param int $product_id
 * @return void
 */
function pg_stock_refresh_out_of_stock($product_id)
{
    $product_id = (int) $product_id;
    $stored = db_item("SELECT inventory, inventory_quantity, out_of_stock FROM products WHERE id = '" . $product_id . "'");

    if ($stored) {
        db("UPDATE products SET "
            . pg_product_out_of_stock_sql((string) $stored['inventory'] === '1', $stored['inventory_quantity'], (string) $stored['out_of_stock'])
            . " WHERE id = '" . $product_id . "'");
    }
}

/**
 * The stock columns of an import file: each heading stock:<name> whose name
 * is a location (compared without case or surrounding spaces), by its
 * position, and the headings that name no location. Pure.
 *
 * @param array $headings  the file's first row
 * @param array $locations rows with id and name
 * @return array positions (position => location id), unknown (headings)
 */
function pg_stock_import_columns(array $headings, array $locations)
{
    $by_name = array();

    foreach ($locations as $location) {
        $by_name[mb_strtolower(trim((string) $location['name']), 'UTF-8')] = (int) $location['id'];
    }

    $positions = array();
    $unknown = array();
    $prefix_length = strlen(PG_STOCK_COLUMN_PREFIX);

    foreach (array_values($headings) as $position => $heading) {
        $heading = trim((string) $heading);

        if (strtolower(substr($heading, 0, $prefix_length)) !== PG_STOCK_COLUMN_PREFIX) {
            continue;
        }

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
 * Whether a heading is a stock column, known location or not: the import
 * leaves it out of the products columns either way. Pure.
 *
 * @param string $heading
 * @return bool
 */
function pg_stock_is_column_heading($heading)
{
    return strtolower(substr(trim((string) $heading), 0, strlen(PG_STOCK_COLUMN_PREFIX))) === PG_STOCK_COLUMN_PREFIX;
}

/**
 * What one import row sets, location id => quantity. The stock columns win;
 * a file with only the old inventory_quantity column fills the default
 * location with it. An empty cell (null) leaves its location as it is. Pure.
 *
 * @param array    $cells      location id => quantity or null
 * @param int|null $old        the inventory_quantity cell, null when empty
 * @param int      $default_id
 * @return array location id => quantity
 */
function pg_stock_import_targets(array $cells, $old, $default_id)
{
    $targets = array();

    foreach ($cells as $location_id => $quantity) {
        if ($quantity !== null) {
            $targets[(int) $location_id] = (int) $quantity;
        }
    }

    if (!$cells && ($old !== null) && ((int) $default_id > 0)) {
        $targets[(int) $default_id] = (int) $old;
    }

    return $targets;
}

/**
 * Write one import row's stock after its product row: on a tracked product
 * each target through the writer (reason 'import'), on a product that does
 * not track stock the old column alone, as it has no rows. Announces
 * nothing, as the rest of the import.
 *
 * @param int      $product_id
 * @param array    $cells location id => quantity or null (the stock columns)
 * @param int|null $old   the inventory_quantity cell, null when empty
 * @return void
 */
function pg_stock_import_write($product_id, array $cells, $old)
{
    $product_id = (int) $product_id;

    if ((string) db_value("SELECT inventory FROM products WHERE id = '" . $product_id . "'") !== '1') {
        if ($old !== null) {
            db("UPDATE products SET inventory_quantity = '" . (int) $old . "' WHERE id = '" . $product_id . "'");
        }

        return;
    }

    $changes = array();

    foreach (pg_stock_import_targets($cells, $old, pg_stock_default_location()) as $location_id => $quantity) {
        $changes[$location_id] = array('set' => $quantity);
    }

    pg_stock_apply_changes($product_id, $changes, 'import');
}

/**
 * The export's stock headings, one per location in the screens' order.
 * Pure.
 *
 * @param array $locations rows with name
 * @return array
 */
function pg_stock_export_headings(array $locations)
{
    $headings = array();

    foreach ($locations as $location) {
        $headings[] = PG_STOCK_COLUMN_PREFIX . (string) $location['name'];
    }

    return $headings;
}

/**
 * One product's cells under the export's stock headings: empty for a
 * product that does not track stock, else its quantity at each location
 * (pg_stock_screen_quantities()). Pure.
 *
 * @param array $locations
 * @param array $rows    location id => quantity of this product
 * @param array $product row with inventory and inventory_quantity
 * @return array
 */
function pg_stock_export_cells(array $locations, array $rows, array $product)
{
    if (!isset($product['inventory']) || ((string) $product['inventory'] !== '1')) {
        return array_fill(0, count($locations), '');
    }

    return array_map('strval', array_values(pg_stock_screen_quantities(
        $locations,
        $rows,
        isset($product['inventory_quantity']) ? $product['inventory_quantity'] : 0)));
}

/**
 * Every product's rows in one read, product id => (location id => quantity),
 * for the export.
 *
 * @return array
 */
function pg_stock_rows_by_product()
{
    $by_product = array();

    foreach (db_items("SELECT product_id, location_id, quantity FROM product_stock") as $row) {
        $by_product[(int) $row['product_id']][(int) $row['location_id']] = (int) $row['quantity'];
    }

    return $by_product;
}
