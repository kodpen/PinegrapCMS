<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Webflow reader of the content import framework (includes/fn/
 * importers.php). Webflow exports three CSV files an import reads; the
 * header tells them apart:
 *
 * - a collection's CMS export: read by the shared collection reader
 *   (includes/import/collection.php) with its mapping step.
 * - the products export, in the columns of Webflow's import template: one
 *   row per variant, the product cells repeated on each, so the rows with
 *   one Product Handle are one product. Items go to the product writer
 *   (write_product.php) in the shape the Shopify reader gives it; physical
 *   and service products come in, other types are named and skipped. The
 *   weight carries no unit: the import's plan step asks it.
 * - the 301 redirects export: an old path and a new one per row, with or
 *   without a header. Each row is a redirect item for the redirect writer
 *   (write_redirect.php); a row with Webflow's wildcard (.*) has no short
 *   link counterpart and is skipped and named in the report.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/collection.php');

// Its products file is read like Squarespace's: the decimal cells and the
// plain text description.
require_once(PG_FUNCTIONS_DIR . '/includes/import/squarespace.php');

/**
 * A header cell as the reader names its column: lower case, single spaces,
 * no quotes or byte order mark. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_webflow_column($cell)
{
    return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', trim((string) $cell, " \t\"'\xEF\xBB\xBF"))));
}

/**
 * Which file this is from its first line's cells: products, redirects or
 * collection. A products header names Product Handle with Variant Price; a
 * redirects file has two cells, a source and target header or two
 * addresses. Pure.
 *
 * @param string[] $cells the first line
 * @return string
 */
function pg_import_webflow_file_kind($cells)
{
    $header = array_map('pg_import_webflow_column', (array) $cells);

    if (in_array('product handle', $header, true) && in_array('variant price', $header, true)) {
        return 'products';
    }

    if (pg_import_webflow_redirect_header($header) !== '') {
        return 'redirects';
    }

    if (in_array('item id', $header, true) && in_array('slug', $header, true)) {
        return 'collection';
    }

    return '';
}

/**
 * Whether two cells are a redirects file's first line: 'header' for its
 * header (source and target, or old and new path), 'row' for a first row
 * without one (an old path and an address), '' for neither. Pure.
 *
 * @param string[] $header pg_import_webflow_column() names
 * @return string
 */
function pg_import_webflow_redirect_header($header)
{
    $header = array_values(array_filter((array) $header, 'strlen'));

    if (count($header) !== 2) {
        return '';
    }

    if (in_array($header[0], array('source', 'old path', 'from', 'redirect from'), true) && in_array($header[1], array('target', 'new path', 'to', 'redirect to'), true)) {
        return 'header';
    }

    if ((strpos($header[0], '/') === 0) && preg_match('#^(/|https?://)#', $header[1])) {
        return 'row';
    }

    return '';
}

/**
 * The first line of a file as cells, with its separator and where the line
 * after it starts; no cells when the file cannot be read.
 *
 * @param string $file
 * @return array{cells:string[],separator:string,start:int,offset:int}
 */
function pg_import_webflow_first_line($file)
{
    $answer = array('cells' => array(), 'separator' => ',', 'start' => 0, 'offset' => -1);
    $handle = ((string) $file !== '') ? @fopen($file, 'rb') : false;

    if (!$handle) {
        return $answer;
    }

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $answer['separator'] = pg_csv_read_separator($handle);
    $answer['start'] = (int) ftell($handle);
    $cells = fgetcsv($handle, 0, $answer['separator'], '"', '\\');

    if (is_array($cells)) {
        $answer['cells'] = array_map('strval', $cells);
        $answer['offset'] = (int) ftell($handle);
    }

    fclose($handle);

    return $answer;
}

/**
 * The mapping rows of the uploaded file: a collection's rows, the products
 * file's product group, picture folder and weight unit (pounds at first,
 * as products.weight holds them), none for redirects.
 *
 * @param string $file the uploaded export
 * @return array
 */
function pg_import_webflow_mapping($file = '')
{
    $file = (string) $file;
    $kind = ($file !== '') ? pg_import_webflow_file_kind(pg_import_webflow_first_line($file)['cells']) : '';

    if ($kind === 'redirects') {
        return array();
    }

    if ($kind !== 'products') {
        return pg_import_collection_mapping($file, 'webflow');
    }

    return array(
        'product_group' => array('label' => lang('Product group the categories go under'), 'type' => 'product_group', 'required' => false),
        'file_folder'   => array('label' => lang('Folder for pictures'), 'type' => 'folder', 'required' => false),
        'weight_unit'   => array(
            'label'    => lang('Unit of the Weight column'),
            'type'     => 'select',
            'required' => true,
            'default'  => 'lb',
            'choices'  => array('kg' => lang('Kilograms (kg)'), 'g' => lang('Grams (g)'), 'lb' => lang('Pounds (lb)'), 'oz' => lang('Ounces (oz)')),
        ),
    );
}

/**
 * The cursor: the collection reader's for a collection file (webflow_file
 * missing, as cursors saved before products were read have it), else the
 * reader's own.
 *
 * @param string $file
 * @return array the cursor
 */
function pg_import_webflow_open($file)
{
    $first = pg_import_webflow_first_line($file);
    $kind = pg_import_webflow_file_kind($first['cells']);

    if (!in_array($kind, array('products', 'redirects'), true)) {
        return pg_import_collection_open((string) $file, 'webflow');
    }

    $cursor = array('webflow_file' => $kind, 'file' => (string) $file, 'offset' => $first['offset'], 'separator' => $first['separator'], 'header' => array(), 'line' => 1);

    if ($kind === 'products') {
        $cursor['header'] = array_map('pg_import_webflow_column', $first['cells']);
    } elseif (pg_import_webflow_redirect_header(array_map('pg_import_webflow_column', $first['cells'])) === 'row') {
        // No header: the first line is a redirect.
        $cursor['offset'] = $first['start'];
        $cursor['line'] = 0;
    }

    return $cursor;
}

/**
 * The next item: a collection record, a product with its variant rows, or
 * a redirect. Null at the end.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_webflow_next(&$cursor)
{
    $kind = (string) ($cursor['webflow_file'] ?? '');

    if (!in_array($kind, array('products', 'redirects'), true)) {
        return pg_import_collection_next($cursor);
    }

    if ((int) ($cursor['offset'] ?? -1) < 0) {
        return null;
    }

    $handle = @fopen($cursor['file'], 'rb');

    if (!$handle) {
        return null;
    }

    fseek($handle, (int) $cursor['offset']);
    $rows = array();
    $item = null;
    $line = (int) $cursor['line'];

    while (true) {
        $at = (int) ftell($handle);
        $cells = fgetcsv($handle, 0, $cursor['separator'], '"', '\\');

        if ($cells === false) {
            $cursor['offset'] = -1;
            break;
        }

        if (pg_csv_row_blank($cells)) {
            $cursor['line'] += pg_csv_row_lines($cells);
            $cursor['offset'] = (int) ftell($handle);
            continue;
        }

        if ($kind === 'redirects') {
            $cursor['line'] += pg_csv_row_lines($cells);
            $cursor['offset'] = (int) ftell($handle);
            $item = pg_import_webflow_redirect($cells, (int) $cursor['line']);

            // A row without an old path says nothing; the next one is read.
            if ($item !== null) {
                break;
            }

            continue;
        }

        $row = pg_import_webflow_row($cursor['header'], $cells);

        // The next product's first row is read again by the next call.
        if ($rows && pg_import_webflow_starts_product($row, $rows[0])) {
            $cursor['offset'] = $at;
            break;
        }

        if (!$rows) {
            $line = (int) $cursor['line'] + 1;
        }

        $cursor['line'] += pg_csv_row_lines($cells);
        $cursor['offset'] = (int) ftell($handle);
        $rows[] = $row;
    }

    fclose($handle);

    if ($kind === 'redirects') {
        return $item;
    }

    return $rows ? pg_import_webflow_product($rows, $line) : null;
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_webflow_plan($item)
{
    $data = (array) ($item['data'] ?? array());

    if (($item['kind'] ?? '') === 'redirect') {
        return array('title' => (string) ($item['title'] ?? ''), 'detail' => (string) ($data['to'] ?? ''));
    }

    if (($item['kind'] ?? '') !== 'product') {
        return pg_import_collection_plan($item);
    }

    $detail = (($data['type'] ?? '') === 'variable')
        ? lang(array('string' => 'Variant set with {var:1} variants', 'vars' => array(count((array) $data['variants']))))
        : (string) ($data['sku'] ?? '');

    return array('title' => (string) ($item['title'] ?? ''), 'detail' => $detail);
}

// ---- Redirects -------------------------------------------------------------

/**
 * The redirect item of a row, null when it names no old path. Keyed by the
 * short link name the old path becomes, so one name is one item. A row
 * with Webflow's wildcard (.*) or without a new path carries the reason it
 * is skipped. Pure but for the short link name rule's file.
 *
 * @param array $cells
 * @param int   $line
 * @return array|null
 */
function pg_import_webflow_redirect($cells, $line)
{
    $from = trim((string) ($cells[0] ?? ''));
    $to = trim((string) ($cells[1] ?? ''));

    if ($from === '') {
        return null;
    }

    if (!function_exists('pg_import_redirect_name')) {
        require_once(__DIR__ . '/write_redirect.php');
    }

    $name = pg_import_redirect_name($from);
    $error = '';

    if ((strpos($from, '(.*)') !== false) || (strpos($to, '%1') !== false)) {
        $error = lang(array('string' => 'The redirect from {var:1} uses a wildcard (.*), which a short link cannot hold; add it on the Short Links screen as a pattern.', 'vars' => array($from)));
    } elseif ($to === '') {
        $error = lang(array('string' => 'The redirect from {var:1} names no new address.', 'vars' => array($from)));
    }

    return array(
        'kind'       => 'redirect',
        'source_key' => mb_substr('webflow:redirect:' . (($name !== '') ? mb_strtolower($name) : 'line:' . (int) $line), 0, 191),
        'title'      => $from,
        'data'       => array('from' => $from, 'to' => $to, 'error' => $error),
    );
}

// ---- Products --------------------------------------------------------------

/**
 * A row as column => trimmed cell, every header column present. Pure.
 *
 * @param string[] $header
 * @param array    $cells
 * @return array<string,string>
 */
function pg_import_webflow_row($header, $cells)
{
    $row = array();

    foreach ((array) $header as $index => $name) {
        if (($name !== '') && !isset($row[$name])) {
            $row[$name] = trim((string) ($cells[$index] ?? ''));
        }
    }

    return $row;
}

/**
 * Whether a row starts a new product rather than adding a variant to the
 * one $first started: its handle, or else its product id, differs. Pure.
 *
 * @param array $row
 * @param array $first the current product's first row
 * @return bool
 */
function pg_import_webflow_starts_product($row, $first)
{
    $handle = mb_strtolower((string) ($row['product handle'] ?? ''));

    if ($handle !== '') {
        return ($handle !== mb_strtolower((string) ($first['product handle'] ?? '')));
    }

    $id = (string) ($row['product id'] ?? '');

    return ($id !== '') && ($id !== (string) ($first['product id'] ?? ''));
}

/**
 * The web addresses of a picture cell, split at spaces, commas and
 * semicolons. Pure.
 *
 * @param string $cell
 * @return string[]
 */
function pg_import_webflow_images($cell)
{
    return array_values(array_filter(preg_split('/[\s,;]+/', trim((string) $cell), -1, PREG_SPLIT_NO_EMPTY), function ($image) {
        return (bool) preg_match('#^https?://#i', $image);
    }));
}

/**
 * The price and compare-at price of a row in minor units; 'refused' names
 * a cell that is not an amount. Pure but for the price reader's file.
 *
 * @param array $row
 * @return array{price:int|null,compare_at:int|null,refused:string}
 */
function pg_import_webflow_money($row)
{
    if (!function_exists('pg_products_import_price_cents')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $money = array('price' => null, 'compare_at' => null, 'refused' => '');

    foreach (array('price' => 'variant price', 'compare_at' => 'variant compare-at price') as $part => $column) {
        $cell = (string) ($row[$column] ?? '');

        if ($cell === '') {
            continue;
        }

        // Read as the product import reads a typed price: a thousands
        // separator, a decimal comma and a currency sign or code.
        $cents = pg_products_import_price_cents($cell);

        if ($cents === null) {
            $money['refused'] = lang(array('string' => 'The price {var:1} is not an amount.', 'vars' => array($cell)));

            return $money;
        }

        $money[$part] = $cents;
    }

    // A compare-at price is shown struck through only above the price.
    if (($money['compare_at'] !== null) && (($money['price'] === null) || ($money['compare_at'] <= $money['price']))) {
        $money['compare_at'] = null;
    }

    return $money;
}

/**
 * The stock of a row: a count, or null for an empty cell, which Webflow
 * does not track. Pure.
 *
 * @param array $row
 * @return int|null
 */
function pg_import_webflow_stock($row)
{
    $cell = trim((string) ($row['variant inventory'] ?? ''));

    return (($cell === '') || !is_numeric($cell)) ? null : max(0, (int) $cell);
}

/**
 * The categories of a Product Categories cell, separated by semicolons,
 * each a path of one name. Pure.
 *
 * @param string $cell
 * @return array[]
 */
function pg_import_webflow_categories($cell)
{
    $paths = array();

    foreach (explode(';', (string) $cell) as $name) {
        $name = trim($name);

        if (($name !== '') && !in_array(array($name), $paths, true)) {
            $paths[] = array($name);
        }
    }

    return $paths;
}

/**
 * Whether a product is shipped: the Requires Shipping cell when it says,
 * else by type, as Webflow sets it (a service is not shipped). Pure.
 *
 * @param string $cell
 * @param string $type lower case
 * @return bool
 */
function pg_import_webflow_shippable($cell, $type)
{
    $cell = mb_strtolower(trim((string) $cell));

    if (in_array($cell, array('true', 'yes', '1'), true)) {
        return true;
    }

    if (in_array($cell, array('false', 'no', '0'), true)) {
        return false;
    }

    return ($type !== 'service');
}

/**
 * The product item of one product's rows, a variant a row. Pure but for
 * the price reader.
 *
 * @param array[] $rows
 * @param int     $line the first row's line, the key when no handle names it
 * @return array
 */
function pg_import_webflow_product($rows, $line)
{
    $first = $rows[0];
    $handle = mb_strtolower((string) ($first['product handle'] ?? ''));
    $id = (string) ($first['product id'] ?? '');
    $stem = ($handle !== '') ? $handle : (($id !== '') ? $id : 'line-' . (int) $line);
    $title = ((string) ($first['product name'] ?? '') !== '') ? $first['product name'] : $stem;
    $type = mb_strtolower((string) ($first['product type'] ?? ''));

    $axes = array();

    for ($number = 1; $number <= 3; $number++) {
        foreach ($rows as $row) {
            if ((string) ($row['option' . $number . ' name'] ?? '') !== '') {
                $axes[$number] = $row['option' . $number . ' name'];
                break;
            }
        }
    }

    $variants = array();

    foreach ($rows as $index => $row) {
        $options = array();

        foreach ($axes as $number => $axis) {
            $options[$axis] = (string) ($row['option' . $number . ' value'] ?? '');
        }

        $label = implode(' / ', array_filter($options, 'strlen'));
        $sku = (string) ($row['variant sku'] ?? '');
        $money = pg_import_webflow_money($row);
        $variant = array(
            'source_key' => 'webflow:variant:' . $stem . ':' . (((string) ($row['variant id'] ?? '') !== '') ? $row['variant id'] : $index),
            'sku'        => ($sku !== '') ? $sku : $stem . (($index > 0) ? '-' . ($index + 1) : ''),
            'name'       => ($label !== '') ? $title . ' - ' . $label : $title,
            'enabled'    => true,
            'price'      => $money['price'],
            'compare_at' => $money['compare_at'],
            'stock'      => pg_import_webflow_stock($row),
            'weight'     => pg_import_squarespace_figure($row['variant weight'] ?? ''),
            'images'     => pg_import_webflow_images($row['main variant image'] ?? ''),
            'options'    => $options,
            'error'      => '',
        );

        if ($money['refused'] !== '') {
            $variant['error'] = $money['refused'];
        } elseif ($money['price'] === null) {
            $variant['error'] = ($label !== '')
                ? lang(array('string' => 'Variation {var:1} is left out: it has no price.', 'vars' => array($label)))
                : lang('The product has no price.');
        } elseif (count(array_filter($options, 'strlen')) < count($axes)) {
            $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: it does not name a value for every attribute.', 'vars' => array($label)));
        }

        $variants[] = $variant;
    }

    $simple = !$axes;
    $base = $variants[0];
    $kept = in_array($type, array('', 'physical', 'service'), true);
    $hidden = false;

    foreach (array('archived', 'draft', 'product archived', 'product draft') as $column) {
        $hidden = $hidden || pg_import_collection_yes($first[$column] ?? '');
    }

    $data = array(
        // Physical and service products are a product here, a service one
        // that is not shipped. Digital, membership and advanced products
        // are named by their type, which the product writer skips.
        'type'          => $kept ? ($simple ? 'simple' : 'variable') : $type,
        'source_type'   => $kept ? ($simple ? 'simple' : 'variable') : $type,
        'shippable'     => pg_import_webflow_shippable($first['requires shipping'] ?? '', $type),
        'sku'           => $simple ? $base['sku'] : $stem,
        'name'          => $title,
        'enabled'       => !$hidden,
        'description'   => pg_import_squarespace_description($first['product description'] ?? ''),
        'details'       => '',
        'keywords'      => '',
        'categories'    => pg_import_webflow_categories($first['product categories'] ?? ''),
        'images'        => array_values(array_unique(array_merge(
            pg_import_webflow_images($first['main variant image'] ?? ''),
            pg_import_webflow_images($first['more variant images'] ?? '')
        ))),
        'price'         => $base['price'],
        'compare_at'    => $base['compare_at'],
        'stock'         => $base['stock'],
        'weight'        => '',
        'weight_figure' => $base['weight'],
        'addresses'     => ($handle !== '') ? array('/product/' . $handle) : array(),
        'axes'          => array(),
        'variants'      => array(),
        'notes'         => array(),
        'error'         => '',
    );

    if ($simple) {
        $data['error'] = $base['error'];
    } else {
        foreach ($variants as $variant) {
            if ($variant['error'] !== '') {
                $data['notes'][] = $variant['error'];
                continue;
            }

            unset($variant['error'], $variant['weight']);
            $data['variants'][] = $variant;
        }

        $data['axes'] = array_values($axes);

        if (!$data['variants']) {
            $data['error'] = lang('None of its variations can be imported.');
        }
    }

    return array('kind' => 'product', 'source_key' => mb_substr('webflow:product:' . $stem, 0, 191), 'title' => $title, 'data' => $data);
}
