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
 * pg_product_feed_render() joins the last two for one format in memory;
 * the daily build streams them to the file chunk by chunk instead
 * (pg_product_feed_writer_open()), so a large catalog is never held whole.
 *
 * Around the builder: the setup in config.product_feeds (2026.11.0, 11.0.4),
 * one secret address per format, the daily build that writes each format
 * that is on to data/feeds/ (pg_product_feed_refresh(), product_feed_job.php)
 * and the answer feed.php gives a platform reading it
 * (pg_product_feed_serve()).
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
    $lines = array();

    foreach ((array) $rows as $row) {
        $line = pg_product_feed_jsonl_line($row);

        if ($line !== false) {
            $lines[] = $line;
        }
    }

    return $lines ? implode("\n", $lines) . "\n" : '';
}

// One row's JSON Lines line, without the line break; false when it cannot be
// encoded.
function pg_product_feed_jsonl_line($row)
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE; // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined() above
    }

    return json_encode($row, $flags);
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
    $lines = array(pg_product_feed_csv_line($columns));

    foreach ((array) $rows as $row) {
        $cells = array();

        foreach ($columns as $column) {
            $cells[] = isset($row[$column]) ? pg_product_feed_cell($row[$column]) : '';
        }

        $lines[] = pg_product_feed_csv_line($cells);
    }

    return implode("\n", $lines) . "\n";
}

// One CSV line from cell texts, without the line break.
function pg_product_feed_csv_line($cells)
{
    $quoted = array();

    foreach ($cells as $text) {
        $text = (string) $text;

        if (($text !== trim($text)) || (strpbrk($text, "\",\r\n") !== false)) {
            $text = '"' . str_replace('"', '""', $text) . '"';
        }

        $quoted[] = $text;
    }

    return implode(',', $quoted);
}

// Tab-separated values as Merchant Center reads them: a header line, then one
// line per row; tabs and line breaks inside a value become spaces, since the
// format has no quoting a reader can rely on.
function pg_product_feed_tsv($rows, $columns)
{
    $lines = array(pg_product_feed_tsv_line($columns));

    foreach ((array) $rows as $row) {
        $cells = array();

        foreach ($columns as $column) {
            $cells[] = isset($row[$column]) ? pg_product_feed_cell($row[$column]) : '';
        }

        $lines[] = pg_product_feed_tsv_line($cells);
    }

    return implode("\n", $lines) . "\n";
}

// One TSV line from cell texts, without the line break.
function pg_product_feed_tsv_line($cells)
{
    $clean = array();

    foreach ($cells as $text) {
        $clean[] = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', (string) $text);
    }

    return implode("\t", $clean);
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

    $seller = pg_product_feed_seller($options);

    if ((strpos($format, 'openai_') === 0) && ($seller['name'] === '')) {
        $result['error'] = 'no_seller_name';
        return $result;
    }

    $rows = pg_product_feed_rows($format, $items, $seller, $result['skipped']);

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

// The seller fields the OpenAI rows carry, from the render options.
function pg_product_feed_seller($options)
{
    return array(
        'name'           => trim((string) (isset($options['seller_name']) ? $options['seller_name'] : '')),
        'url'            => isset($options['seller_url']) ? (string) $options['seller_url'] : '',
        'privacy_policy' => isset($options['privacy_policy_url']) ? (string) $options['privacy_policy_url'] : '',
        'terms'          => isset($options['terms_url']) ? (string) $options['terms_url'] : '',
    );
}

// The rows of $format for these items; an item the format cannot carry is
// counted in $skipped by its reason.
function pg_product_feed_rows($format, $items, $seller, &$skipped)
{
    $openai = (strpos($format, 'openai_') === 0);
    $rows = array();

    foreach ((array) $items as $item) {
        $reason = '';
        $row = $openai ? pg_product_feed_openai_row($item, $seller, $reason) : pg_product_feed_google_row($item, $reason);

        if ($row === null) {
            $skipped[$reason] = (isset($skipped[$reason]) ? $skipped[$reason] : 0) + 1;
            continue;
        }

        $rows[] = $row;
    }

    return $rows;
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
// code, an account or a cart (get_discounted_product_prices(false)), as on
// the catalog page, whoever's request builds the feed.
//
// $options: 'excluded_group_ids', 'default_brand'.
// $each: called with each chunk's items (a list) instead of collecting them
// in 'items', so a large catalog is never in memory whole
// (pg_product_feed_refresh()); null collects them.
// Returns array('error' => '' | 'ecommerce_off' | 'no_catalog' | a
// pg_product_feed_site() error, 'items' => list, 'skipped' => reason =>
// count).
function pg_product_feed_products($options = array(), $each = null)
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

        $items = array();

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

            $items[] = pg_product_feed_item($product, array(
                'category_path' => $placement['category_path'],
                'group_id'      => $placement['group_id'],
                'gallery'       => isset($gallery[$id]) ? $gallery[$id] : array(),
                'choices'       => isset($choices[$id]) ? $choices[$id] : array(),
                'sale_price'    => isset($sale_prices[$id]) ? $sale_prices[$id] : null,
            ), $site);
        }

        if ($each !== null) {
            call_user_func($each, $items);
        } else {
            foreach ($items as $item) {
                $result['items'][] = $item;
            }
        }

        unset($items, $groups, $gallery, $choices);
    } while (count($products) === PG_PRODUCT_FEED_CHUNK);

    return $result;
}


// ---------------------------------------------------------------------------
// Setup (config.product_feeds, 2026.11.0, 11.0.4)
// ---------------------------------------------------------------------------

// What config.product_feeds keeps for one format: whether it is on, its
// address token (a SHA-256 hash to find it, the token sealed with the site's
// key to show it again) and how its last build went. built_at, count and
// bytes describe the file being served, so a failed build leaves them as they
// were and only sets error.
function pg_product_feed_format_defaults()
{
    return array(
        'enabled'      => 0,
        'token_hash'   => '',
        'token_secret' => '',
        'built_at'     => 0,
        'count'        => 0,
        'bytes'        => 0,
        'error'        => '',
        'skipped'      => array(),
    );
}

// The setup as stored, every key present and typed: the seller fields and
// excluded groups pg_product_feed_products() and pg_product_feed_render()
// take as options, and one pg_product_feed_format_defaults() entry per
// format. Anything else in the stored JSON is dropped.
function pg_product_feed_settings_parse($json)
{
    $settings = array(
        'seller_name'        => '',
        'seller_url'         => '',
        'privacy_policy_url' => '',
        'terms_url'          => '',
        'default_brand'      => '',
        'excluded_group_ids' => array(),
        'formats'            => array(),
    );

    foreach (array_keys(pg_product_feed_formats()) as $format) {
        $settings['formats'][$format] = pg_product_feed_format_defaults();
    }

    $stored = json_decode((string) $json, true);

    if (!is_array($stored)) {
        return $settings;
    }

    foreach (array('seller_name', 'seller_url', 'privacy_policy_url', 'terms_url', 'default_brand') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $settings[$key] = trim((string) $stored[$key]);
        }
    }

    if (isset($stored['excluded_group_ids']) && is_array($stored['excluded_group_ids'])) {
        $ids = array();

        foreach ($stored['excluded_group_ids'] as $id) {
            if (is_scalar($id) && ((int) $id > 0)) {
                $ids[(int) $id] = true;
            }
        }

        $settings['excluded_group_ids'] = array_keys($ids);
    }

    if (!isset($stored['formats']) || !is_array($stored['formats'])) {
        return $settings;
    }

    foreach ($settings['formats'] as $format => $defaults) {
        if (!isset($stored['formats'][$format]) || !is_array($stored['formats'][$format])) {
            continue;
        }

        $entry = $stored['formats'][$format];

        foreach (array('enabled', 'built_at', 'count', 'bytes') as $key) {
            if (isset($entry[$key]) && is_scalar($entry[$key])) {
                $settings['formats'][$format][$key] = max(0, (int) $entry[$key]);
            }
        }

        $settings['formats'][$format]['enabled'] = ($settings['formats'][$format]['enabled'] > 0) ? 1 : 0;

        if (isset($entry['token_hash']) && is_string($entry['token_hash']) && preg_match('/^[0-9a-f]{64}$/', $entry['token_hash'])) {
            $settings['formats'][$format]['token_hash'] = $entry['token_hash'];
        }

        if (isset($entry['token_secret']) && is_string($entry['token_secret'])) {
            $settings['formats'][$format]['token_secret'] = $entry['token_secret'];
        }

        if (isset($entry['error']) && is_string($entry['error']) && preg_match('/^[a-z_]{1,40}$/', $entry['error'])) {
            $settings['formats'][$format]['error'] = $entry['error'];
        }

        if (isset($entry['skipped']) && is_array($entry['skipped'])) {
            foreach ($entry['skipped'] as $reason => $count) {
                if (is_string($reason) && preg_match('/^[a-z_]{1,40}$/', $reason) && is_scalar($count) && ((int) $count > 0)) {
                    $settings['formats'][$format]['skipped'][$reason] = (int) $count;
                }
            }
        }
    }

    return $settings;
}

// The options pg_product_feed_products() and pg_product_feed_render() take,
// from the setup.
function pg_product_feed_settings_options($settings)
{
    return array(
        'seller_name'        => $settings['seller_name'],
        'seller_url'         => $settings['seller_url'],
        'privacy_policy_url' => $settings['privacy_policy_url'],
        'terms_url'          => $settings['terms_url'],
        'default_brand'      => $settings['default_brand'],
        'excluded_group_ids' => $settings['excluded_group_ids'],
    );
}

// The setup after a build: each format's status replaced by the one the build
// left ($statuses: format => pg_product_feed_status() entry), everything else
// - the seller fields, the switches, the tokens - as $settings has it. The
// job reads the setup again before it writes, so a change saved on the
// settings screen while the feed was being built is kept.
function pg_product_feed_settings_merge_statuses($settings, $statuses)
{
    foreach ((array) $statuses as $format => $status) {
        if (!isset($settings['formats'][$format])) {
            continue;
        }

        foreach (array('built_at', 'count', 'bytes', 'error', 'skipped') as $key) {
            $settings['formats'][$format][$key] = $status[$key];
        }
    }

    return $settings;
}

// A format's entry after one build at $now. A good build describes the new
// file; a failed one keeps the description of the file still being served
// and says why it was not replaced.
function pg_product_feed_status($entry, $result, $now)
{
    $entry['error'] = $result['ok'] ? '' : (string) $result['error'];
    $entry['skipped'] = array();

    foreach ((array) $result['skipped'] as $reason => $count) {
        if ((int) $count > 0) {
            $entry['skipped'][(string) $reason] = (int) $count;
        }
    }

    if ($result['ok']) {
        $entry['built_at'] = (int) $now;
        $entry['count'] = (int) $result['count'];
        $entry['bytes'] = isset($result['bytes']) ? (int) $result['bytes'] : strlen((string) $result['body']);
    }

    return $entry;
}

// Whether the 11.0.4 column is there.
function pg_product_feed_schema_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('config', 'product_feeds');
}

function pg_product_feed_settings()
{
    if (!pg_product_feed_schema_ready()) {
        return pg_product_feed_settings_parse('');
    }

    return pg_product_feed_settings_parse(db_value("SELECT product_feeds FROM config"));
}

function pg_product_feed_settings_save($settings)
{
    if (!pg_product_feed_schema_ready()) {
        return false;
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    return (bool) db("UPDATE config SET product_feeds = '" . e(json_encode(pg_product_feed_settings_parse(json_encode($settings, $flags)), $flags)) . "'");
}

// ---------------------------------------------------------------------------
// Addresses
// ---------------------------------------------------------------------------

// The shape of a feed token (43 URL-safe characters, pg_short_link_new_token()),
// checked before anything is hashed or read.
function pg_product_feed_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

// The format a token opens, or '' when it opens none. Compared against every
// format's hash in constant time; an empty hash (no address yet) matches
// nothing.
function pg_product_feed_format_for_token($token, $settings)
{
    if (!pg_product_feed_token_shape($token)) {
        return '';
    }

    $hash = hash('sha256', $token);
    $found = '';

    foreach ($settings['formats'] as $format => $entry) {
        if (($entry['token_hash'] !== '') && hash_equals($entry['token_hash'], $hash)) {
            $found = $format;
        }
    }

    return $found;
}

// A new address for a format: the old token stops working at once. Changes
// $settings; the caller saves them. Returns the token.
function pg_product_feed_issue_token(&$settings, $format)
{
    $token = pg_short_link_new_token();

    $settings['formats'][$format]['token_hash'] = hash('sha256', $token);
    $settings['formats'][$format]['token_secret'] = pg_product_feed_seal($token);

    return $token;
}

// The token as kept for showing again: encrypted with the site's key,
// "<ciphertext>:<iv>". Empty where the site has no key; the address is then
// shown once, when it is made.
function pg_product_feed_seal($token)
{
    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_encrypt')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv((string) $token);

    return $cipher . ':' . $iv;
}

// A format's token read back; empty when it cannot be, or when it no longer
// matches its hash (the site's key was changed).
function pg_product_feed_token($entry)
{
    $sealed = (string) $entry['token_secret'];

    if (($sealed === '') || (strpos($sealed, ':') === false) || !defined('ENCRYPTION_KEY') || !function_exists('openssl_decrypt')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);
    $token = decode_ssl_keys($cipher, $iv);

    if (!pg_product_feed_token_shape($token) || !hash_equals((string) $entry['token_hash'], hash('sha256', $token))) {
        return '';
    }

    return $token;
}

// The address a platform reads a feed from.
function pg_product_feed_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/feed.php?f=' . rawurlencode((string) $token);
}

// Where a format's file is kept: data/feeds/<format>.<extension>. Named by
// format, not by token, so the token is never written to the disk in the
// clear. data/ is closed to HTTP by the server rules; feed.php reads it.
function pg_product_feed_path($format)
{
    $formats = pg_product_feed_formats();

    if (!isset($formats[$format])) {
        return '';
    }

    return PG_FUNCTIONS_DIR . '/data/feeds/' . $format . '.' . $formats[$format]['extension'];
}


// ---------------------------------------------------------------------------
// Writing a feed file chunk by chunk
// ---------------------------------------------------------------------------

// The daily build never holds a whole catalog: pg_product_feed_refresh()
// hands each chunk of items to the writer of every format that is on, and
// the writer appends the chunk's rows to a work file beside the feed. A
// JSON Lines work file is the feed itself. CSV and TSV name only the columns
// some row fills, which is known after the last row, so their work file
// keeps every column of a row (pg_product_feed_spool_line()) and the feed is
// written from it line by line once the catalog has been read, with the
// header in front. The finished file replaces the old one in one rename, so
// feed.php never serves half a file. The bytes are those of
// pg_product_feed_render() for the same items.

// A row's cell texts as one line of a work file: the cells joined with tabs,
// a backslash, tab or line break inside a cell written as \\, \t, \n, \r.
// Every byte comes back as it was (pg_product_feed_spool_cells()).
function pg_product_feed_spool_line($cells)
{
    $escaped = array();

    foreach ($cells as $text) {
        $escaped[] = strtr((string) $text, array('\\' => '\\\\', "\t" => '\t', "\n" => '\n', "\r" => '\r'));
    }

    return implode("\t", $escaped);
}

// The cell texts of a work file line (without its line break).
function pg_product_feed_spool_cells($line)
{
    $cells = array();

    foreach (explode("\t", (string) $line) as $text) {
        $cells[] = strtr($text, array('\\\\' => '\\', '\t' => "\t", '\n' => "\n", '\r' => "\r"));
    }

    return $cells;
}

// Starts a format's file at $path. $options as pg_product_feed_render().
// The writer is an array handed back to pg_product_feed_writer_add() and
// pg_product_feed_writer_close(); 'error' is set when it cannot start
// (unknown_format, no_seller_name, write_failed).
function pg_product_feed_writer_open($format, $path, $options = array())
{
    $writer = array(
        'format'  => $format,
        'path'    => (string) $path,
        'error'   => '',
        'seller'  => pg_product_feed_seller($options),
        'count'   => 0,
        'skipped' => array(),
        'used'    => array(),
        'work'    => '',
        'handle'  => null,
    );

    $formats = pg_product_feed_formats();

    if (!isset($formats[$format])) {
        $writer['error'] = 'unknown_format';
        return $writer;
    }

    if ((strpos($format, 'openai_') === 0) && ($writer['seller']['name'] === '')) {
        $writer['error'] = 'no_seller_name';
        return $writer;
    }

    $directory = dirname($writer['path']);

    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        $writer['error'] = 'write_failed';
        return $writer;
    }

    $writer['work'] = $writer['path'] . '.tmp-' . bin2hex(random_bytes(6));
    $writer['handle'] = @fopen($writer['work'], 'wb');

    if (!$writer['handle']) {
        $writer['error'] = 'write_failed';
        $writer['handle'] = null;
    }

    return $writer;
}

// Appends the rows of a chunk of items (pg_product_feed_item()) to the work
// file.
function pg_product_feed_writer_add(&$writer, $items)
{
    if (($writer['error'] !== '') || !$writer['handle']) {
        return;
    }

    $format = $writer['format'];
    $columns = ($format === 'openai_jsonl') ? array() : pg_product_feed_columns($format);
    $text = '';

    foreach (pg_product_feed_rows($format, $items, $writer['seller'], $writer['skipped']) as $row) {
        if ($format === 'openai_jsonl') {
            $line = pg_product_feed_jsonl_line($row);

            if ($line === false) {
                continue;
            }
        } else {
            $cells = array();

            foreach ($row as $key => $value) {
                $writer['used'][$key] = true;
            }

            foreach ($columns as $column) {
                $cells[] = isset($row[$column]) ? pg_product_feed_cell($row[$column]) : '';
            }

            $line = pg_product_feed_spool_line($cells);
        }

        $text .= $line . "\n";
        $writer['count']++;
    }

    if (($text !== '') && (@fwrite($writer['handle'], $text) !== strlen($text))) {
        $writer['error'] = 'write_failed';
    }
}

// Finishes the file and puts it in place, or drops the work when the build
// failed: $error is the catalog's own error (pg_product_feed_products()),
// which outweighs the writer's. Returns array('ok', 'error', 'count',
// 'bytes', 'skipped'); 'skipped' counts the items this format could not
// carry.
function pg_product_feed_writer_close(&$writer, $error = '')
{
    $result = array('ok' => false, 'error' => '', 'count' => 0, 'bytes' => 0, 'skipped' => $writer['skipped']);

    if ($writer['handle']) {
        if (!@fclose($writer['handle']) && ($writer['error'] === '')) {
            $writer['error'] = 'write_failed';
        }

        $writer['handle'] = null;
    }

    $result['error'] = ($error !== '') ? (string) $error : $writer['error'];

    $file = ($result['error'] === '') ? pg_product_feed_writer_finish($writer) : '';

    if (($writer['work'] !== '') && ($file !== $writer['work'])) {
        @unlink($writer['work']);
    }

    if ($result['error'] !== '') {
        return $result;
    }

    if ($file === '') {
        $result['error'] = 'write_failed';
        return $result;
    }

    $bytes = @filesize($file);

    // Windows will not rename over an existing file.
    if (!@rename($file, $writer['path'])) {
        @unlink($writer['path']);

        if (!@rename($file, $writer['path'])) {
            @unlink($file);
            $result['error'] = 'write_failed';
            return $result;
        }
    }

    $result['ok'] = true;
    $result['count'] = $writer['count'];
    $result['bytes'] = (int) $bytes;

    return $result;
}

// The finished file of a writer, still beside the feed under a temporary
// name: the work file itself for JSON Lines, a new file with the header and
// the used columns for CSV and TSV. '' when it could not be written.
function pg_product_feed_writer_finish($writer)
{
    $format = $writer['format'];

    if ($format === 'openai_jsonl') {
        return $writer['work'];
    }

    $all = pg_product_feed_columns($format);
    $picked = array();

    foreach ($all as $index => $column) {
        if (isset($writer['used'][$column])) {
            $picked[$index] = $column;
        }
    }

    // An empty feed still names its columns, as pg_product_feed_render()
    // does.
    if (!$picked) {
        $picked = array_intersect($all, pg_product_feed_required_columns($format));
    }

    $line = ($format === 'openai_csv') ? 'pg_product_feed_csv_line' : 'pg_product_feed_tsv_line';

    $file = $writer['path'] . '.tmp-' . bin2hex(random_bytes(6));
    $out = @fopen($file, 'wb');
    $in = @fopen($writer['work'], 'rb');

    if (!$out || !$in) {
        if ($out) {
            @fclose($out);
        }

        if ($in) {
            @fclose($in);
        }

        @unlink($file);

        return '';
    }

    $ok = (@fwrite($out, $line(array_values($picked)) . "\n") !== false);

    while ($ok && (($text = fgets($in)) !== false)) {
        $cells = pg_product_feed_spool_cells(rtrim($text, "\n"));
        $kept = array();

        foreach ($picked as $index => $column) {
            $kept[] = isset($cells[$index]) ? $cells[$index] : '';
        }

        $text = $line($kept) . "\n";
        $ok = (@fwrite($out, $text) === strlen($text));
    }

    @fclose($in);

    if (!@fclose($out) || !$ok) {
        @unlink($file);
        return '';
    }

    return $file;
}

// ---------------------------------------------------------------------------
// Daily build (product_feed_job.php)
// ---------------------------------------------------------------------------

// Builds every format that is on and writes its file; removes the file of a
// format that is off. The catalog is read once for all formats, a chunk at a
// time, and each chunk goes to every format's writer before the next is read
// (pg_product_feed_writer_open()), so the memory a build takes does not grow
// with the catalog. $only limits the run to some formats (the settings
// screen's "Build now").
//
// Returns array('error' => '' | 'not_ready', 'formats' => format =>
// array('ok', 'error', 'count', 'skipped')).
function pg_product_feed_refresh($only = null)
{
    $report = array('error' => '', 'formats' => array());

    if (!pg_product_feed_schema_ready()) {
        $report['error'] = 'not_ready';
        return $report;
    }

    $settings = pg_product_feed_settings();
    $options = pg_product_feed_settings_options($settings);
    $writers = array();
    $statuses = array();

    foreach ($settings['formats'] as $format => $entry) {
        if (($only !== null) && !in_array($format, (array) $only, true)) {
            continue;
        }

        $path = pg_product_feed_path($format);

        if (!$entry['enabled']) {
            if (is_file($path)) {
                @unlink($path);
            }

            continue;
        }

        $writers[$format] = pg_product_feed_writer_open($format, $path, $options);
    }

    $products = array('error' => '', 'items' => array(), 'skipped' => array());

    if ($writers) {
        $products = pg_product_feed_products($options, function ($items) use (&$writers) {
            foreach ($writers as $format => $writer) {
                pg_product_feed_writer_add($writers[$format], $items);
            }
        });
    }

    foreach ($writers as $format => $writer) {
        $entry = $settings['formats'][$format];
        $result = pg_product_feed_writer_close($writers[$format], $products['error']);

        // The reader's skip counts belong to every format.
        foreach ($products['skipped'] as $reason => $count) {
            $result['skipped'][$reason] = (isset($result['skipped'][$reason]) ? $result['skipped'][$reason] : 0) + $count;
        }

        $statuses[$format] = pg_product_feed_status($entry, $result, time());

        $report['formats'][$format] = array(
            'ok'      => $result['ok'],
            'error'   => $result['ok'] ? '' : $result['error'],
            'count'   => $result['count'],
            'skipped' => $result['skipped'],
        );
    }

    if ($statuses) {
        pg_product_feed_settings_save(pg_product_feed_settings_merge_statuses(pg_product_feed_settings(), $statuses));
    }

    return $report;
}

// Adds the daily build to the jobs the general job runs, when the general job
// is the one running them; a site that schedules each job on its own keeps
// its own schedule. True when the job is (now) in the list.
function pg_product_feed_job_switch_on()
{
    if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return false;
    }

    $list = pg_cron_dispatch_list();

    if (in_array('product_feed_job', $list, true)) {
        return true;
    }

    $list[] = 'product_feed_job';

    db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");

    return true;
}

// ---------------------------------------------------------------------------
// Serving (feed.php)
// ---------------------------------------------------------------------------

// Whether a reader's If-Modified-Since already covers a file changed at
// $modified: HTTP dates have whole seconds, so equal is current.
function pg_product_feed_not_modified($modified, $if_modified_since)
{
    $since = ((string) $if_modified_since !== '') ? strtotime((string) $if_modified_since) : false;

    return ($since !== false) && ((int) $modified > 0) && ($since >= (int) $modified);
}

// Answers feed.php: the file of the format the token opens, with
// Last-Modified, and 304 to a reader that has it. One 404 for every address
// that opens nothing - an unknown or renewed token, a format switched off, a
// feed not built yet, a shop without e-commerce - so the answer says nothing
// about which.
function pg_product_feed_serve($token)
{
    $method = strtoupper((string) (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'));

    if (($method !== 'GET') && ($method !== 'HEAD')) {
        pg_product_feed_refuse(405);
    }

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_product_feed_schema_ready()) {
        pg_product_feed_refuse(404);
    }

    $settings = pg_product_feed_settings();
    $format = pg_product_feed_format_for_token((string) $token, $settings);

    if (($format === '') || !$settings['formats'][$format]['enabled']) {
        pg_product_feed_refuse(404);
    }

    $path = pg_product_feed_path($format);
    $modified = is_file($path) ? (int) @filemtime($path) : 0;

    if ($modified <= 0) {
        pg_product_feed_refuse(404);
    }

    $formats = pg_product_feed_formats();

    pg_product_feed_discard_output();

    header('Content-Type: ' . $formats[$format]['content_type']);
    header('Content-Disposition: inline; filename="products.' . $formats[$format]['extension'] . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    // The address carries its token: no shared cache may keep it.
    header('Cache-Control: private, max-age=3600');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');

    if (pg_product_feed_not_modified($modified, isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : '')) {
        http_response_code(304);
        exit;
    }

    $size = (int) @filesize($path);

    header('Content-Length: ' . $size);

    if ($method !== 'HEAD') {
        readfile($path);
    }

    exit;
}

// Ends the request with a status and nothing else.
function pg_product_feed_refuse($status)
{
    pg_product_feed_discard_output();

    http_response_code((int) $status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    if ((int) $status === 405) {
        header('Allow: GET, HEAD');
    }

    exit;
}

// Drops whatever the shared code printed on the way here; it would sit in
// front of the feed and break it.
function pg_product_feed_discard_output()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// ---------------------------------------------------------------------------
// Settings screen (Settings › SEO › Product Feeds)
// ---------------------------------------------------------------------------

// An address the feeds may carry as the seller's site, privacy policy or
// terms: empty, or an absolute http(s) address with a host. The OpenAI
// specification takes absolute addresses only.
function pg_product_feed_valid_url($url)
{
    $url = trim((string) $url);

    if ($url === '') {
        return true;
    }

    if ((strlen($url) > 2000) || (filter_var($url, FILTER_VALIDATE_URL) === false)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = (string) parse_url($url, PHP_URL_HOST);

    return in_array($scheme, array('http', 'https'), true) && ($host !== '');
}

// The groups the card offers to leave out: the ones the shop's catalogs
// show, gid => trail ("Shop > Office > Chairs"), in walk order. Empty
// without e-commerce or before the 11.0.4 step.
function pg_product_feed_screen_groups()
{
    if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_product_feed_schema_ready()) {
        return array();
    }

    $groups = array();

    $index = pg_product_feed_group_index(
        db_items("SELECT id, parent_id, name, display_type FROM product_groups WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"),
        pg_product_feed_catalog_roots()
    );

    foreach ($index as $group_id => $group) {
        $groups[(int) $group_id] = $group['path'];
    }

    return $groups;
}

// The excluded group ids a save posted, kept only where they name one of the
// groups the screen offered.
function pg_product_feed_posted_groups($posted, $offered_ids)
{
    $offered = array_flip(array_map('intval', (array) $offered_ids));
    $ids = array();

    foreach ((array) $posted as $id) {
        if (is_scalar($id) && ctype_digit((string) $id) && isset($offered[(int) $id])) {
            $ids[(int) $id] = true;
        }
    }

    return array_keys($ids);
}

// What each format is called on the screen, and what it is for.
function pg_product_feed_format_texts()
{
    return array(
        'openai_jsonl' => array(
            'label' => lang('OpenAI product feed (JSON Lines)'),
            'help'  => lang('For shopping answers in ChatGPT. Give this address where OpenAI asks for your product feed.'),
        ),
        'openai_csv' => array(
            'label' => lang('OpenAI product feed (CSV)'),
            'help'  => lang('The same feed as a CSV file, for a platform that asks for CSV.'),
        ),
        'google_tsv' => array(
            'label' => lang('Google Merchant Center (TSV)'),
            'help'  => lang('Add this address in Merchant Center as a scheduled fetch; Google Shopping and its AI answers read your products from there.'),
        ),
    );
}

// Why a build failed, as the screen says it.
function pg_product_feed_error_text($code)
{
    $texts = array(
        'ecommerce_off'          => lang('E-commerce is turned off.'),
        'no_catalog'             => lang('No catalog page or Catalog widget on a public page shows a product group.'),
        'no_detail_page'         => lang('The site has no product detail page, so the products have no address.'),
        'detail_page_not_public' => lang('The product detail page is in a folder visitors cannot open.'),
        'no_seller_name'         => lang('The OpenAI feed needs the seller name on every product; enter it below.'),
        'write_failed'           => lang('The file could not be written to the data/feeds folder; check that the folder can be written to.'),
        'not_ready'              => lang('The database upgrade that adds product feeds has not run yet.'),
    );

    return isset($texts[$code]) ? $texts[$code] : lang('The feed could not be built.');
}

// One reason products were left out, with how many, as the screen says it.
function pg_product_feed_skip_text($reason, $count)
{
    $vars = array((int) $count);

    switch ($reason) {
        case 'no_address':
            return lang(array('string' => '{var:1} without an address', 'vars' => $vars));
        case 'donation':
            return lang(array('string' => '{var:1} donation(s)', 'vars' => $vars));
        case 'recurring':
            return lang(array('string' => '{var:1} with recurring payments', 'vars' => $vars));
        case 'no_price':
            return lang(array('string' => '{var:1} without a price', 'vars' => $vars));
        case 'not_in_catalog':
            return lang(array('string' => '{var:1} not in a catalog', 'vars' => $vars));
        case 'excluded_group':
            return lang(array('string' => '{var:1} in an excluded group', 'vars' => $vars));
        case 'no_title':
            return lang(array('string' => '{var:1} without a name', 'vars' => $vars));
        case 'no_brand':
            return lang(array('string' => '{var:1} without a brand', 'vars' => $vars));
        case 'no_image':
            return lang(array('string' => '{var:1} without a suitable image', 'vars' => $vars));
    }

    return lang(array('string' => '{var:1} for another reason', 'vars' => $vars));
}

// The last build of a format, as one line for the screen; '' before the first.
function pg_product_feed_status_text($entry)
{
    $parts = array();

    if ((int) $entry['built_at'] > 0) {
        $parts[] = lang(array('string' => 'Built {var:1}: {var:2} product(s).', 'vars' => array(
            get_relative_time(array('timestamp' => (int) $entry['built_at'], 'format' => 'plain_text')),
            (int) $entry['count'],
        )));
    }

    if ($entry['skipped']) {
        $reasons = array();

        foreach ($entry['skipped'] as $reason => $count) {
            $reasons[] = pg_product_feed_skip_text($reason, $count);
        }

        $parts[] = lang(array('string' => 'Left out: {var:1}.', 'vars' => array(implode(', ', $reasons))));
    }

    return implode(' ', $parts);
}

// The seller fields of a Product Feeds card save, as they are stored. The
// name and the brand are cut at the 255 characters their boxes allow. An
// address is never cut: a shortened address points somewhere else (a privacy
// policy URL that lost its query string), and the setup is kept as JSON in a
// TEXT column, so the 2000 characters pg_product_feed_valid_url() accepts
// fit. An address that check refuses is stored empty; seo.save.php refuses
// such a save with a message before this point.
function pg_product_feed_seller_from_input($input)
{
    $seller = array();

    foreach (array('seller_name', 'default_brand') as $key) {
        $seller[$key] = mb_substr(trim(isset($input[$key]) ? (string) $input[$key] : ''), 0, 255, 'UTF-8');
    }

    foreach (array('seller_url', 'privacy_policy_url', 'terms_url') as $key) {
        $url = trim(isset($input[$key]) ? (string) $input[$key] : '');
        $seller[$key] = pg_product_feed_valid_url($url) ? $url : '';
    }

    return $seller;
}

// Reads a save of the Product Feeds card into the stored setup: the seller
// fields, the excluded groups, the switches, a new address asked for, and a
// build when one was asked for or a feed was just switched on. Called by
// includes/settings/seo.save.php after its own columns are written; the
// fields were validated there first.
//
// $input: 'seller_name', 'seller_url', 'privacy_policy_url', 'terms_url',
// 'default_brand', 'excluded_group_ids', 'enabled' (format => bool),
// 'new_address' (a format or ''), 'build' (bool).
// Returns array('settings', 'issued' => format => token for addresses that
// cannot be shown again, 'built' => pg_product_feed_refresh() or null,
// 'renewed' => format or '').
function pg_product_feed_save_from_screen($input)
{
    $settings = pg_product_feed_settings();
    $issued = array();
    $switched_on = false;
    $renewed = '';

    $settings = array_merge($settings, pg_product_feed_seller_from_input($input));

    $settings['excluded_group_ids'] = array_values(array_map('intval', (array) $input['excluded_group_ids']));

    foreach ($settings['formats'] as $format => $entry) {
        $on = !empty($input['enabled'][$format]);

        if ($on && !$entry['enabled']) {
            $switched_on = true;
        }

        $settings['formats'][$format]['enabled'] = $on ? 1 : 0;

        $renew = ($input['new_address'] === $format);

        if (($on && ($entry['token_hash'] === '')) || $renew) {
            $token = pg_product_feed_issue_token($settings, $format);

            if ($settings['formats'][$format]['token_secret'] === '') {
                $issued[$format] = $token;
            }

            if ($renew) {
                $renewed = $format;
            }
        }
    }

    pg_product_feed_settings_save($settings);

    $any_on = false;

    foreach ($settings['formats'] as $entry) {
        $any_on = $any_on || $entry['enabled'];
    }

    if ($any_on) {
        pg_product_feed_job_switch_on();
    }

    $built = null;

    if ($any_on && (!empty($input['build']) || $switched_on)) {
        $built = pg_product_feed_refresh();
        $settings = pg_product_feed_settings();
    }

    return array('settings' => $settings, 'issued' => $issued, 'built' => $built, 'renewed' => $renewed);
}

// ---------------------------------------------------------------------------
// System Status
// ---------------------------------------------------------------------------

// How long a feed that is on may go without a good build before System
// Status calls it stale: two days, the job's own stale_after, so one missed
// daily run is not yet a warning.
if (!defined('PG_PRODUCT_FEED_STALE_AFTER')) {
    define('PG_PRODUCT_FEED_STALE_AFTER', 172800);
}

// The state of the feeds that are on, for the System Status tile.
//
// Per format that is on: 'fail' when its last build failed (platforms still
// read the file built before it), 'warn' when it was never built or not for
// longer than PG_PRODUCT_FEED_STALE_AFTER, 'ok' otherwise. The whole is the
// worst of them, or 'none' when no feed is on (no tile).
//
// Returns array('state', 'on', 'failing', 'stale', 'rows' => list of
// array('format', 'state', 'built_at', 'error')).
function pg_product_feed_health($settings, $now)
{
    $health = array('state' => 'none', 'on' => 0, 'failing' => 0, 'stale' => 0, 'rows' => array());

    foreach ($settings['formats'] as $format => $entry) {
        if (!$entry['enabled']) {
            continue;
        }

        $health['on']++;
        $built_at = (int) $entry['built_at'];

        if ($entry['error'] !== '') {
            $state = 'fail';
            $health['failing']++;
        } elseif (($built_at <= 0) || (((int) $now - $built_at) > PG_PRODUCT_FEED_STALE_AFTER)) {
            $state = 'warn';
            $health['stale']++;
        } else {
            $state = 'ok';
        }

        $health['rows'][] = array('format' => $format, 'state' => $state, 'built_at' => $built_at, 'error' => $entry['error']);
    }

    if ($health['failing'] > 0) {
        $health['state'] = 'fail';
    } elseif ($health['stale'] > 0) {
        $health['state'] = 'warn';
    } elseif ($health['on'] > 0) {
        $health['state'] = 'ok';
    }

    return $health;
}
