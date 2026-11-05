<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Shopify reader of the content import framework (includes/fn/
 * importers.php). Shopify exports two CSV files and either may be uploaded:
 *
 * - products: the rows of one product share its Handle and stand together;
 *   the first carries the title and body, every row a variant (Option1-3
 *   values, SKU, price, stock) and/or a picture. One "Default Title" variant
 *   is a simple product, more are a variable one (its options as the variant
 *   set's attributes). Items go to the product writer (write_product.php),
 *   in the shape the WooCommerce reader gives it.
 * - customers: one contact per row, its default address as the business
 *   address, "Accepts Email Marketing" as opt_in, tags as contact groups
 *   (write_contact.php).
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
 * The mapping rows: the product group categories go under and the folder
 * pictures are stored in (products), as the WooCommerce reader asks.
 *
 * @return array
 */
function pg_import_shopify_mapping()
{
    return array(
        'product_group' => array('label' => lang('Product group the categories go under'), 'type' => 'product_group', 'required' => false),
        'file_folder'   => array('label' => lang('Folder for pictures'), 'type' => 'folder', 'required' => false),
    );
}

/**
 * Which file this is from its header (lower case): products, customers or
 * ''. Pure.
 *
 * @param string[] $header
 * @return string
 */
function pg_import_shopify_file_kind($header)
{
    if (in_array('handle', $header, true)) {
        return 'products';
    }

    if (in_array('email', $header, true) && (in_array('first name', $header, true) || in_array('customer id', $header, true))) {
        return 'customers';
    }

    return '';
}

/**
 * @param string $file
 * @return array the cursor
 */
function pg_import_shopify_open($file)
{
    $cursor = array('file' => (string) $file, 'offset' => -1, 'separator' => ',', 'header' => array(), 'line' => 1, 'kind' => '');
    $handle = @fopen($file, 'rb');

    if (!$handle) {
        return $cursor;
    }

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $cursor['separator'] = pg_csv_read_separator($handle);
    $cursor['header'] = array_map(function ($cell) {
        return mb_strtolower(trim((string) $cell));
    }, (array) fgetcsv($handle, 0, $cursor['separator'], '"', '\\'));
    $cursor['kind'] = pg_import_shopify_file_kind($cursor['header']);
    $cursor['offset'] = ($cursor['kind'] !== '') ? (int) ftell($handle) : -1;

    fclose($handle);

    return $cursor;
}

/**
 * The next item: a product with all the rows of its handle, or a contact.
 * Null at the end.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_shopify_next(&$cursor)
{
    if ((int) $cursor['offset'] < 0) {
        return null;
    }

    $handle = @fopen($cursor['file'], 'rb');

    if (!$handle) {
        return null;
    }

    fseek($handle, (int) $cursor['offset']);
    $rows = array();
    $line = (int) $cursor['line'];

    while (true) {
        $at = (int) ftell($handle);
        $cells = fgetcsv($handle, 0, $cursor['separator'], '"', '\\');

        if ($cells === false) {
            $cursor['offset'] = -1;
            break;
        }

        if (trim(implode('', $cells)) === '') {
            $cursor['line'] += pg_csv_row_lines($cells);
            $cursor['offset'] = (int) ftell($handle);
            continue;
        }

        $row = pg_import_shopify_row($cursor['header'], $cells);

        // A product ends where the next handle starts; that row is read again
        // by the next call.
        if (($cursor['kind'] === 'products') && $rows && ($row['handle'] !== $rows[0]['handle'])) {
            $cursor['offset'] = $at;
            break;
        }

        $cursor['line'] += pg_csv_row_lines($cells);
        $cursor['offset'] = (int) ftell($handle);
        $line = (int) $cursor['line'];
        $rows[] = $row;

        if ($cursor['kind'] === 'customers') {
            break;
        }
    }

    fclose($handle);

    if (!$rows) {
        return null;
    }

    return ($cursor['kind'] === 'products') ? pg_import_shopify_product($rows) : pg_import_shopify_customer($rows[0], $line);
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_shopify_plan($item)
{
    $data = (array) ($item['data'] ?? array());

    if (($item['kind'] ?? '') === 'contact') {
        return array('title' => (string) ($item['title'] ?? ''), 'detail' => (string) ($data['email_address'] ?? ''));
    }

    $detail = (($data['type'] ?? '') === 'variable')
        ? lang(array('string' => 'Variant set with {var:1} variants', 'vars' => array(count((array) $data['variants']))))
        : (string) ($data['sku'] ?? '');

    return array('title' => (string) ($item['title'] ?? ''), 'detail' => $detail);
}

// ---- Rows ------------------------------------------------------------------

/**
 * A row as column => trimmed cell, every header column present. Pure.
 *
 * @param string[] $header
 * @param array    $cells
 * @return array<string,string>
 */
function pg_import_shopify_row($header, $cells)
{
    $row = array();

    foreach ($header as $index => $name) {
        if ($name !== '') {
            $row[$name] = trim((string) ($cells[$index] ?? ''));
        }
    }

    return $row + array('handle' => '');
}

/**
 * A yes/true cell. Pure.
 *
 * @param string $cell
 * @return bool
 */
function pg_import_shopify_yes($cell)
{
    return in_array(mb_strtolower(trim((string) $cell)), array('yes', 'true', '1', 'y'), true);
}

/**
 * The price and compare-at price of a row in minor units: the compare-at
 * price only when above the price. 'refused' names a cell that is not an
 * amount. Pure but for the price reader's file.
 *
 * @param array $row
 * @return array{price:int|null,compare_at:int|null,refused:string}
 */
function pg_import_shopify_money($row)
{
    if (!function_exists('pg_pb_price_figure')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $money = array('price' => null, 'compare_at' => null, 'refused' => '');

    foreach (array('price' => 'variant price', 'compare_at' => 'variant compare at price') as $key => $column) {
        $cell = (string) ($row[$column] ?? '');

        if ($cell === '') {
            continue;
        }

        $money[$key] = pg_products_import_price_cents($cell);

        if ($money[$key] === null) {
            $money['refused'] = lang(array('string' => 'The price {var:1} is not an amount.', 'vars' => array($cell)));

            return $money;
        }
    }

    if (($money['compare_at'] !== null) && (($money['price'] === null) || ($money['compare_at'] <= $money['price']))) {
        $money['compare_at'] = null;
    }

    return $money;
}

/**
 * The stock of a variant row: a count when Shopify tracks it, null when it
 * does not. Orders taken past zero make it 0. Pure.
 *
 * @param array $row
 * @return int|null
 */
function pg_import_shopify_stock($row)
{
    if ((string) ($row['variant inventory tracker'] ?? '') === '') {
        return null;
    }

    return max(0, (int) ($row['variant inventory qty'] ?? 0));
}

/**
 * The weight of a variant in pounds, the unit products.weight holds:
 * Shopify keeps grams whatever unit the shop shows (Variant Weight Unit).
 * Pure.
 *
 * @param array $row
 * @return string
 */
function pg_import_shopify_weight($row)
{
    $grams = (string) ($row['variant grams'] ?? '');

    if (!is_numeric($grams)) {
        return '';
    }

    return pg_import_weight_pounds((float) $grams, 'g');
}

/**
 * The product item of one handle's rows. Pure but for the price reader.
 *
 * @param array[] $rows
 * @return array
 */
function pg_import_shopify_product($rows)
{
    $first = $rows[0];
    $handle = $first['handle'];
    $title = ($first['title'] ?? '') !== '' ? $first['title'] : $handle;
    $status = mb_strtolower((string) ($first['status'] ?? ''));

    $axes = array();

    for ($number = 1; $number <= 3; $number++) {
        $axis = (string) ($first['option' . $number . ' name'] ?? '');

        if ($axis !== '') {
            $axes[$number] = $axis;
        }
    }

    $images = array();
    $variants = array();
    $notes = array();

    foreach ($rows as $index => $row) {
        if ((string) ($row['image src'] ?? '') !== '') {
            $images[(int) ($row['image position'] ?? 0) * 1000 + $index] = $row['image src'];
        }

        // A row with neither a price nor a SKU carries only a picture.
        if (((string) ($row['variant price'] ?? '') === '') && ((string) ($row['variant sku'] ?? '') === '')) {
            continue;
        }

        $options = array();

        foreach ($axes as $number => $axis) {
            $options[$axis] = (string) ($row['option' . $number . ' value'] ?? '');
        }

        $label = implode(' / ', array_filter($options, 'strlen'));
        $money = pg_import_shopify_money($row);
        $variant = array(
            'source_key' => 'shopify:variant:' . $handle . ':' . (($row['variant sku'] ?? '') !== '' ? mb_strtolower($row['variant sku']) : $index),
            'sku'        => ($row['variant sku'] ?? '') !== '' ? $row['variant sku'] : $handle . '-' . ($index + 1),
            'name'       => ($label !== '') ? $title . ' - ' . $label : $title,
            'enabled'    => true,
            'price'      => $money['price'],
            'compare_at' => $money['compare_at'],
            'stock'      => pg_import_shopify_stock($row),
            'weight'     => pg_import_shopify_weight($row),
            'images'     => (($row['variant image'] ?? '') !== '') ? array($row['variant image']) : array(),
            'options'    => $options,
            'error'      => '',
        );

        if ($money['refused'] !== '') {
            $variant['error'] = $money['refused'];
        } elseif ($money['price'] === null) {
            $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: it has no price.', 'vars' => array($label)));
        } elseif (count(array_filter($options, 'strlen')) < count($axes)) {
            $variant['error'] = lang(array('string' => 'Variation {var:1} is left out: it does not name a value for every attribute.', 'vars' => array($label)));
        }

        $variants[] = $variant;
    }

    ksort($images);

    $simple = (count($variants) === 1) && (array_values($axes) === array('Title'));
    $base = $variants ? $variants[0] : array('sku' => $handle, 'price' => null, 'compare_at' => null, 'stock' => null, 'weight' => '', 'error' => lang('The product has no variant row.'));

    $data = array(
        'type'        => $simple ? 'simple' : 'variable',
        'source_type' => $simple ? 'simple' : 'variable',
        'sku'         => $base['sku'],
        'name'        => $title,
        'enabled'     => pg_import_shopify_yes($first['published'] ?? '') && in_array($status, array('', 'active'), true),
        'description' => (string) ($first['body (html)'] ?? ''),
        'details'     => '',
        'keywords'    => implode(', ', array_filter(array_map('trim', explode(',', (string) ($first['tags'] ?? ''))), 'strlen')),
        'categories'  => (($first['type'] ?? '') !== '') ? array(array($first['type'])) : array(),
        'images'      => array_values(array_unique($images)),
        'price'       => $base['price'],
        'compare_at'  => $base['compare_at'],
        'stock'       => $base['stock'],
        'weight'      => $base['weight'],
        'axes'        => array(),
        'variants'    => array(),
        'notes'       => array(),
        'error'       => '',
    );

    if ($simple || !$variants) {
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

    return array('kind' => 'product', 'source_key' => 'shopify:product:' . mb_strtolower($handle), 'title' => $title, 'data' => $data);
}

/**
 * The contact item of a customers row. Shopify writes the customer id with
 * a leading apostrophe so spreadsheets keep it as text. Pure.
 *
 * @param array $row
 * @param int   $line
 * @return array
 */
function pg_import_shopify_customer($row, $line)
{
    $id = ltrim((string) ($row['customer id'] ?? ''), "'");
    $email = mb_strtolower((string) ($row['email'] ?? ''));
    $cell = function ($new, $old) use ($row) {
        return (string) (($row[$new] ?? '') !== '' ? $row[$new] : ($row[$old] ?? ''));
    };

    $key = ($id !== '') ? 'shopify:customer:' . $id : (($email !== '') ? 'shopify:email:' . $email : 'shopify:line:' . $line);
    $name = trim(($row['first name'] ?? '') . ' ' . ($row['last name'] ?? ''));

    return array(
        'kind'       => 'contact',
        'source_key' => $key,
        'title'      => ($name !== '') ? $name : $email,
        'data'       => array(
            'first_name'         => (string) ($row['first name'] ?? ''),
            'last_name'          => (string) ($row['last name'] ?? ''),
            'email_address'      => $email,
            'company'            => $cell('default address company', 'company'),
            'business_address_1' => $cell('default address address1', 'address1'),
            'business_address_2' => $cell('default address address2', 'address2'),
            'business_city'      => $cell('default address city', 'city'),
            'business_state'     => $cell('default address province code', 'province code'),
            'business_country'   => $cell('default address country code', 'country code'),
            'business_zip_code'  => $cell('default address zip', 'zip'),
            'business_phone'     => $cell('default address phone', 'phone'),
            'mobile_phone'       => (string) ($row['phone'] ?? ''),
            'description'        => (string) ($row['note'] ?? ''),
            // Both spellings: "Accepts Email Marketing" (2023 on) and the
            // older "Accepts Marketing".
            'opt_in'             => pg_import_shopify_yes($cell('accepts email marketing', 'accepts marketing')),
            'tags'               => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($row['tags'] ?? ''))), 'strlen'))),
        ),
    );
}
