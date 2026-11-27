<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Squarespace reader of the content import framework (includes/fn/
 * importers.php). Squarespace exports its site content as one XML file and
 * its products and customers as two CSV files; any of the three may be
 * uploaded:
 *
 * - the site content: WordPress's export format, so the WordPress reader
 *   (wordpress.php) reads it and its page, record, comment, file and author
 *   writers take the items. A post's <link> is /<blog page slug>/<post
 *   slug>, the post slug holding any date part, and becomes its redirect.
 * - products: a product's first row holds it (page, URL, title, description,
 *   categories, tags, pictures, visibility) and each row below it, with
 *   those cells blank, is one more variant (options, SKU, price, stock).
 *   Items go to the product writer (write_product.php) in the shape the
 *   Shopify reader gives it. No column names the weight unit: the figure is
 *   handed over as weight_figure and the writer converts it with the unit
 *   the import's plan step asked.
 * - customers: one contact per row (write_contact.php). The file has no
 *   consent column, so no address comes in as subscribed.
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
 * The mapping rows of the uploaded file. The site content asks what the
 * WordPress reader asks; the customers file asks nothing. The products file
 * asks the product group categories go under, the folder pictures are
 * stored in, and the unit of the Weight column, which the file does not
 * name. Pounds are chosen at first: products.weight holds pounds and the
 * product screen shows them unless its metric switch is ticked. Without a
 * file the products rows are given.
 *
 * @param string $file the uploaded export
 * @return array
 */
function pg_import_squarespace_mapping($file = '')
{
    $file = (string) $file;

    if (($file !== '') && pg_import_squarespace_is_xml(pg_import_squarespace_head($file))) {
        require_once(__DIR__ . '/wordpress.php');

        return pg_import_wordpress_mapping();
    }

    if (($file !== '') && (pg_import_squarespace_open($file)['kind'] === 'customers')) {
        return array();
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
 * A header cell as the reader names its column: lower case, single spaces,
 * without the "[Non Editable]" note Squarespace adds to some. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_squarespace_column($cell)
{
    $cell = preg_replace('/\s*\[[^\]]*\]\s*$/u', '', trim((string) $cell, " \t\"'\xEF\xBB\xBF"));

    return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $cell)));
}

/**
 * Which file this is from its header (pg_import_squarespace_column() names):
 * products, customers or ''. Pure.
 *
 * @param string[] $header
 * @return string
 */
function pg_import_squarespace_file_kind($header)
{
    $header = (array) $header;

    if (in_array('product page', $header, true) && in_array('product url', $header, true)) {
        return 'products';
    }

    if (in_array('email', $header, true) && in_array('first name', $header, true) && in_array('customer since', $header, true)) {
        return 'customers';
    }

    return '';
}

/**
 * A file's first 8 KB, '' when it cannot be read.
 *
 * @param string $file
 * @return string
 */
function pg_import_squarespace_head($file)
{
    $handle = @fopen((string) $file, 'rb');

    if (!$handle) {
        return '';
    }

    $head = (string) fread($handle, 8192);
    fclose($handle);

    return $head;
}

/**
 * Whether an export is the site content XML rather than a CSV: after a byte
 * order mark and white space it opens with a tag. Pure.
 *
 * @param string $head
 * @return bool
 */
function pg_import_squarespace_is_xml($head)
{
    $head = (string) $head;

    if (strncmp($head, "\xEF\xBB\xBF", 3) === 0) {
        $head = substr($head, 3);
    }

    return (strncmp(ltrim($head), '<', 1) === 0);
}

/**
 * The site content XML is given the WordPress reader's cursor under wxr;
 * a CSV gets the reader's own.
 *
 * @param string $file
 * @return array the cursor
 */
function pg_import_squarespace_open($file)
{
    if (pg_import_squarespace_is_xml(pg_import_squarespace_head($file))) {
        require_once(__DIR__ . '/wordpress.php');

        return array('format' => 'xml', 'wxr' => pg_import_wordpress_open($file));
    }

    $cursor = array('format' => 'csv', 'file' => (string) $file, 'offset' => -1, 'separator' => ',', 'header' => array(), 'line' => 1, 'kind' => '');
    $handle = @fopen($file, 'rb');

    if (!$handle) {
        return $cursor;
    }

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $cursor['separator'] = pg_csv_read_separator($handle);
    $cursor['header'] = array_map('pg_import_squarespace_column', (array) fgetcsv($handle, 0, $cursor['separator'], '"', '\\'));
    $cursor['kind'] = pg_import_squarespace_file_kind($cursor['header']);
    $cursor['offset'] = ($cursor['kind'] !== '') ? (int) ftell($handle) : -1;

    fclose($handle);

    return $cursor;
}

/**
 * The next item: the WordPress reader's from the site content, else a
 * product with its variant rows, or a contact. Null at the end.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_squarespace_next(&$cursor)
{
    if (($cursor['format'] ?? '') === 'xml') {
        require_once(__DIR__ . '/wordpress.php');

        $wxr = (array) $cursor['wxr'];
        $item = pg_import_wordpress_next($wxr);
        $cursor['wxr'] = $wxr;

        return ($item === null) ? null : pg_import_squarespace_content_item($item);
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

        $row = pg_import_squarespace_row($cursor['header'], $cells);

        // The next product's first row is read again by the next call.
        if (($cursor['kind'] === 'products') && $rows && pg_import_squarespace_starts_product($row, $rows[0])) {
            $cursor['offset'] = $at;
            break;
        }

        // An item is named by the line its first row starts on.
        if (!$rows) {
            $line = (int) $cursor['line'] + 1;
        }

        $cursor['line'] += pg_csv_row_lines($cells);
        $cursor['offset'] = (int) ftell($handle);
        $rows[] = $row;

        if ($cursor['kind'] === 'customers') {
            break;
        }
    }

    fclose($handle);

    if (!$rows) {
        return null;
    }

    return ($cursor['kind'] === 'products') ? pg_import_squarespace_product($rows, $line) : pg_import_squarespace_customer($rows[0], $line);
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_squarespace_plan($item)
{
    $data = (array) ($item['data'] ?? array());

    if (!in_array(($item['kind'] ?? ''), array('product', 'contact'), true)) {
        require_once(__DIR__ . '/wordpress.php');

        return pg_import_wordpress_plan($item);
    }

    if (($item['kind'] ?? '') === 'contact') {
        return array('title' => (string) ($item['title'] ?? ''), 'detail' => (string) ($data['email_address'] ?? ''));
    }

    $detail = (($data['type'] ?? '') === 'variable')
        ? lang(array('string' => 'Variant set with {var:1} variants', 'vars' => array(count((array) $data['variants']))))
        : (string) ($data['sku'] ?? '');

    return array('title' => (string) ($item['title'] ?? ''), 'detail' => $detail);
}

/**
 * An item of the site content as the WordPress reader gave it. A picture's
 * excerpt is a placeholder (exc-<id>), not a caption, and is dropped. Pure.
 *
 * @param array $item
 * @return array
 */
function pg_import_squarespace_content_item($item)
{
    if ((($item['kind'] ?? '') === 'file') && preg_match('/^exc-[0-9a-f]+$/i', (string) ($item['data']['caption'] ?? ''))) {
        $item['data']['caption'] = '';
    }

    return $item;
}

// ---- Rows ------------------------------------------------------------------

/**
 * A row as column => trimmed cell, every header column present. Pure.
 *
 * @param string[] $header
 * @param array    $cells
 * @return array<string,string>
 */
function pg_import_squarespace_row($header, $cells)
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
 * Whether a products row starts a new product rather than adding a variant
 * to the one $first started: it carries a product cell (title, URL, page),
 * or a product id other than the product's own. Pure.
 *
 * @param array $row
 * @param array $first the current product's first row
 * @return bool
 */
function pg_import_squarespace_starts_product($row, $first)
{
    foreach (array('title', 'product url', 'product page') as $column) {
        if ((string) ($row[$column] ?? '') !== '') {
            return true;
        }
    }

    $id = (string) ($row['product id'] ?? '');

    return ($id !== '') && ($id !== (string) ($first['product id'] ?? ''));
}

/**
 * The old addresses of a product: /<page>/<url> on Squarespace 7.0 and
 * /<page>/p/<url> on 7.1. The file does not say which version the site ran,
 * so both are given. Pure.
 *
 * @param string $page the Product Page cell
 * @param string $url  the Product URL cell
 * @return string[]
 */
function pg_import_squarespace_product_addresses($page, $url)
{
    $page = trim((string) $page, " /");
    $url = trim((string) $url, " /");

    if (($page === '') || ($url === '')) {
        return array();
    }

    return array('/' . $page . '/' . $url, '/' . $page . '/p/' . $url);
}

/**
 * A decimal cell as a number string, '' when empty or not a number; a lone
 * decimal comma is read. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_squarespace_figure($cell)
{
    $figure = trim((string) $cell);

    if ((strpos($figure, ',') !== false) && (strpos($figure, '.') === false)) {
        $figure = str_replace(',', '.', $figure);
    }

    return is_numeric($figure) ? $figure : '';
}

/**
 * The price and compare-at price of a row in minor units: the sale price
 * is the price while On Sale is Yes and it is below the price, which then
 * becomes the compare-at price. 'refused' names a cell that is not an
 * amount. Pure but for the price reader's file.
 *
 * @param array $row
 * @return array{price:int|null,compare_at:int|null,refused:string}
 */
function pg_import_squarespace_money($row)
{
    if (!function_exists('pg_pb_price_figure')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $money = array('price' => null, 'compare_at' => null, 'refused' => '');
    $cents = array('price' => null, 'sale price' => null);

    foreach (array_keys($cents) as $column) {
        $cell = (string) ($row[$column] ?? '');

        if ($cell === '') {
            continue;
        }

        $cents[$column] = pg_products_import_price_cents($cell);

        if ($cents[$column] === null) {
            $money['refused'] = lang(array('string' => 'The price {var:1} is not an amount.', 'vars' => array($cell)));

            return $money;
        }
    }

    $money['price'] = $cents['price'];

    if ((mb_strtolower((string) ($row['on sale'] ?? '')) === 'yes') && ($cents['sale price'] !== null) && ($cents['price'] !== null) && ($cents['sale price'] < $cents['price'])) {
        $money['price'] = $cents['sale price'];
        $money['compare_at'] = $cents['price'];
    }

    return $money;
}

/**
 * The stock of a row: a count, or null for "unlimited" and an empty cell,
 * which Squarespace does not track. Pure.
 *
 * @param array $row
 * @return int|null
 */
function pg_import_squarespace_stock($row)
{
    $cell = mb_strtolower(trim((string) ($row['stock'] ?? '')));

    if (($cell === '') || ($cell === 'unlimited') || !is_numeric($cell)) {
        return null;
    }

    return max(0, (int) $cell);
}

/**
 * The category paths of a Categories cell: comma separated /slug paths,
 * each step a name made of its slug ("summer-shirts" is "Summer shirts").
 * Pure.
 *
 * @param string $cell
 * @return array[]
 */
function pg_import_squarespace_categories($cell)
{
    $paths = array();

    foreach (explode(',', (string) $cell) as $path) {
        $steps = array();

        foreach (explode('/', $path) as $slug) {
            $name = trim(str_replace(array('-', '_'), ' ', rawurldecode(trim($slug))));

            if ($name !== '') {
                $steps[] = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
            }
        }

        if ($steps && !in_array($steps, $paths, true)) {
            $paths[] = $steps;
        }
    }

    return $paths;
}

/**
 * A Description cell as HTML: kept when it is HTML already, else its text
 * escaped with a paragraph per blank line. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_squarespace_description($cell)
{
    $text = trim(str_replace("\r\n", "\n", (string) $cell));

    if (($text === '') || preg_match('/<[a-z][^>]*>/i', $text)) {
        return $text;
    }

    $paragraphs = array();

    foreach (preg_split('/\n\s*\n/', $text) as $paragraph) {
        $paragraphs[] = '<p>' . nl2br(h(trim($paragraph)), false) . '</p>';
    }

    return implode("\n", $paragraphs);
}

/**
 * The product item of one product's rows. Pure but for the price reader.
 *
 * @param array[] $rows
 * @param int     $line the first row's line, the key when no id or URL names it
 * @return array
 */
function pg_import_squarespace_product($rows, $line)
{
    $first = $rows[0];
    $id = (string) ($first['product id'] ?? '');
    $url = (string) ($first['product url'] ?? '');
    $title = ((string) ($first['title'] ?? '') !== '') ? $first['title'] : (($url !== '') ? $url : $id);
    $type = mb_strtolower((string) ($first['product type'] ?? ''));
    $stem = ($url !== '') ? $url : (($id !== '') ? $id : 'line-' . (int) $line);

    $axes = array();

    for ($number = 1; $number <= 3; $number++) {
        foreach ($rows as $row) {
            if ((string) ($row['option name ' . $number] ?? '') !== '') {
                $axes[$number] = $row['option name ' . $number];
                break;
            }
        }
    }

    $variants = array();

    foreach ($rows as $index => $row) {
        $options = array();

        foreach ($axes as $number => $axis) {
            $options[$axis] = (string) ($row['option value ' . $number] ?? '');
        }

        $label = implode(' / ', array_filter($options, 'strlen'));
        $sku = (string) ($row['sku'] ?? '');
        $money = pg_import_squarespace_money($row);
        $variant = array(
            'source_key' => 'squarespace:variant:' . $stem . ':' . (((string) ($row['variant id'] ?? '') !== '') ? $row['variant id'] : $index),
            'sku'        => ($sku !== '') ? $sku : $stem . (($index > 0) ? '-' . ($index + 1) : ''),
            'name'       => ($label !== '') ? $title . ' - ' . $label : $title,
            'enabled'    => true,
            'price'      => $money['price'],
            'compare_at' => $money['compare_at'],
            'stock'      => pg_import_squarespace_stock($row),
            'weight'     => pg_import_squarespace_figure($row['weight'] ?? ''),
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
    $base = $variants[0];
    $images = preg_split('/\s+/', trim((string) ($first['hosted image urls'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

    $data = array(
        // Physical and service products are both a product here, a service
        // one that is not shipped. A digital product or a gift card is
        // named by its type, which the product writer skips.
        'type'          => in_array($type, array('', 'physical', 'service'), true) ? ($simple ? 'simple' : 'variable') : $type,
        'source_type'   => in_array($type, array('', 'physical', 'service'), true) ? ($simple ? 'simple' : 'variable') : $type,
        'shippable'     => ($type !== 'service'),
        'sku'           => $simple ? $base['sku'] : $stem,
        'name'          => $title,
        'enabled'       => (mb_strtolower((string) ($first['visible'] ?? '')) === 'yes'),
        'description'   => pg_import_squarespace_description($first['description'] ?? ''),
        'details'       => '',
        'keywords'      => implode(', ', array_filter(array_map('trim', explode(',', (string) ($first['tags'] ?? ''))), 'strlen')),
        'categories'    => pg_import_squarespace_categories($first['categories'] ?? ''),
        'images'        => array_values(array_unique(array_filter($images, function ($image) {
            return (bool) preg_match('#^https?://#i', $image);
        }))),
        'price'         => $base['price'],
        'compare_at'    => $base['compare_at'],
        'stock'         => $base['stock'],
        'weight'        => '',
        'weight_figure' => $base['weight'],
        'addresses'     => pg_import_squarespace_product_addresses($first['product page'] ?? '', $url),
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

    return array('kind' => 'product', 'source_key' => 'squarespace:product:' . mb_strtolower(($id !== '') ? $id : $stem), 'title' => $title, 'data' => $data);
}

/**
 * The address, city and phone cells of a customers row: billing first,
 * shipping when the billing cells are empty. Squarespace names "Last used
 * Billing Name, Address, and Phone Number"; a file that puts that in one
 * cell gives it whole as the description, one with a column per part gives
 * each part its own. Pure.
 *
 * @param array $row
 * @return array<string,string>
 */
function pg_import_squarespace_address($row)
{
    $parts = array(
        'business_address_2' => array('address 2', 'address2', 'address line 2'),
        'business_city'      => array('city'),
        'business_state'     => array('state', 'province', 'region'),
        'business_zip_code'  => array('zip', 'postal'),
        'business_country'   => array('country'),
        'business_phone'     => array('phone'),
        'business_address_1' => array('address'),
    );

    foreach (array('billing', 'shipping') as $prefix) {
        $found = array();
        $whole = '';

        foreach ($row as $column => $cell) {
            $column = (string) $column;

            if ((strpos($column, $prefix) === false) || ((string) $cell === '')) {
                continue;
            }

            // One cell for name, address and phone together.
            if ((strpos($column, 'address') !== false) && (strpos($column, 'phone') !== false)) {
                $whole = (string) $cell;
                continue;
            }

            foreach ($parts as $part => $words) {
                foreach ($words as $word) {
                    if (!isset($found[$part]) && (strpos($column, $word) !== false)) {
                        $found[$part] = (string) $cell;
                        continue 3;
                    }
                }
            }
        }

        if ($found || ($whole !== '')) {
            return $found + array('description' => $whole);
        }
    }

    return array();
}

/**
 * The contact item of a customers row. No column says the person agreed to
 * marketing mail, so opt_in is false. Pure.
 *
 * @param array $row
 * @param int   $line
 * @return array
 */
function pg_import_squarespace_customer($row, $line)
{
    $email = mb_strtolower((string) ($row['email'] ?? ''));
    $name = trim(($row['first name'] ?? '') . ' ' . ($row['last name'] ?? ''));

    $data = array_merge(array(
        'first_name'         => (string) ($row['first name'] ?? ''),
        'last_name'          => (string) ($row['last name'] ?? ''),
        'email_address'      => $email,
        'company'            => '',
        'business_address_1' => '',
        'business_address_2' => '',
        'business_city'      => '',
        'business_state'     => '',
        'business_country'   => '',
        'business_zip_code'  => '',
        'business_phone'     => '',
        'mobile_phone'       => '',
        'description'        => '',
    ), pg_import_squarespace_address($row), array(
        'opt_in' => false,
        'tags'   => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($row['tags'] ?? ''))), 'strlen'))),
    ));

    return array(
        'kind'       => 'contact',
        'source_key' => ($email !== '') ? 'squarespace:email:' . $email : 'squarespace:line:' . (int) $line,
        'title'      => ($name !== '') ? $name : $email,
        'data'       => $data,
    );
}
