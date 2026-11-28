<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Wix reader of the content import framework (includes/fn/
 * importers.php). Wix exports three CSV files an import reads; the header
 * tells them apart:
 *
 * - a collection's CMS export: read by the shared collection reader
 *   (includes/import/collection.php) with its mapping step.
 * - the Stores products export, in two layouts: the current one keyed by
 *   handle (PRODUCT, VARIANT and MEDIA rows, a picture a media row) and the
 *   previous one keyed by handleId (Product and Variant rows, a variant's
 *   price the product's plus its surcharge, a discount on every price).
 *   Items go to the product writer (write_product.php) in the shape the
 *   Shopify reader gives it, with brand, barcode and cost. Wix exports
 *   physical products only, and no address: no redirect is made. The
 *   weight carries no unit: the import's plan step asks it.
 * - the contacts export: no help page lists its headings, so the plan step
 *   pairs each contact part with a column, guessed from the headings; the
 *   e-mail subscription status is the consent and the labels the tags
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

require_once(PG_FUNCTIONS_DIR . '/includes/import/collection.php');

// The products file is read like Webflow's: the first line, the decimal
// cells, the plain text description and the price check.
require_once(PG_FUNCTIONS_DIR . '/includes/import/webflow.php');

/**
 * A header cell as the reader names its column: lower case letters and
 * digits only, so "productOptionName[1]" and "productOptionName1" are one
 * name and "Address 1 - Street" is address1street. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_wix_column($cell)
{
    return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $cell));
}

/**
 * Which file this is from its first line's cells: products (handle or
 * handleId with fieldType), contacts (a name column with the e-mail
 * subscription status or the labels) or collection. Pure.
 *
 * @param string[] $cells the first line
 * @return string
 */
function pg_import_wix_file_kind($cells)
{
    $header = array_map('pg_import_wix_column', (array) $cells);
    $has = function ($name) use ($header) {
        return in_array($name, $header, true);
    };

    if (($has('handle') || $has('handleid')) && $has('fieldtype')) {
        return 'products';
    }

    if (pg_import_wix_is_contacts($header)) {
        return 'contacts';
    }

    if ($has('id') && ($has('owner') || ($has('createddate') && $has('updateddate')))) {
        return 'collection';
    }

    return '';
}

/**
 * Whether a header (pg_import_wix_column() names) is a contacts export: a
 * first or last name column with a subscription status or a labels column.
 * Pure.
 *
 * @param string[] $header
 * @return bool
 */
function pg_import_wix_is_contacts($header)
{
    $named = false;
    $wix = false;

    foreach ((array) $header as $name) {
        $named = $named || in_array($name, array('firstname', 'lastname'), true);
        $wix = $wix || ($name === 'labels') || (bool) preg_match('/subscri(ber|ption)status/', $name);
    }

    return $named && $wix;
}

/**
 * The mapping rows of the uploaded file: a collection's rows; the products
 * file's product group, picture folder and weight unit; for contacts one
 * row per contact part, its choices the file's columns, starting on the
 * column its heading suggests.
 *
 * @param string $file the uploaded export
 * @return array
 */
function pg_import_wix_mapping($file = '')
{
    $file = (string) $file;
    $first = ($file !== '') ? pg_import_webflow_first_line($file) : array('cells' => array());
    $kind = pg_import_wix_file_kind($first['cells']);

    if ($kind === 'products') {
        // Squarespace's products rows: product group, picture folder and the
        // weight unit, pounds at first.
        return pg_import_squarespace_mapping('');
    }

    if ($kind !== 'contacts') {
        return pg_import_collection_mapping($file, 'wix');
    }

    $choices = array();

    foreach ($first['cells'] as $index => $heading) {
        if (trim($heading) !== '') {
            $choices['col_' . $index] = trim(preg_replace('/\s+/u', ' ', $heading), " \t\"'\xEF\xBB\xBF");
        }
    }

    $guess = pg_import_wix_contact_guess($first['cells']);
    $rows = array();

    foreach (pg_import_wix_contact_parts() as $part => $label) {
        $rows['contact_' . $part] = array('label' => $label, 'type' => 'select', 'required' => false, 'default' => $guess[$part], 'choices' => $choices);
    }

    return $rows;
}

/**
 * The contact parts the contacts file's columns can be paired with.
 *
 * @return array<string,string> part => label
 */
function pg_import_wix_contact_parts()
{
    return array(
        'email_address'      => lang('E-mail address'),
        'first_name'         => lang('First name'),
        'last_name'          => lang('Last name'),
        'company'            => lang('Company'),
        'mobile_phone'       => lang('Phone'),
        'business_address_1' => lang('Address'),
        'business_address_2' => lang('Address line 2'),
        'business_city'      => lang('City'),
        'business_state'     => lang('State or region'),
        'business_zip_code'  => lang('Postal code'),
        'business_country'   => lang('Country'),
        'description'        => lang('Notes'),
        'opt_in'             => lang('E-mail subscription status'),
        'tags'               => lang('Labels'),
    );
}

/**
 * The column each contact part starts on, guessed from the headings: the
 * first heading that names it, '' when none does. A heading naming a
 * label, type or status of an e-mail or phone is not the value. Pure.
 *
 * @param string[] $cells the header as written
 * @return array<string,string> part => col_<n> or ''
 */
function pg_import_wix_contact_guess($cells)
{
    $tests = array(
        'opt_in'             => '/^emailsubscri(ber|ption)status$|^emailmarketingstatus$/',
        'email_address'      => '/^e?mail(address)?\d*(value)?$/',
        'first_name'         => '/^(firstname|givenname)$/',
        'last_name'          => '/^(lastname|familyname|surname)$/',
        'company'            => '/^(company(name)?|organi[sz]ation\d*(name)?)$/',
        'mobile_phone'       => '/^(phone|mobile|mobilephone)\d*(value)?$/',
        'business_address_2' => '/^address\d*(line2|street2|addressline2)$/',
        'business_address_1' => '/^address\d*(street|line1|streetaddress|formatted)?$/',
        'business_city'      => '/^(address\d*)?city$/',
        'business_state'     => '/^(address\d*)?(state|region|stateregion|province)$/',
        'business_zip_code'  => '/^(address\d*)?(zip|zipcode|postalcode|postcode)$/',
        'business_country'   => '/^(address\d*)?country$/',
        'description'        => '/^notes?$/',
        'tags'               => '/^(labels?|tags)$/',
    );
    $guess = array_fill_keys(array_keys(pg_import_wix_contact_parts()), '');

    foreach ((array) $cells as $index => $heading) {
        $name = pg_import_wix_column($heading);

        foreach ($tests as $part => $pattern) {
            if (($guess[$part] === '') && preg_match($pattern, $name)) {
                $guess[$part] = 'col_' . $index;
                break;
            }
        }
    }

    return $guess;
}

/**
 * The cursor: the collection reader's for a collection file (wix_file
 * missing, as cursors saved before products were read have it), else the
 * reader's own.
 *
 * @param string $file
 * @return array the cursor
 */
function pg_import_wix_open($file)
{
    $first = pg_import_webflow_first_line($file);
    $kind = pg_import_wix_file_kind($first['cells']);

    if (!in_array($kind, array('products', 'contacts'), true)) {
        return pg_import_collection_open((string) $file, 'wix');
    }

    $cursor = array('wix_file' => $kind, 'file' => (string) $file, 'offset' => $first['offset'], 'separator' => $first['separator'], 'header' => array(), 'line' => 1);
    $cursor['header'] = ($kind === 'products') ? array_map('pg_import_wix_column', $first['cells']) : array_map('strval', $first['cells']);

    return $cursor;
}

/**
 * The next item: a collection record, a product with its variant and media
 * rows, or a contact. Null at the end.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_wix_next(&$cursor)
{
    $kind = (string) ($cursor['wix_file'] ?? '');

    if (!in_array($kind, array('products', 'contacts'), true)) {
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

        if ($kind === 'contacts') {
            $cursor['line'] += pg_csv_row_lines($cells);
            $cursor['offset'] = (int) ftell($handle);
            $item = pg_import_wix_contact($cursor['header'], $cells, (int) $cursor['line']);
            break;
        }

        $row = pg_import_webflow_row($cursor['header'], $cells);

        // The next product's first row is read again by the next call.
        if ($rows && pg_import_wix_starts_product($row, $rows[0])) {
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

    if ($kind === 'contacts') {
        return $item;
    }

    return $rows ? pg_import_wix_product($rows, $line) : null;
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_wix_plan($item)
{
    $data = (array) ($item['data'] ?? array());

    if (($item['kind'] ?? '') === 'contact') {
        return array('title' => (string) ($item['title'] ?? ''), 'detail' => (string) ($data['email_address'] ?? ''));
    }

    if (($item['kind'] ?? '') === 'product') {
        return pg_import_webflow_plan($item);
    }

    return pg_import_collection_plan($item);
}

// ---- Contacts --------------------------------------------------------------

/**
 * The contact item of a row: its cells by column (col_<n>), which the
 * writer reads with the pairs the person chose, and the contact parts read
 * with the guessed pairs for the dry run. Keyed by the guessed e-mail
 * address, else the line. Pure.
 *
 * @param string[] $header the header as written
 * @param array    $cells
 * @param int      $line
 * @return array
 */
function pg_import_wix_contact($header, $cells, $line)
{
    if (!function_exists('pg_import_contact_from_columns')) {
        require_once(__DIR__ . '/write_contact.php');
    }

    $columns = array();

    foreach ((array) $header as $index => $heading) {
        $cell = trim((string) ($cells[$index] ?? ''));

        if (($cell !== '') && (trim((string) $heading) !== '')) {
            $columns['col_' . $index] = $cell;
        }
    }

    $data = pg_import_contact_from_columns($columns, array_filter(pg_import_wix_contact_guess($header), 'strlen'));
    $name = trim($data['first_name'] . ' ' . $data['last_name']);
    $title = ($name !== '') ? $name : (($data['email_address'] !== '') ? $data['email_address'] : $data['mobile_phone']);
    $data['columns'] = $columns;

    return array(
        'kind'       => 'contact',
        'source_key' => mb_substr(($data['email_address'] !== '') ? 'wix:email:' . $data['email_address'] : 'wix:line:' . (int) $line, 0, 191),
        'title'      => ($title !== '') ? $title : lang(array('string' => 'Line {var:1}', 'vars' => array((int) $line))),
        'data'       => $data,
    );
}

// ---- Products --------------------------------------------------------------

/**
 * Whether a row starts a new product rather than adding a variant or a
 * picture to the one $first started: a product row, or another handle.
 * Pure.
 *
 * @param array $row
 * @param array $first the current product's first row
 * @return bool
 */
function pg_import_wix_starts_product($row, $first)
{
    if (mb_strtolower((string) ($row['fieldtype'] ?? '')) === 'product') {
        return true;
    }

    $handle = pg_import_wix_handle($row);

    return ($handle !== '') && ($handle !== pg_import_wix_handle($first));
}

// A row's handle, in either layout.
function pg_import_wix_handle($row)
{
    $handle = (string) ($row['handle'] ?? '');

    return ($handle !== '') ? $handle : (string) ($row['handleid'] ?? '');
}

/**
 * The web address of a picture cell: an address as it is, a Wix media id
 * (abc_123~mv2.jpg) on Wix's media host. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_wix_image($cell)
{
    $cell = trim((string) $cell);

    if (preg_match('#^https?://#i', $cell)) {
        return $cell;
    }

    if (strpos($cell, 'wix:image://') === 0) {
        return pg_import_collection_wix_media($cell);
    }

    return preg_match('/^[A-Za-z0-9_~.\-]+\.[A-Za-z0-9]{2,5}$/', $cell) ? 'https://static.wixstatic.com/media/' . $cell : '';
}

/**
 * The addresses of a picture cell, several separated by semicolons. Pure.
 *
 * @param string $cell
 * @return string[]
 */
function pg_import_wix_images($cell)
{
    return array_values(array_filter(array_map('pg_import_wix_image', explode(';', (string) $cell)), 'strlen'));
}

/**
 * An amount cell in minor units, null when empty or not an amount, read by
 * the product import's price reader. Pure but for the price reader's file.
 *
 * @param string $cell
 * @return int|null
 */
function pg_import_wix_cents($cell)
{
    if (!function_exists('pg_products_import_price_cents')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $cell = trim((string) $cell);

    // Read as the product import reads a typed price: a thousands
    // separator, a decimal comma and a currency sign or code.
    return ($cell !== '') ? pg_products_import_price_cents($cell) : null;
}

/**
 * A price less the previous layout's discount: AMOUNT takes the amount off,
 * PERCENT the percentage, rounded half away from zero on the exact figure;
 * never below zero. Pure but for the price reader's file.
 *
 * @param int    $cents
 * @param string $mode  AMOUNT or PERCENT
 * @param string $value
 * @return int
 */
function pg_import_wix_discounted($cents, $mode, $value)
{
    $mode = strtoupper(trim((string) $mode));
    $figure = pg_import_squarespace_figure($value);

    if (($figure === '') || ((float) $figure <= 0)) {
        return (int) $cents;
    }

    if ($mode === 'PERCENT') {
        return max(0, (int) pg_percent_off((int) $cents, $figure));
    }

    if ($mode === 'AMOUNT') {
        $off = pg_import_wix_cents($figure);

        return ($off === null) ? (int) $cents : max(0, (int) $cents - $off);
    }

    return (int) $cents;
}

/**
 * The stock of an inventory cell: a count, 0 for out of stock, null for in
 * stock or empty, which Wix does not count. Pure.
 *
 * @param string $cell
 * @return int|null
 */
function pg_import_wix_stock($cell)
{
    $cell = trim((string) $cell);

    if (pg_import_wix_column($cell) === 'outofstock') {
        return 0;
    }

    // The count as written: its decimal point and minus sign kept, a comma
    // grouping thousands dropped.
    if (preg_match('/^\d{1,3}(,\d{3})+$/', $cell)) {
        $cell = str_replace(',', '', $cell);
    }

    return is_numeric($cell) ? max(0, (int) $cell) : null;
}

/**
 * A variant's choice of an option: the label of a swatch ("#FFFFFF:White"
 * or "rgb(…):White"), else the cell. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_wix_choice($cell)
{
    $cell = trim((string) $cell);

    if (preg_match('/^(#[0-9a-f]{3,8}|rgba?\([^)]*\)):(.+)$/i', $cell, $match)) {
        return trim($match[2]);
    }

    return $cell;
}

/**
 * The product item of one product's rows: the product row, its variant
 * rows and, in the current layout, its media rows. Pure but for the price
 * reader.
 *
 * @param array[] $rows
 * @param int     $line the first row's line, the key when no handle names it
 * @return array
 */
function pg_import_wix_product($rows, $line)
{
    $previous = !array_key_exists('handle', $rows[0]);
    $product = null;
    $variant_rows = array();
    $images = array();

    foreach ($rows as $row) {
        $type = mb_strtolower((string) ($row['fieldtype'] ?? ''));

        if (($type === 'product') && ($product === null)) {
            $product = $row;
            $images = array_merge($images, $previous ? pg_import_wix_images($row['productimageurl'] ?? '') : pg_import_wix_images($row['media'] ?? ''));
        } elseif ($type === 'variant') {
            $variant_rows[] = $row;
        } elseif ($type === 'media') {
            $images = array_merge($images, pg_import_wix_images($row['media'] ?? ''));
        }
    }

    $product = ($product !== null) ? $product : $rows[0];
    $handle = pg_import_wix_handle($product);
    $stem = ($handle !== '') ? $handle : 'line-' . (int) $line;
    $title = ((string) ($product['name'] ?? '') !== '') ? $product['name'] : $stem;
    $option_choice = $previous ? 'productoptiondescription' : 'productoptionchoices';

    $axes = array();

    for ($number = 1; $number <= 6; $number++) {
        if ((string) ($product['productoptionname' . $number] ?? '') !== '') {
            $axes[$number] = $product['productoptionname' . $number];
        }
    }

    // Without options a product row is the product; a variant row the
    // export gives it fills the cells the product row leaves empty.
    if (!$axes) {
        $own = $variant_rows ? $variant_rows[0] : array();

        foreach (array('price', 'strikethroughprice', 'cost', 'inventory', 'sku', 'barcode', 'weight') as $column) {
            if (((string) ($product[$column] ?? '') === '') && ((string) ($own[$column] ?? '') !== '')) {
                $product[$column] = $own[$column];
            }
        }

        $variant_rows = array($product);
    }

    $variants = array();

    foreach ($variant_rows as $index => $row) {
        $options = array();

        foreach ($axes as $number => $axis) {
            $options[$axis] = pg_import_wix_choice($row[$option_choice . $number] ?? '');
        }

        $label = implode(' / ', array_filter($options, 'strlen'));
        $money = pg_import_wix_money($product, $row, $previous);
        $sku = (string) ($row['sku'] ?? '');
        $cost = pg_import_wix_cents(((string) ($row['cost'] ?? '') !== '') ? $row['cost'] : ($product['cost'] ?? ''));
        $variant = array(
            'source_key' => 'wix:variant:' . $stem . ':' . (($sku !== '') ? $sku : $index),
            'sku'        => ($sku !== '') ? $sku : $stem . (($index > 0) ? '-' . ($index + 1) : ''),
            'name'       => ($label !== '') ? $title . ' - ' . $label : $title,
            'enabled'    => (mb_strtolower((string) ($row['visible'] ?? '')) !== 'false'),
            'price'      => $money['price'],
            'compare_at' => $money['compare_at'],
            'stock'      => pg_import_wix_stock($row['inventory'] ?? ''),
            'weight'     => pg_import_squarespace_figure($row['weight'] ?? ''),
            'barcode'    => (string) ($row['barcode'] ?? ''),
            'cost'       => $cost,
            'images'     => array(),
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
    $base = $variants ? $variants[0] : array('sku' => $stem, 'price' => null, 'compare_at' => null, 'stock' => null, 'weight' => '', 'barcode' => '', 'cost' => null, 'error' => lang('None of its variations can be imported.'));

    $categories = array();
    $names = $previous ? (string) ($product['collection'] ?? '') : (string) ($product['primarycategoryslug'] ?? '') . ';' . (string) ($product['categoryslugs'] ?? '');

    foreach (pg_import_webflow_categories($names) as $path) {
        if (!in_array($path, $categories, true)) {
            $categories[] = $path;
        }
    }

    $data = array(
        // Wix exports physical products only.
        'type'          => $simple ? 'simple' : 'variable',
        'source_type'   => $simple ? 'simple' : 'variable',
        'shippable'     => true,
        'sku'           => $simple ? $base['sku'] : $stem,
        'name'          => $title,
        'enabled'       => (mb_strtolower((string) ($product['visible'] ?? '')) !== 'false'),
        'description'   => pg_import_squarespace_description($previous ? ($product['description'] ?? '') : ($product['plaindescription'] ?? '')),
        'details'       => '',
        'keywords'      => '',
        'brand'         => (string) ($product['brand'] ?? ''),
        'barcode'       => $base['barcode'],
        'cost'          => $base['cost'],
        'categories'    => $categories,
        'images'        => array_values(array_unique($images)),
        'price'         => $base['price'],
        'compare_at'    => $base['compare_at'],
        'stock'         => $base['stock'],
        'weight'        => '',
        'weight_figure' => $base['weight'],
        'addresses'     => array(),
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

    return array('kind' => 'product', 'source_key' => mb_substr('wix:product:' . $stem, 0, 191), 'title' => $title, 'data' => $data);
}

/**
 * A variant's price and compare-at price in minor units. The current
 * layout: its own price and strikethrough price, else the product's, the
 * strikethrough price above the price the compare-at. The previous one: the product's price plus
 * the variant's surcharge, which a cheaper variant has below zero (or the
 * variant's own price), never below zero, less the product's
 * discount, the price before the discount the compare-at. 'refused' names
 * a cell that is not an amount. Pure but for the price reader's file.
 *
 * @param array $product  the product row
 * @param array $row      the variant's row (the product row for a simple product)
 * @param bool  $previous the previous layout
 * @return array{price:int|null,compare_at:int|null,refused:string}
 */
function pg_import_wix_money($product, $row, $previous)
{
    $money = array('price' => null, 'compare_at' => null, 'refused' => '');
    $cells = $previous
        ? array('price' => ((string) ($row['price'] ?? '') !== '') ? $row['price'] : ($product['price'] ?? ''), 'surcharge' => ($row === $product) ? '' : ($row['surcharge'] ?? ''))
        : (((string) ($row['price'] ?? '') !== '')
            ? array('price' => $row['price'], 'compare_at' => $row['strikethroughprice'] ?? '')
            : array('price' => $product['price'] ?? '', 'compare_at' => $product['strikethroughprice'] ?? ''));
    $cents = array();

    foreach ($cells as $part => $cell) {
        $cell = trim((string) $cell);

        if ($cell === '') {
            $cents[$part] = null;
            continue;
        }

        // A variant cheaper than its product has a negative surcharge.
        $minus = ($part === 'surcharge') && (strncmp($cell, '-', 1) === 0);
        $cents[$part] = pg_import_wix_cents($minus ? substr($cell, 1) : $cell);

        if ($minus && ($cents[$part] !== null)) {
            $cents[$part] = -$cents[$part];
        }

        if ($cents[$part] === null) {
            $money['refused'] = lang(array('string' => 'The price {var:1} is not an amount.', 'vars' => array($cell)));

            return $money;
        }
    }

    if ($cents['price'] === null) {
        return $money;
    }

    if ($previous) {
        $full = max(0, $cents['price'] + (int) $cents['surcharge']);
        $money['price'] = pg_import_wix_discounted($full, (string) ($product['discountmode'] ?? ''), (string) ($product['discountvalue'] ?? ''));
        $money['compare_at'] = ($money['price'] < $full) ? $full : null;

        return $money;
    }

    $money['price'] = $cents['price'];
    $money['compare_at'] = (($cents['compare_at'] !== null) && ($cents['compare_at'] > $cents['price'])) ? $cents['compare_at'] : null;

    return $money;
}
