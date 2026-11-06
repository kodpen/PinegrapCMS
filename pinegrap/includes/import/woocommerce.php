<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WooCommerce reader of the content import framework: the products
 * export CSV (Products > Export) read row by row.
 *
 * - A simple product is one product item.
 * - A variable product is one product item carrying its variations: the
 *   variation rows name their parent ("id:11" or the parent's SKU) and may
 *   stand anywhere in the file, so the first cursor holds, for every
 *   parent, the byte offsets of its variation rows, found in one pass.
 * - A variation whose parent is not in the file is an item that fails with
 *   its reason; a grouped or external product one that is skipped.
 * - Prices go through the product import's cell reader
 *   (pg_products_import_price_cents()): a sale price is the price and the
 *   regular price above it the compare-at price.
 *
 * The item's data is the shape write_product.php writes, whichever reader
 * made it.
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
 * The mapping rows: the group the categories are made under and the folder
 * the pictures are stored in.
 *
 * @return array
 */
function pg_import_woocommerce_mapping()
{
    return array(
        'product_group' => array('label' => lang('Product group the categories go under'), 'type' => 'product_group', 'required' => false),
        'file_folder'   => array('label' => lang('Folder for pictures'), 'type' => 'folder', 'required' => false),
    );
}

/**
 * The first cursor: the header, where the rows start, and the variation
 * rows of every parent.
 *
 * @param string $file
 * @return array
 */
function pg_import_woocommerce_open($file)
{
    $cursor = array('file' => (string) $file, 'offset' => 0, 'separator' => ',', 'header' => array(), 'line' => 1, 'children' => array(), 'parents' => array());
    $handle = @fopen($file, 'rb');

    if (!$handle) {
        $cursor['offset'] = -1;

        return $cursor;
    }

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $cursor['separator'] = pg_csv_read_separator($handle);
    $cursor['header'] = array_map(function ($cell) {
        return mb_strtolower(trim((string) $cell));
    }, (array) fgetcsv($handle, 0, $cursor['separator'], '"', '\\'));
    $cursor['offset'] = (int) ftell($handle);

    while (true) {
        $at = (int) ftell($handle);
        $cells = fgetcsv($handle, 0, $cursor['separator'], '"', '\\');

        if ($cells === false) {
            break;
        }

        $row = pg_import_woocommerce_row($cursor['header'], $cells);
        $type = mb_strtolower($row['type']);

        if ($type === 'variation') {
            $cursor['children'][pg_import_woocommerce_ref($row['parent'])][] = $at;
        } elseif ($type === 'variable') {
            if ($row['id'] !== '') {
                $cursor['parents']['id:' . $row['id']] = 1;
            }

            if ($row['sku'] !== '') {
                $cursor['parents'][pg_import_woocommerce_ref($row['sku'])] = 1;
            }
        }
    }

    fclose($handle);

    return $cursor;
}

/**
 * The next product item, null at the end. Variation rows of a parent in the
 * file are read with it and passed over here.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_woocommerce_next(&$cursor)
{
    if ((int) $cursor['offset'] < 0) {
        return null;
    }

    $handle = @fopen($cursor['file'], 'rb');

    if (!$handle) {
        return null;
    }

    fseek($handle, (int) $cursor['offset']);
    $item = null;

    while ($item === null) {
        $cells = fgetcsv($handle, 0, $cursor['separator'], '"', '\\');

        if ($cells === false) {
            $cursor['offset'] = -1;
            break;
        }

        $cursor['line'] += pg_csv_row_lines($cells);
        $cursor['offset'] = (int) ftell($handle);

        if (trim(implode('', $cells)) === '') {
            continue;
        }

        $row = pg_import_woocommerce_row($cursor['header'], $cells);
        $type = mb_strtolower($row['type']);

        // A variation of a parent in the file comes with its parent.
        if (($type === 'variation') && isset($cursor['parents'][pg_import_woocommerce_ref($row['parent'])])) {
            continue;
        }

        $children = array();

        if ($type === 'variable') {
            $offsets = array();

            foreach (array('id:' . $row['id'], pg_import_woocommerce_ref($row['sku'])) as $ref) {
                if (($ref !== 'id:') && ($ref !== '') && isset($cursor['children'][$ref])) {
                    $offsets = array_merge($offsets, $cursor['children'][$ref]);
                }
            }

            sort($offsets);

            foreach (array_unique($offsets) as $offset) {
                fseek($handle, (int) $offset);
                $children[] = pg_import_woocommerce_row($cursor['header'], (array) fgetcsv($handle, 0, $cursor['separator'], '"', '\\'));
            }

            fseek($handle, (int) $cursor['offset']);
        }

        $item = pg_import_woocommerce_item($row, $children, (int) $cursor['line']);
    }

    fclose($handle);

    return $item;
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_woocommerce_plan($item)
{
    $data = (array) ($item['data'] ?? array());

    if (($data['error'] ?? '') !== '') {
        $detail = $data['error'];
    } elseif (($data['type'] ?? '') === 'variable') {
        $detail = lang(array('string' => 'Variant set with {var:1} variants', 'vars' => array(count((array) $data['variants']))));
    } elseif (($data['type'] ?? '') === 'simple') {
        $detail = lang('Product');
    } else {
        $detail = lang('Not imported: only simple and variable products are.');
    }

    return array('title' => (string) ($item['title'] ?? ''), 'detail' => $detail);
}

/**
 * The cells of a row by heading, the columns the reader reads always
 * present. Pure.
 *
 * @param string[] $header lower-case headings
 * @param array    $cells
 * @return array<string,string>
 */
function pg_import_woocommerce_row($header, $cells)
{
    $row = array();

    foreach ($header as $index => $name) {
        if (($name !== '') && !isset($row[$name])) {
            $row[$name] = trim((string) ($cells[$index] ?? ''));
        }
    }

    foreach (array('id', 'type', 'sku', 'name', 'published', 'short description', 'description', 'in stock?', 'stock',
        'sale price', 'regular price', 'categories', 'tags', 'images', 'parent') as $name) {
        if (!isset($row[$name])) {
            $row[$name] = '';
        }
    }

    return $row;
}

/**
 * A parent reference as variations write it and parents are keyed: "id:11"
 * as it is, a SKU in lower case (WooCommerce compares SKUs so). Pure.
 *
 * @param string $ref
 * @return string
 */
function pg_import_woocommerce_ref($ref)
{
    $ref = trim((string) $ref);

    return (preg_match('/^id:\s*(\d+)$/i', $ref, $match) === 1) ? 'id:' . $match[1] : mb_strtolower($ref);
}

/**
 * A list cell (categories, tags, images): comma separated, "\," a comma
 * inside a name. Pure.
 *
 * @param string $cell
 * @return string[]
 */
function pg_import_woocommerce_list($cell)
{
    $list = array();

    foreach (explode("\x00", str_replace(',', "\x00", str_replace('\\,', "\x01", (string) $cell))) as $part) {
        $part = trim(str_replace("\x01", ',', $part));

        if ($part !== '') {
            $list[] = $part;
        }
    }

    return $list;
}

/**
 * The categories of a row, each as its path from the top
 * ("Clothing > Tshirts" is array('Clothing', 'Tshirts')). Pure.
 *
 * @param string $cell
 * @return array[]
 */
function pg_import_woocommerce_categories($cell)
{
    $paths = array();

    foreach (pg_import_woocommerce_list($cell) as $category) {
        $path = array_values(array_filter(array_map('trim', explode('>', $category)), 'strlen'));

        if ($path) {
            $paths[] = $path;
        }
    }

    return $paths;
}

/**
 * The money of a row in minor units: the price (the sale price when there
 * is one), the compare-at price (the regular price above a sale price) and
 * the refusal when a cell is not an amount. An empty price is null. Pure
 * but for product_builder.php, which the price reader needs.
 *
 * @param array $row
 * @return array{price:int|null,compare_at:int|null,refused:string}
 */
function pg_import_woocommerce_money($row)
{
    if (!function_exists('pg_pb_price_figure')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $money = array('price' => null, 'compare_at' => null, 'refused' => '');
    $amounts = array();

    foreach (array('regular price', 'sale price') as $column) {
        $cell = (string) ($row[$column] ?? '');
        $amounts[$column] = null;

        if ($cell === '') {
            continue;
        }

        $amounts[$column] = pg_products_import_price_cents($cell);

        if ($amounts[$column] === null) {
            $money['refused'] = lang(array('string' => 'The price {var:1} is not an amount.', 'vars' => array($cell)));

            return $money;
        }
    }

    if ($amounts['sale price'] !== null) {
        $money['price'] = $amounts['sale price'];

        if (($amounts['regular price'] !== null) && ($amounts['regular price'] > $amounts['sale price'])) {
            $money['compare_at'] = $amounts['regular price'];
        }
    } else {
        $money['price'] = $amounts['regular price'];
    }

    return $money;
}

/**
 * The stock of a row: a count when the row tracks it ("Stock" given, or
 * marked out of stock), null when it does not. A negative count (orders
 * taken on backorder) is 0. Pure.
 *
 * @param array $row
 * @return int|null
 */
function pg_import_woocommerce_stock($row)
{
    $stock = (string) ($row['stock'] ?? '');

    if (preg_match('/^-?\d+(\.0+)?$/', $stock) === 1) {
        return max(0, (int) $stock);
    }

    return ((string) ($row['in stock?'] ?? '') === '0') ? 0 : null;
}

/**
 * The option pairs of a variation: axis name => value, in the order of its
 * attribute columns. Pure.
 *
 * @param array $row
 * @return array<string,string>
 */
function pg_import_woocommerce_options($row)
{
    $options = array();

    for ($number = 1; isset($row['attribute ' . $number . ' name']); $number++) {
        $axis = trim($row['attribute ' . $number . ' name']);

        if ($axis !== '') {
            $options[$axis] = trim((string) ($row['attribute ' . $number . ' value(s)'] ?? ''));
        }
    }

    return $options;
}

/**
 * The weight of a row in pounds, the unit products.weight holds. The
 * column is named with the shop's unit, as "Weight (kg)", "Weight (g)",
 * "Weight (lbs)" or "Weight (oz)"; one without a unit is in kilograms,
 * WooCommerce's default. A decimal comma is read too; '' when the cell is
 * empty or not a number. Pure.
 *
 * @param array $row lower-case header => cell
 * @return string
 */
function pg_import_woocommerce_weight($row)
{
    foreach ($row as $column => $cell) {
        if (strpos((string) $column, 'weight') !== 0) {
            continue;
        }

        $figure = trim((string) $cell);

        if ((strpos($figure, ',') !== false) && (strpos($figure, '.') === false)) {
            $figure = str_replace(',', '.', $figure);
        }

        if (!is_numeric($figure)) {
            return '';
        }

        $unit = preg_match('/\(\s*([a-z]+)\s*\)/', (string) $column, $match) ? $match[1] : 'kg';

        return pg_import_weight_pounds((float) $figure, $unit);
    }

    return '';
}

/**
 * The product item of a row and, for a variable product, its variation
 * rows. Pure but for the price reader's file.
 *
 * @param array $row
 * @param array $children variation rows
 * @param int   $line     the line the row ends on, for a key without an id
 * @return array
 */
function pg_import_woocommerce_item($row, $children, $line)
{
    $type = mb_strtolower($row['type']);

    // A product can be of several kinds at once ("simple, virtual").
    $types = array_map('trim', explode(',', $type));
    $type = in_array('variable', $types, true) ? 'variable' : (in_array('variation', $types, true) ? 'variation' : (in_array('simple', $types, true) ? 'simple' : $type));

    if ($row['id'] !== '') {
        $key = 'woo:product:' . $row['id'];
    } elseif ($row['sku'] !== '') {
        $key = 'woo:sku:' . mb_strtolower($row['sku']);
    } else {
        $key = 'woo:line:' . $line;
    }

    $name = ($row['name'] !== '') ? $row['name'] : $row['sku'];
    $money = pg_import_woocommerce_money($row);

    $data = array(
        'type'        => in_array($type, array('simple', 'variable'), true) ? $type : 'other',
        'source_type' => $type,
        'sku'         => ($row['sku'] !== '') ? $row['sku'] : (($row['id'] !== '') ? 'woo-' . $row['id'] : ''),
        'name'        => $name,
        'enabled'     => ($row['published'] === '1'),
        'description' => $row['description'],
        'details'     => $row['short description'],
        'keywords'    => implode(', ', pg_import_woocommerce_list($row['tags'])),
        'categories'  => pg_import_woocommerce_categories($row['categories']),
        'images'      => pg_import_woocommerce_list($row['images']),
        'price'       => $money['price'],
        'compare_at'  => $money['compare_at'],
        'stock'       => pg_import_woocommerce_stock($row),
        'weight'      => '',
        'axes'        => array(),
        'variants'    => array(),
        'notes'       => array(),
        'error'       => '',
    );

    $data['weight'] = pg_import_woocommerce_weight($row);

    if ($type === 'variation') {
        $data['error'] = lang(array('string' => 'Its parent product ({var:1}) is not in the file.', 'vars' => array($row['parent'])));
    } elseif (($data['type'] !== 'other') && ($money['refused'] !== '')) {
        $data['error'] = $money['refused'];
    }

    if ($data['type'] === 'variable') {
        foreach ($children as $child) {
            $variant = pg_import_woocommerce_variant($child, $data);

            if ($variant['error'] !== '') {
                $data['notes'][] = $variant['error'];
                continue;
            }

            foreach (array_keys($variant['options']) as $axis) {
                if (!in_array($axis, $data['axes'], true)) {
                    $data['axes'][] = $axis;
                }
            }

            unset($variant['error']);
            $data['variants'][] = $variant;
        }

        if (!$data['variants'] && ($data['error'] === '')) {
            $data['error'] = lang('None of its variations can be imported.');
        }
    }

    return array('kind' => 'product', 'source_key' => $key, 'title' => $name, 'data' => $data);
}

/**
 * One variation of a variable product: its own SKU (else the parent's with
 * its id), price (else the parent's), stock and picture, and its option
 * pairs; 'error' says why it is left out. Pure but for the price reader.
 *
 * @param array $row
 * @param array $parent the parent's item data
 * @return array
 */
function pg_import_woocommerce_variant($row, $parent)
{
    $money = pg_import_woocommerce_money($row);
    $label = ($row['name'] !== '') ? $row['name'] : $row['sku'];

    $variant = array(
        'source_key' => ($row['id'] !== '') ? 'woo:product:' . $row['id'] : 'woo:sku:' . mb_strtolower($row['sku']),
        'sku'        => ($row['sku'] !== '') ? $row['sku'] : $parent['sku'] . '-' . $row['id'],
        'name'       => $label,
        'enabled'    => ($row['published'] === '1'),
        'price'      => ($money['price'] !== null) ? $money['price'] : $parent['price'],
        'compare_at' => ($money['price'] !== null) ? $money['compare_at'] : $parent['compare_at'],
        'stock'      => pg_import_woocommerce_stock($row),
        'images'     => pg_import_woocommerce_list($row['images']),
        'options'    => pg_import_woocommerce_options($row),
        'error'      => '',
    );

    if ($money['refused'] !== '') {
        $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: {var:2}', 'vars' => array($label, $money['refused'])));
    } elseif ($variant['price'] === null) {
        $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: it has no price.', 'vars' => array($label)));
    } elseif (!$variant['options'] || in_array('', $variant['options'], true)) {
        // "Any" value: one row stands for several, which a variant set has no row for.
        $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: it does not name a value for every attribute.', 'vars' => array($label)));
    }

    return $variant;
}
