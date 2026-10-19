<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product feeds for shopping agents and merchant platforms: the shop's
 * sellable products as an OpenAI product feed (JSONL or CSV) and a Google
 * Merchant Center file (TSV), built from one read of the catalog.
 *
 * The work is split in three so most of it can be tested without a database:
 *
 *   - pg_product_feed_products() reads the products a visitor can reach in
 *     the catalog, in chunks, with their groups, gallery, choices and offer
 *     prices, and hands each one to pg_product_feed_item(), which turns the
 *     row into a format-neutral item (absolute URLs, plain text, price in
 *     minor units, availability, variant family);
 *   - pg_product_feed_openai_row() and pg_product_feed_google_row() map an
 *     item onto one format's fields, or say why the format cannot carry it;
 *   - pg_product_feed_jsonl(), pg_product_feed_csv() and pg_product_feed_tsv()
 *     serialise the rows.
 *
 * pg_product_feed_render() joins the last two for one format and
 * pg_product_feed_build() runs everything. Nothing here writes a file or
 * answers a request; the caller stores or serves the body.
 *
 * The catalog page's ?rss=true feed (_pg_build_product_rss_item()) stays as
 * it is; its title, availability and product type rules are reused here so a
 * product is described the same way in every feed.
 *
 * Field rules follow the OpenAI product feed specification (nine required
 * fields: item_id, title, description, url, brand, seller_name, image_url,
 * availability, price) and the Google Merchant Center product data columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Longest title and description both specifications accept.
if (!defined('PG_PRODUCT_FEED_TITLE_MAX')) {
    define('PG_PRODUCT_FEED_TITLE_MAX', 150);
}

if (!defined('PG_PRODUCT_FEED_DESCRIPTION_MAX')) {
    define('PG_PRODUCT_FEED_DESCRIPTION_MAX', 5000);
}

// Gallery images sent beside the main image (Google reads at most 10).
if (!defined('PG_PRODUCT_FEED_EXTRA_IMAGES')) {
    define('PG_PRODUCT_FEED_EXTRA_IMAGES', 10);
}

// Products read per query, so a large catalog's descriptions are never all in
// memory at once.
if (!defined('PG_PRODUCT_FEED_CHUNK')) {
    define('PG_PRODUCT_FEED_CHUNK', 500);
}

// The formats a feed can be built in: file extension and the Content-Type
// feed.php answers with.
function pg_product_feed_formats()
{
    return array(
        'openai_jsonl' => array('extension' => 'jsonl', 'content_type' => 'application/jsonl; charset=utf-8'),
        'openai_csv'   => array('extension' => 'csv', 'content_type' => 'text/csv; charset=utf-8'),
        'google_tsv'   => array('extension' => 'tsv', 'content_type' => 'text/tab-separated-values; charset=utf-8'),
    );
}

// Column order of each format. A writer keeps this order and leaves out a
// column no row fills.
function pg_product_feed_columns($format)
{
    if ($format === 'google_tsv') {
        return array(
            'id', 'title', 'description', 'link', 'image_link', 'additional_image_link',
            'availability', 'price', 'sale_price', 'brand', 'gtin', 'mpn', 'identifier_exists',
            'condition', 'item_group_id', 'product_type', 'google_product_category',
        );
    }

    return array(
        'item_id', 'title', 'description', 'url', 'brand', 'seller_name', 'image_url',
        'availability', 'price', 'sale_price', 'additional_image_urls', 'gtin', 'mpn',
        'condition', 'product_category', 'group_id', 'listing_has_variations', 'variant_dict',
        'is_eligible_search', 'is_eligible_checkout', 'seller_url', 'seller_privacy_policy',
        'seller_tos',
    );
}

// The columns a format requires on every row: what an empty feed's header
// still names.
function pg_product_feed_required_columns($format)
{
    if ($format === 'google_tsv') {
        return array('id', 'title', 'description', 'link', 'image_link', 'availability', 'price');
    }

    return array('item_id', 'title', 'description', 'url', 'brand', 'seller_name', 'image_url', 'availability', 'price');
}

// ---------------------------------------------------------------------------
// Values
// ---------------------------------------------------------------------------

// "79.99 TRY": minor units with two decimals and the upper-case ISO 4217
// code, built from integers so no float rounding reaches the price.
function pg_product_feed_money($minor_units, $currency)
{
    $minor_units = (int) round((float) $minor_units);
    $sign = ($minor_units < 0) ? '-' : '';
    $minor_units = abs($minor_units);

    return $sign . intdiv($minor_units, 100) . '.' . sprintf('%02d', $minor_units % 100)
        . ' ' . strtoupper(trim((string) $currency));
}

// A GTIN the feeds accept: 8, 12, 13 or 14 digits with a valid GS1 check
// digit. Spaces and dashes typed into the product screen are dropped; any
// other value gives '' so the field is left out rather than rejected.
function pg_product_feed_gtin($value)
{
    $digits = str_replace(array(' ', '-'), '', trim((string) $value));

    if (($digits === '') || !ctype_digit($digits) || !in_array(strlen($digits), array(8, 12, 13, 14), true)) {
        return '';
    }

    // GS1 mod 10: from the right, after the check digit, weights 3, 1, 3, ...
    $sum = 0;
    $body = substr($digits, 0, -1);
    $weight = 3;

    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += ((int) $body[$i]) * $weight;
        $weight = ($weight === 3) ? 1 : 3;
    }

    $check = (10 - ($sum % 10)) % 10;

    return ($check === (int) substr($digits, -1)) ? $digits : '';
}

// A one-line text field (title, meta description) as feed text: invalid
// UTF-8 repaired, entities decoded (product text is stored entity-encoded),
// white space collapsed, then cut to $max characters. Angle brackets stay:
// these fields are not HTML, so "<10 cm" is text.
function pg_product_feed_text($value, $max = 0)
{
    $text = (string) $value;

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $collapsed = preg_replace('/[\s\x{00A0}\x{200B}]+/u', ' ', $text);
    $text = trim(($collapsed === null) ? preg_replace('/\s+/', ' ', $text) : $collapsed);

    return ($max > 0) ? pg_product_feed_truncate($text, $max) : $text;
}

// Rich text (the full description) as one line of plain text: scripts and
// styles dropped, block ends turned into spaces so words of two paragraphs do
// not run together, tags removed, then pg_product_feed_text().
function pg_product_feed_plain_text($html, $max = 0)
{
    $text = (string) $html;

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $text);
    $text = preg_replace('#<(br|hr)\b[^>]*>|</(p|div|li|ul|ol|h[1-6]|tr|td|th|table|blockquote|section|article)\s*>#i', ' ', (string) $text);

    return pg_product_feed_text(strip_tags((string) $text), $max);
}

// $text cut to $max characters, at the last space when one falls in the last
// fifth so a word is not split.
function pg_product_feed_truncate($text, $max)
{
    $text = (string) $text;
    $max = (int) $max;

    if (($max <= 0) || (mb_strlen($text, 'UTF-8') <= $max)) {
        return $text;
    }

    $cut = mb_substr($text, 0, $max, 'UTF-8');
    $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

    if (($space !== false) && ($space >= (int) floor($max * 0.8))) {
        $cut = mb_substr($cut, 0, $space, 'UTF-8');
    }

    return rtrim($cut);
}

// in_stock, out_of_stock or backorder, by the rule the catalog and the RSS
// feed use (_pg_product_is_out_of_stock()): only a product whose stock is
// tracked can run out, and one that takes back orders keeps selling.
function pg_product_feed_availability($product)
{
    if (empty($product['inventory']) || ((int) $product['inventory_quantity'] > 0)) {
        return 'in_stock';
    }

    return empty($product['backorder']) ? 'out_of_stock' : 'backorder';
}

// Lower-case file extension of an image address, without its query string.
function pg_product_feed_image_extension($url)
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $dot = strrpos($path, '.');

    return ($dot === false) ? '' : strtolower(substr($path, $dot + 1));
}

// ---------------------------------------------------------------------------
// Catalog structure
// ---------------------------------------------------------------------------

// The groups a visitor can reach, from the enabled product_groups rows
// (id, parent_id, name, display_type) and the groups the shop's catalogs
// show ($root_ids, pg_product_feed_catalog_roots()): every root and every
// group under it through enabled groups only. A group under a disabled one,
// or outside every catalog (the dues, exam or event registration groups a
// template keeps beside the shop), is not on the shop's shelves.
//
// Returns gid => array('path' => 'Root > Child', 'select' => bool,
// 'excluded' => bool): path is the " > " trail Google reads as product_type,
// starting at the catalog's group as the RSS feed's does
// (_pg_catalog_group_paths()); select marks a variant chooser group; excluded
// marks a group in the subtree of one of $excluded_ids. A root inside another
// root's subtree is reached from the outer one and keeps its trail.
function pg_product_feed_group_index($groups, $root_ids, $excluded_ids = array())
{
    $by_id = array();
    $kids = array();

    foreach ((array) $groups as $group) {
        $id = (int) $group['id'];

        if ($id <= 0) {
            continue;
        }

        $by_id[$id] = $group;
        $kids[(int) $group['parent_id']][] = $id;
    }

    $excluded = array();

    foreach ((array) $excluded_ids as $excluded_id) {
        $excluded_id = (int) $excluded_id;

        if ($excluded_id > 0) {
            $excluded[$excluded_id] = true;
        }
    }

    $index = array();
    $stack = array();

    $roots = array_unique(array_map('intval', (array) $root_ids));
    sort($roots);
    $root_set = array_flip($roots);

    foreach ($roots as $root_id) {
        if (!isset($by_id[$root_id])) {
            continue;
        }

        // A root under another root, through enabled groups, is reached from
        // that one and keeps its longer trail.
        $parent_id = (int) $by_id[$root_id]['parent_id'];
        $nested = false;
        $guard = 1000;

        while (($parent_id > 0) && isset($by_id[$parent_id]) && ($guard-- > 0)) {
            if (isset($root_set[$parent_id])) {
                $nested = true;
                break;
            }

            $parent_id = (int) $by_id[$parent_id]['parent_id'];
        }

        if (!$nested) {
            $stack[] = array($root_id, pg_product_feed_text($by_id[$root_id]['name']), isset($excluded[$root_id]));
        }
    }

    // Children are pushed in reverse so they come off the stack in row
    // order; a group already indexed is skipped, which also breaks a cycle.
    $stack = array_reverse($stack);
    $guard = 100000;

    while ($stack && ($guard-- > 0)) {
        list($id, $path, $is_excluded) = array_pop($stack);

        if (isset($index[$id])) {
            continue;
        }

        $index[$id] = array(
            'path'     => $path,
            'select'   => (isset($by_id[$id]['display_type']) && ($by_id[$id]['display_type'] === 'select')),
            'excluded' => $is_excluded,
        );

        if (!isset($kids[$id])) {
            continue;
        }

        foreach (array_reverse($kids[$id]) as $child_id) {
            $stack[] = array(
                $child_id,
                $path . ' > ' . pg_product_feed_text($by_id[$child_id]['name']),
                ($is_excluded || isset($excluded[$child_id])),
            );
        }
    }

    return $index;
}

// Where a product sits in the catalog, from its group ids (in the shop's
// sort order) and the group index: 'skip' => '' | 'not_in_catalog' |
// 'excluded_group', the deepest category path, and the first variant chooser
// group as the variant family ('' when it has none).
function pg_product_feed_placement($group_ids, $index)
{
    $placement = array('skip' => 'not_in_catalog', 'category_path' => '', 'group_id' => 0);

    foreach ((array) $group_ids as $group_id) {
        $group_id = (int) $group_id;

        if (!isset($index[$group_id])) {
            continue;
        }

        // One excluded group is enough: the merchant asked for the products
        // under it to stay out, wherever else they are also listed.
        if ($index[$group_id]['excluded']) {
            return array('skip' => 'excluded_group', 'category_path' => '', 'group_id' => 0);
        }

        $placement['skip'] = '';
        $path = $index[$group_id]['path'];

        if (substr_count($path, ' > ') > substr_count($placement['category_path'], ' > ') || ($placement['category_path'] === '')) {
            $placement['category_path'] = $path;
        }

        if ($index[$group_id]['select'] && ($placement['group_id'] === 0)) {
            $placement['group_id'] = $group_id;
        }
    }

    return $placement;
}

// ---------------------------------------------------------------------------
// Item
// ---------------------------------------------------------------------------

// Why a product row is not offered at all, or '' when it is. A product needs
// its own address on the detail page, a fixed positive price, and must not
// be a donation (the visitor names the amount) or a recurring payment (a
// subscription price, which neither format carries).
function pg_product_feed_row_skip($product)
{
    if (trim((string) (isset($product['address_name']) ? $product['address_name'] : '')) === '') {
        return 'no_address';
    }

    if (isset($product['selection_type']) && ($product['selection_type'] === 'donation')) {
        return 'donation';
    }

    if (!empty($product['recurring'])) {
        return 'recurring';
    }

    if ((int) (isset($product['price']) ? $product['price'] : 0) <= 0) {
        return 'no_price';
    }

    return '';
}

// One product row as a format-neutral item.
//
// $extra: 'category_path', 'group_id' (variant chooser group, 0 for none),
// 'gallery' (file names after the main image), 'choices' (attribute label =>
// option label) and 'sale_price' (offer price in minor units or null).
// $site: 'base_url' (scheme, host and site path, ending in "/"),
// 'detail_page' (name of the page products open on), 'currency' and
// 'default_brand' (used when the product has none).
function pg_product_feed_item($product, $extra, $site)
{
    $base_url = (string) $site['base_url'];
    $detail_page = (string) $site['detail_page'];

    $image_urls = array();

    foreach (array_merge(array(isset($product['image_name']) ? $product['image_name'] : ''), isset($extra['gallery']) ? (array) $extra['gallery'] : array()) as $file_name) {
        $file_name = trim((string) $file_name);

        if ($file_name === '') {
            continue;
        }

        $image_url = $base_url . encode_url_path($file_name);

        if (!in_array($image_url, $image_urls, true)) {
            $image_urls[] = $image_url;
        }
    }

    $image_urls = array_slice($image_urls, 0, PG_PRODUCT_FEED_EXTRA_IMAGES + 1);

    // The same title the RSS feed and the product's structured data show.
    $title = pg_product_feed_text(_pg_product_rss_title($product), PG_PRODUCT_FEED_TITLE_MAX);

    // The full description first: an agent answers questions from it, and the
    // meta description is a search snippet of a sentence or two.
    $description = '';

    if (!empty($product['full_description'])) {
        $description = pg_product_feed_plain_text($product['full_description'], PG_PRODUCT_FEED_DESCRIPTION_MAX);
    }

    foreach (array('meta_description', 'short_description') as $column) {
        if (($description === '') && !empty($product[$column])) {
            $description = pg_product_feed_text($product[$column], PG_PRODUCT_FEED_DESCRIPTION_MAX);
        }
    }

    $price = (int) $product['price'];
    $sale_price = null;

    if (isset($extra['sale_price']) && ($extra['sale_price'] !== null)) {
        $offer_price = (int) round((float) $extra['sale_price']);

        // Only a price that really undercuts the regular one is a sale price.
        if (($offer_price > 0) && ($offer_price < $price)) {
            $sale_price = $offer_price;
        }
    }

    $brand = pg_product_feed_text(isset($product['brand']) ? $product['brand'] : '');

    if ($brand === '') {
        $brand = pg_product_feed_text(isset($site['default_brand']) ? $site['default_brand'] : '');
    }

    $choices = array();

    foreach (isset($extra['choices']) ? (array) $extra['choices'] : array() as $label => $option) {
        // Shops write the label as it stands before the drop-down ("Renk:");
        // the feed wants the attribute's name.
        $label = rtrim(pg_product_feed_text($label), ': ');
        $option = pg_product_feed_text($option);

        if (($label !== '') && ($option !== '')) {
            $choices[$label] = $option;
        }
    }

    $group_id = isset($extra['group_id']) ? (int) $extra['group_id'] : 0;

    return array(
        'id'                      => (string) (int) $product['id'],
        'title'                   => $title,
        'description'             => ($description !== '') ? $description : $title,
        'url'                     => $base_url . (($detail_page !== '') ? encode_url_path($detail_page) . '/' : '') . encode_url_path(trim((string) $product['address_name'])),
        'image_urls'              => $image_urls,
        'price'                   => $price,
        'sale_price'              => $sale_price,
        'currency'                => strtoupper(trim((string) $site['currency'])),
        'availability'            => pg_product_feed_availability($product),
        'brand'                   => $brand,
        'gtin'                    => pg_product_feed_gtin(isset($product['gtin']) ? $product['gtin'] : ''),
        'mpn'                     => pg_product_feed_text(isset($product['mpn']) ? $product['mpn'] : ''),
        'condition'               => 'new',
        'category_path'           => isset($extra['category_path']) ? (string) $extra['category_path'] : '',
        'google_product_category' => pg_product_feed_text(isset($product['google_product_category']) ? $product['google_product_category'] : ''),
        // Prefixed so it can never equal an item id, which the OpenAI
        // specification requires of a variant family id.
        'group_id'                => ($group_id > 0) ? 'G' . $group_id : '',
        'choices'                 => ($group_id > 0) ? $choices : array(),
    );
}

// ---------------------------------------------------------------------------
// Format rows
// ---------------------------------------------------------------------------

// The OpenAI product feed row of an item, or null with $reason set when the
// row would be rejected: no title, no brand, no JPEG or PNG image (the
// specification asks for one of those two), no price.
//
// $seller: 'name' (required on every row), 'url', 'privacy_policy', 'terms'.
// Checkout through the agent is never offered: it needs the storefront API
// (checkout sessions) this site does not serve yet, so is_eligible_checkout
// is always false and the policy links are informational.
function pg_product_feed_openai_row($item, $seller, &$reason = null)
{
    $reason = '';

    $images = array();

    foreach ($item['image_urls'] as $image_url) {
        if (in_array(pg_product_feed_image_extension($image_url), array('jpg', 'jpeg', 'png'), true)) {
            $images[] = $image_url;
        }
    }

    if ($item['title'] === '') {
        $reason = 'no_title';
    } elseif ($item['brand'] === '') {
        $reason = 'no_brand';
    } elseif (!$images) {
        $reason = 'no_image';
    } elseif ($item['price'] <= 0) {
        $reason = 'no_price';
    }

    if ($reason !== '') {
        return null;
    }

    $row = array(
        'item_id'      => $item['id'],
        'title'        => $item['title'],
        'description'  => $item['description'],
        'url'          => $item['url'],
        'brand'        => $item['brand'],
        'seller_name'  => (string) $seller['name'],
        'image_url'    => $images[0],
        'availability' => $item['availability'],
        'price'        => pg_product_feed_money($item['price'], $item['currency']),
    );

    if ($item['sale_price'] !== null) {
        $row['sale_price'] = pg_product_feed_money($item['sale_price'], $item['currency']);
    }

    if (count($images) > 1) {
        $row['additional_image_urls'] = array_slice($images, 1);
    }

    if ($item['gtin'] !== '') {
        $row['gtin'] = $item['gtin'];
    }

    if ($item['mpn'] !== '') {
        $row['mpn'] = $item['mpn'];
    }

    $row['condition'] = $item['condition'];

    if ($item['category_path'] !== '') {
        $row['product_category'] = $item['category_path'];
    }

    if ($item['group_id'] !== '') {
        $row['group_id'] = $item['group_id'];
        $row['listing_has_variations'] = true;

        if ($item['choices']) {
            // An object even when every label is numeric.
            $row['variant_dict'] = (object) $item['choices'];
        }
    }

    $row['is_eligible_search'] = true;
    $row['is_eligible_checkout'] = false;

    foreach (array('url' => 'seller_url', 'privacy_policy' => 'seller_privacy_policy', 'terms' => 'seller_tos') as $key => $column) {
        if (isset($seller[$key]) && (trim((string) $seller[$key]) !== '')) {
            $row[$column] = trim((string) $seller[$key]);
        }
    }

    return $row;
}

// The Google Merchant Center row of an item, or null with $reason set when
// Merchant Center would reject it outright (no title, no image, no price).
// Fields mirror the RSS feed's g: set. A product that takes back orders is
// in_stock, as in that feed: Google's backorder needs an availability date
// the shop does not keep. Without a GTIN or MPN, identifier_exists is "no".
function pg_product_feed_google_row($item, &$reason = null)
{
    $reason = '';

    $images = array();

    foreach ($item['image_urls'] as $image_url) {
        if (in_array(pg_product_feed_image_extension($image_url), array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'), true)) {
            $images[] = $image_url;
        }
    }

    if ($item['title'] === '') {
        $reason = 'no_title';
    } elseif (!$images) {
        $reason = 'no_image';
    } elseif ($item['price'] <= 0) {
        $reason = 'no_price';
    }

    if ($reason !== '') {
        return null;
    }

    $row = array(
        'id'           => $item['id'],
        'title'        => $item['title'],
        'description'  => $item['description'],
        'link'         => $item['url'],
        'image_link'   => $images[0],
    );

    if (count($images) > 1) {
        $row['additional_image_link'] = array_slice($images, 1);
    }

    $row['availability'] = ($item['availability'] === 'out_of_stock') ? 'out_of_stock' : 'in_stock';
    $row['price'] = pg_product_feed_money($item['price'], $item['currency']);

    if ($item['sale_price'] !== null) {
        $row['sale_price'] = pg_product_feed_money($item['sale_price'], $item['currency']);
    }

    if ($item['brand'] !== '') {
        $row['brand'] = $item['brand'];
    }

    if ($item['gtin'] !== '') {
        $row['gtin'] = $item['gtin'];
    }

    if ($item['mpn'] !== '') {
        $row['mpn'] = $item['mpn'];
    }

    if (($item['gtin'] === '') && ($item['mpn'] === '')) {
        $row['identifier_exists'] = 'no';
    }

    $row['condition'] = $item['condition'];

    if ($item['group_id'] !== '') {
        $row['item_group_id'] = $item['group_id'];
    }

    if ($item['category_path'] !== '') {
        $row['product_type'] = $item['category_path'];
    }

    if ($item['google_product_category'] !== '') {
        $row['google_product_category'] = $item['google_product_category'];
    }

    return $row;
}

// ---------------------------------------------------------------------------
// Writers
// ---------------------------------------------------------------------------

// One JSON object per line (JSON Lines), UTF-8, slashes and letters as they
// are. A byte that is not UTF-8 becomes U+FFFD instead of dropping the row
// (PHP 7.2+; 7.1 drops the row's line).
function pg_product_feed_jsonl($rows)
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $lines = array();

    foreach ((array) $rows as $row) {
        $line = json_encode($row, $flags);

        if ($line !== false) {
            $lines[] = $line;
        }
    }

    return $lines ? implode("\n", $lines) . "\n" : '';
}

// One field as text for CSV and TSV: true/false, a list joined with commas
// (a comma inside an address is percent-encoded, as both specifications
// ask), an object (variant_dict) as JSON.
function pg_product_feed_cell($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_object($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (is_array($value)) {
        return implode(',', str_replace(',', '%2C', array_map('strval', $value)));
    }

    return (string) $value;
}

// The columns of $format that at least one row fills, in the format's order.
function pg_product_feed_used_columns($format, $rows)
{
    $used = array();

    foreach ((array) $rows as $row) {
        foreach ($row as $key => $value) {
            $used[$key] = true;
        }
    }

    $columns = array();

    foreach (pg_product_feed_columns($format) as $column) {
        if (isset($used[$column])) {
            $columns[] = $column;
        }
    }

    return $columns;
}

// Comma-separated values (RFC 4180): a header line, then one line per row; a
// field holding a quote, comma, line break or edge space is quoted with its
// quotes doubled. Written by hand rather than with fputcsv(), whose escape
// character would add backslash rules the readers do not know.
function pg_product_feed_csv($rows, $columns)
{
    $quote = function ($text) {
        $text = (string) $text;

        if (($text !== trim($text)) || (strpbrk($text, "\",\r\n") !== false)) {
            return '"' . str_replace('"', '""', $text) . '"';
        }

        return $text;
    };

    $lines = array(implode(',', array_map($quote, $columns)));

    foreach ((array) $rows as $row) {
        $cells = array();

        foreach ($columns as $column) {
            $cells[] = $quote(isset($row[$column]) ? pg_product_feed_cell($row[$column]) : '');
        }

        $lines[] = implode(',', $cells);
    }

    return implode("\n", $lines) . "\n";
}

// Tab-separated values as Merchant Center reads them: a header line, then one
// line per row; tabs and line breaks inside a value become spaces, since the
// format has no quoting a reader can rely on.
function pg_product_feed_tsv($rows, $columns)
{
    $clean = function ($text) {
        return str_replace(array("\r\n", "\r", "\n", "\t"), ' ', (string) $text);
    };

    $lines = array(implode("\t", array_map($clean, $columns)));

    foreach ((array) $rows as $row) {
        $cells = array();

        foreach ($columns as $column) {
            $cells[] = $clean(isset($row[$column]) ? pg_product_feed_cell($row[$column]) : '');
        }

        $lines[] = implode("\t", $cells);
    }

    return implode("\n", $lines) . "\n";
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------

// The body of one format from prepared items. $options: 'seller_name',
// 'seller_url', 'privacy_policy_url', 'terms_url'.
//
// Returns array('ok' => bool, 'error' => '' | 'unknown_format' |
// 'no_seller_name', 'body' => string, 'count' => rows written,
// 'skipped' => reason => count of items the format could not carry).
function pg_product_feed_render($format, $items, $options = array())
{
    $result = array('ok' => false, 'error' => '', 'body' => '', 'count' => 0, 'skipped' => array());
    $formats = pg_product_feed_formats();

    if (!isset($formats[$format])) {
        $result['error'] = 'unknown_format';
        return $result;
    }

    $openai = (strpos($format, 'openai_') === 0);

    $seller = array(
        'name'           => trim((string) (isset($options['seller_name']) ? $options['seller_name'] : '')),
        'url'            => isset($options['seller_url']) ? (string) $options['seller_url'] : '',
        'privacy_policy' => isset($options['privacy_policy_url']) ? (string) $options['privacy_policy_url'] : '',
        'terms'          => isset($options['terms_url']) ? (string) $options['terms_url'] : '',
    );

    if ($openai && ($seller['name'] === '')) {
        $result['error'] = 'no_seller_name';
        return $result;
    }

    $rows = array();

    foreach ((array) $items as $item) {
        $reason = '';
        $row = $openai ? pg_product_feed_openai_row($item, $seller, $reason) : pg_product_feed_google_row($item, $reason);

        if ($row === null) {
            $result['skipped'][$reason] = (isset($result['skipped'][$reason]) ? $result['skipped'][$reason] : 0) + 1;
            continue;
        }

        $rows[] = $row;
    }

    if ($format === 'openai_jsonl') {
        $result['body'] = pg_product_feed_jsonl($rows);
    } else {
        $columns = pg_product_feed_used_columns($format, $rows);

        // An empty feed still names its columns, so a platform reading it
        // sees an empty catalog rather than a broken file.
        if (!$columns) {
            $columns = pg_product_feed_required_columns($format);
        }

        $result['body'] = ($format === 'openai_csv') ? pg_product_feed_csv($rows, $columns) : pg_product_feed_tsv($rows, $columns);
    }

    $result['ok'] = true;
    $result['count'] = count($rows);

    return $result;
}

// The site values pg_product_feed_item() needs, from the running site.
// 'error' is 'no_detail_page' when the site has no page products open on,
// 'detail_page_not_public' when that page sits in a folder visitors cannot
// open: either way no product address in the feed would work.
function pg_product_feed_site($options = array())
{
    $site = array(
        'error'         => '',
        'base_url'      => URL_SCHEME . HOSTNAME_SETTING . PATH,
        'detail_page'   => '',
        'currency'      => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
        'default_brand' => isset($options['default_brand']) ? (string) $options['default_brand'] : '',
    );

    $site['detail_page'] = function_exists('pg_sw_catalog_detail_page_name') ? (string) pg_sw_catalog_detail_page_name(0) : '';

    if ($site['detail_page'] === '') {
        $site['error'] = 'no_detail_page';
        return $site;
    }

    $folder_id = (int) db_value("SELECT page_folder FROM page WHERE page_name = '" . e($site['detail_page']) . "' LIMIT 1");

    if (($folder_id <= 0) || (get_access_control_type($folder_id) !== 'public')) {
        $site['error'] = 'detail_page_not_public';
    }

    return $site;
}

// The product groups the shop's catalogs show, as the sitemap finds them: the
// group of each legacy catalog page and of each Catalog widget on a designed
// page (0 there means the top-level group), when the page sits in a folder
// visitors can open. A page in the Recycle Bin is left out by
// pg_sw_widget_pages().
function pg_product_feed_catalog_roots()
{
    $top_group_id = (int) db_value("SELECT id FROM product_groups WHERE parent_id = '0' ORDER BY id ASC LIMIT 1");
    $public = array();

    $is_public = function ($folder_id) use (&$public) {
        $folder_id = (int) $folder_id;

        if (!isset($public[$folder_id])) {
            $public[$folder_id] = ($folder_id > 0) && (get_access_control_type($folder_id) === 'public');
        }

        return $public[$folder_id];
    };

    $roots = array();

    foreach (db_items(
        "SELECT catalog_pages.product_group_id, page.page_folder
        FROM catalog_pages
        INNER JOIN page ON page.page_id = catalog_pages.page_id
        WHERE page.page_type = 'catalog'") as $row) {
        $group_id = ((int) $row['product_group_id'] > 0) ? (int) $row['product_group_id'] : $top_group_id;

        if (($group_id > 0) && $is_public($row['page_folder'])) {
            $roots[$group_id] = true;
        }
    }

    if (function_exists('pg_sw_widget_pages') && function_exists('_pg_find_system_widget_on_page')) {
        foreach (pg_sw_widget_pages('catalog_listing') as $host) {
            $folder_id = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) $host['page_id'] . "' LIMIT 1");

            if (!$is_public($folder_id)) {
                continue;
            }

            $listing = _pg_find_system_widget_on_page((int) $host['page_id'], 'catalog_listing');

            if (!$listing) {
                continue;
            }

            $group_id = ((int) $listing['product_group_id'] > 0) ? (int) $listing['product_group_id'] : $top_group_id;

            if ($group_id > 0) {
                $roots[$group_id] = true;
            }
        }
    }

    $roots = array_keys($roots);
    sort($roots);

    return $roots;
}

// Every product a visitor can buy from the catalog, as items.
//
// A product is offered when it is enabled and sits in at least one group a
// catalog shows (pg_product_feed_catalog_roots(),
// pg_product_feed_group_index()), none of them under one of
// $options['excluded_group_ids'], and pg_product_feed_row_skip() finds
// nothing against it. Offer prices are the ones any visitor gets, without a
// code (get_discounted_product_prices(false)), as on the catalog page.
//
// $options: 'excluded_group_ids', 'default_brand'.
// Returns array('error' => '' | 'ecommerce_off' | 'no_catalog' | a
// pg_product_feed_site() error, 'items' => list, 'skipped' => reason =>
// count).
function pg_product_feed_products($options = array())
{
    $result = array('error' => '', 'items' => array(), 'skipped' => array());

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        $result['error'] = 'ecommerce_off';
        return $result;
    }

    $site = pg_product_feed_site($options);

    if ($site['error'] !== '') {
        $result['error'] = $site['error'];
        return $result;
    }

    $roots = pg_product_feed_catalog_roots();

    if (!$roots) {
        $result['error'] = 'no_catalog';
        return $result;
    }

    $index = pg_product_feed_group_index(
        db_items("SELECT id, parent_id, name, display_type FROM product_groups WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"),
        $roots,
        isset($options['excluded_group_ids']) ? $options['excluded_group_ids'] : array()
    );

    $sale_prices = function_exists('get_discounted_product_prices') ? get_discounted_product_prices(false) : array();

    if (!is_array($sale_prices)) {
        $sale_prices = array();
    }

    $skip = function ($reason) use (&$result) {
        $result['skipped'][$reason] = (isset($result['skipped'][$reason]) ? $result['skipped'][$reason] : 0) + 1;
    };

    $last_id = 0;

    do {
        $products = db_items(
            "SELECT id, name, title, short_description, full_description, meta_description,
                price, image_name, address_name, selection_type, recurring,
                inventory, inventory_quantity, backorder,
                gtin, brand, mpn, google_product_category
            FROM products
            WHERE (enabled = 1) AND (id > " . (int) $last_id . ")
            ORDER BY id ASC
            LIMIT " . (int) PG_PRODUCT_FEED_CHUNK);

        if (!is_array($products) || !$products) {
            break;
        }

        $last_id = (int) $products[count($products) - 1]['id'];
        $ids = implode(',', array_map('intval', array_column($products, 'id')));

        $groups = array();

        foreach ((array) db_items("SELECT product, product_group FROM products_groups_xref
            WHERE product IN (" . $ids . ")
            ORDER BY product ASC, sort_order ASC, product_group ASC") as $row) {
            $groups[(int) $row['product']][] = (int) $row['product_group'];
        }

        // products_images_xref has no id column: the gallery is read in file
        // name order, as the product page shows it.
        $gallery = array();

        foreach ((array) db_items("SELECT product, file_name FROM products_images_xref
            WHERE (product IN (" . $ids . ")) AND (file_name != '')
            ORDER BY product ASC, file_name ASC") as $row) {
            $gallery[(int) $row['product']][] = (string) $row['file_name'];
        }

        // The choices each variant stands for (Size: Large), by the label the
        // shop shows; the "nothing selected" option is no choice.
        $choices = array();

        foreach ((array) db_items("SELECT products_attributes_xref.product_id,
                product_attributes.name AS attribute_name,
                product_attributes.label AS attribute_label,
                product_attribute_options.label AS option_label
            FROM products_attributes_xref
            INNER JOIN product_attributes ON product_attributes.id = products_attributes_xref.attribute_id
            INNER JOIN product_attribute_options ON product_attribute_options.id = products_attributes_xref.option_id
            WHERE (products_attributes_xref.product_id IN (" . $ids . "))
                AND (product_attribute_options.no_value = 0)
            ORDER BY products_attributes_xref.product_id ASC, products_attributes_xref.sort_order ASC") as $row) {
            $label = ((string) $row['attribute_label'] !== '') ? (string) $row['attribute_label'] : (string) $row['attribute_name'];
            $choices[(int) $row['product_id']][$label] = (string) $row['option_label'];
        }

        foreach ($products as $product) {
            $id = (int) $product['id'];

            $reason = pg_product_feed_row_skip($product);

            if ($reason !== '') {
                $skip($reason);
                continue;
            }

            $placement = pg_product_feed_placement(isset($groups[$id]) ? $groups[$id] : array(), $index);

            if ($placement['skip'] !== '') {
                $skip($placement['skip']);
                continue;
            }

            $result['items'][] = pg_product_feed_item($product, array(
                'category_path' => $placement['category_path'],
                'group_id'      => $placement['group_id'],
                'gallery'       => isset($gallery[$id]) ? $gallery[$id] : array(),
                'choices'       => isset($choices[$id]) ? $choices[$id] : array(),
                'sale_price'    => isset($sale_prices[$id]) ? $sale_prices[$id] : null,
            ), $site);
        }
    } while (count($products) === PG_PRODUCT_FEED_CHUNK);

    return $result;
}

// One feed body from the running site: pg_product_feed_products() then
// pg_product_feed_render(). 'skipped' adds both steps' reasons.
function pg_product_feed_build($format, $options = array())
{
    $formats = pg_product_feed_formats();

    if (!isset($formats[$format])) {
        return array('ok' => false, 'error' => 'unknown_format', 'body' => '', 'count' => 0, 'skipped' => array());
    }

    $products = pg_product_feed_products($options);

    if ($products['error'] !== '') {
        return array('ok' => false, 'error' => $products['error'], 'body' => '', 'count' => 0, 'skipped' => $products['skipped']);
    }

    $result = pg_product_feed_render($format, $products['items'], $options);

    foreach ($products['skipped'] as $reason => $count) {
        $result['skipped'][$reason] = (isset($result['skipped'][$reason]) ? $result['skipped'][$reason] : 0) + $count;
    }

    return $result;
}
