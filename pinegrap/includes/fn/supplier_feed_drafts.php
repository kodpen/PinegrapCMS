<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Drafts from a supplier feed: a record no product matches becomes a product
 * that is switched off (enabled 0) until a person publishes it, with its
 * pictures, description, brand and category; records sharing a model code
 * become one variant set.
 *
 * The apply phase of a pull (supplier_feed_sync.php) writes each record that
 * has a model code or matched no product to one of a few spool files, chosen
 * by the model code, so every record of one model lands in the same file
 * wherever it sits in the feed. The drafts phase then works one spool file
 * at a time:
 *   - a model with a product already in a variant set gets its new records
 *     added to that set;
 *   - two or more new records of one model become a new variant set
 *     (pg_catalog_variant_set_create());
 *   - any other new record becomes a single product (pg_pb_create_product()).
 * The item row then points at the draft, so a step worked again after a cut
 * makes nothing twice, and a draft a person deleted marks its item dismissed
 * (supplier_feed_sync.php): no draft is made from it again.
 *
 * Pictures are fetched through pg_curl_run() with the address check (no
 * address inside the network, the address pinned), the cap of one upload,
 * and stored by pg_file_store_upload().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Spool files a pull's records are spread over by model code.
define('PG_SUPPLIER_FEED_SPOOL_BUCKETS', 16);

// Pictures one record gives its draft.
define('PG_SUPPLIER_FEED_DRAFT_PICTURES', 10);

/**
 * The picture addresses of a record: the mapped key and its repeats
 * (key[2], key[3]…), a value holding several addresses split before each
 * one. Only http(s) and protocol-relative addresses, each once. Pure.
 *
 * @param array  $record a flat record
 * @param string $key    the feed's field mapped to pictures, '' for none
 * @param int    $max
 * @return string[]
 */
function pg_supplier_feed_record_images($record, $key, $max = PG_SUPPLIER_FEED_DRAFT_PICTURES)
{
    $urls = array();
    $key = (string) $key;

    if ($key === '') {
        return $urls;
    }

    $values = array();

    foreach ((array) $record as $name => $value) {
        $name = (string) $name;

        if (($name === $key) || preg_match('/^' . preg_quote($key, '/') . '\[\d+\]$/', $name)) {
            $values[] = (string) $value;
        }
    }

    foreach ($values as $value) {
        foreach (preg_split('/[\s,;|]+(?=(?:https?:)?\/\/)/i', trim($value)) as $url) {
            $url = trim($url, " \t\r\n,;|");

            if (preg_match('#^(?:https?:)?//[^\s/]+#i', $url) && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }

            if (count($urls) >= $max) {
                return $urls;
            }
        }
    }

    return $urls;
}

/**
 * The names a category is looked up by: the whole text, then its last step
 * when it is a path ("Ev > Mutfak > Tencere", "Ev/Mutfak"). Pure.
 *
 * @param string $category
 * @return string[]
 */
function pg_supplier_feed_category_names($category)
{
    $category = trim((string) $category);

    if ($category === '') {
        return array();
    }

    $names = array($category);
    $steps = preg_split('/\s*(?:>|»|\/|\\\\|\|)\s*/u', $category);
    $last = trim((string) end($steps));

    if (($last !== '') && ($last !== $category)) {
        $names[] = $last;
    }

    return $names;
}

/**
 * A variant set's name from its records' names: the words they all start
 * with, without a trailing separator; the first name when they share none.
 * Pure.
 *
 * @param string[] $names
 * @return string
 */
function pg_supplier_feed_set_name($names)
{
    $names = array_values(array_filter(array_map('trim', (array) $names), 'strlen'));

    if ($names === array()) {
        return '';
    }

    $common = preg_split('/\s+/u', $names[0]);

    foreach ($names as $name) {
        $words = preg_split('/\s+/u', $name);
        $same = 0;

        while (($same < count($common)) && isset($words[$same]) && (pg_supplier_feed_lower($words[$same]) === pg_supplier_feed_lower($common[$same]))) {
            $same++;
        }

        $common = array_slice($common, 0, $same);
    }

    $set = trim(preg_replace('/[\s\-–,\/:;|(]+$/u', '', implode(' ', $common)));

    return ($set !== '') ? $set : $names[0];
}

/**
 * The spool file a record goes to: by its model code, by its key without one.
 * Pure.
 *
 * @param string $model
 * @param string $key
 * @return int
 */
function pg_supplier_feed_spool_bucket($model, $key)
{
    $by = ((string) $model !== '') ? 'm:' . pg_supplier_feed_lower($model) : 'k:' . pg_supplier_feed_lower($key);

    return (int) (sprintf('%u', crc32($by)) % PG_SUPPLIER_FEED_SPOOL_BUCKETS);
}

/**
 * One spool file's records as drafts to make, by model. A record read twice
 * (a step worked again) counts once. A record is new while its item points
 * at no product and is not dismissed; a model's records with products give
 * the products a new record may join. Pure.
 *
 * @param array $rows  spool lines: m (model), k (key), p (product at the apply), v (values of a new record)
 * @param array $items lower key => item row now (product_id, dismissed)
 * @return array list of model ('' for a record of its own), new (values), products (ids)
 */
function pg_supplier_feed_draft_groups($rows, $items)
{
    $models = array();
    $singles = array();

    foreach ((array) $rows as $row) {
        $lower = pg_supplier_feed_lower((string) $row['k']);
        $item = isset($items[$lower]) ? $items[$lower] : null;
        $product = ($item !== null) ? (int) $item['product_id'] : (int) $row['p'];
        $new = ($product === 0) && is_array($row['v']) && (($item === null) || empty($item['dismissed']));
        $model = (string) $row['m'];

        if ($model === '') {
            if ($new) {
                $singles[$lower] = array('model' => '', 'new' => array($lower => $row['v']), 'products' => array());
            }

            continue;
        }

        $model_lower = pg_supplier_feed_lower($model);

        if (!isset($models[$model_lower])) {
            $models[$model_lower] = array('model' => $model, 'new' => array(), 'products' => array());
        }

        if ($new) {
            $models[$model_lower]['new'][$lower] = $row['v'];
        } elseif ($product > 0) {
            $models[$model_lower]['products'][$product] = $product;
        }
    }

    $groups = array();

    foreach (array_merge(array_values($models), array_values($singles)) as $group) {
        if ($group['new'] !== array()) {
            $group['new'] = array_values($group['new']);
            $group['products'] = array_values($group['products']);
            $groups[] = $group;
        }
    }

    return $groups;
}

/**
 * A spool file of a feed's running pull.
 *
 * @param int $feed_id
 * @param int $bucket
 * @return string
 */
function pg_supplier_feed_spool_file($feed_id, $bucket)
{
    return PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_' . (int) $feed_id . '_drafts_' . (int) $bucket . '.dat';
}

/**
 * Adds one step's records to the spool files.
 *
 * @param int   $feed_id
 * @param array $rows spool lines (m, k, p, v)
 * @return void
 */
function pg_supplier_feed_spool_add($feed_id, $rows)
{
    $by_bucket = array();

    foreach ((array) $rows as $row) {
        // A value the readers could not make UTF-8 is left out, not the line.
        $by_bucket[pg_supplier_feed_spool_bucket($row['m'], $row['k'])][] = json_encode($row, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    foreach ($by_bucket as $bucket => $lines) {
        @file_put_contents(pg_supplier_feed_spool_file($feed_id, $bucket), implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }
}

/**
 * Deletes a feed's spool files.
 *
 * @param int $feed_id
 * @return void
 */
function pg_supplier_feed_spool_clear($feed_id)
{
    for ($bucket = 0; $bucket < PG_SUPPLIER_FEED_SPOOL_BUCKETS; $bucket++) {
        $file = pg_supplier_feed_spool_file($feed_id, $bucket);

        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * The spool line of a record after the apply: a record with a model code
 * always (its product may be what a new record joins), one without only
 * while it matched no product. Null for no line. Pure.
 *
 * @param array $value  the record's values (key, model…)
 * @param array $item   its item (product_id, dismissed)
 * @return array|null
 */
function pg_supplier_feed_spool_line($value, $item)
{
    $product = (int) $item['product_id'];
    $model = isset($value['model']) ? (string) $value['model'] : '';
    $new = ($product === 0) && empty($item['dismissed']);

    if (!$new && (($model === '') || ($product === 0))) {
        return null;
    }

    return array('m' => $model, 'k' => (string) $value['key'], 'p' => $product, 'v' => $new ? $value : null);
}

/**
 * Works the spool files of a pull until $deadline: one file at a time, every
 * model of it made. A file cut by the deadline is worked again next time; its
 * drafts made already are left alone.
 *
 * @param array $feed
 * @param array $state    bucket (the file worked) changes in place
 * @param array $context
 * @param array $mapping
 * @param float $deadline microtime
 * @param array $lines    failed lines, added to
 * @return bool whether files are left
 */
function pg_supplier_feed_drafts_chunk($feed, &$state, $context, $mapping, $deadline, &$lines)
{
    $bucket = isset($state['bucket']) ? (int) $state['bucket'] : 0;

    if ($bucket >= PG_SUPPLIER_FEED_SPOOL_BUCKETS) {
        return false;
    }

    $file = pg_supplier_feed_spool_file($feed['id'], $bucket);
    $rows = array();

    foreach ((is_file($file) ? (array) @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array()) as $line) {
        $row = json_decode($line, true);

        if (is_array($row) && isset($row['k'])) {
            $rows[] = $row + array('m' => '', 'p' => 0, 'v' => null);
        }
    }

    $keys = array();

    foreach ($rows as $row) {
        $keys[] = (string) $row['k'];
    }

    $groups = pg_supplier_feed_draft_groups($rows, pg_supplier_feed_items_of($feed['id'], array_values(array_unique($keys))));

    if (($groups !== array()) && !pg_supplier_feed_draft_writers()) {
        $groups = array();
    }

    foreach ($groups as $group) {
        if (microtime(true) >= $deadline) {
            return true;
        }

        pg_supplier_feed_draft_make($feed, $state, $context, $mapping, $group, $lines);
    }

    if (is_file($file)) {
        @unlink($file);
    }

    $state['bucket'] = $bucket + 1;

    return $state['bucket'] < PG_SUPPLIER_FEED_SPOOL_BUCKETS;
}

/**
 * Loads the product screens' writer and the import's row builders and
 * picture checks.
 *
 * @return bool
 */
function pg_supplier_feed_draft_writers()
{
    if (!function_exists('pg_pb_create_product')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    foreach (array('write_product', 'media') as $name) {
        $file = PG_FUNCTIONS_DIR . '/includes/import/' . $name . '.php';

        if (is_file($file)) {
            require_once($file);
        }
    }

    return function_exists('pg_import_product_row') && function_exists('pg_import_media_refusal') && function_exists('pg_catalog_variant_set_create');
}

/**
 * The drafts of one model (or one record without a model): added to the
 * variant set its products sit in, a new variant set, or single products.
 *
 * @param array $feed
 * @param array $state
 * @param array $context
 * @param array $mapping
 * @param array $group   pg_supplier_feed_draft_groups()
 * @param array $lines
 * @return void
 */
function pg_supplier_feed_draft_make($feed, &$state, $context, $mapping, $group, &$lines)
{
    $user_id = (int) $feed['created_by'];
    $set_id = 0;

    if ($group['products'] !== array()) {
        $set_id = (int) db_value("SELECT product_groups.id FROM products_groups_xref
            JOIN product_groups ON product_groups.id = products_groups_xref.product_group
            WHERE products_groups_xref.product IN ('" . implode("', '", array_map('intval', $group['products'])) . "')
                AND product_groups.display_type = 'select'" . pg_catalog_bin_live_sql('product_groups') . "
            ORDER BY product_groups.id LIMIT 1");
    }

    $drafts = array();
    $fetched = array();

    foreach ($group['new'] as $value) {
        $notes = array();
        $draft = array(
            'value'  => $value,
            'buy'    => pg_supplier_feed_draft_buy($value, $context),
            'images' => pg_supplier_feed_draft_pictures($feed, (array) $value['images'], $notes, $fetched),
        );
        $draft['price'] = pg_supplier_feed_draft_price($draft['buy'], $value, $context);

        foreach ($notes as $note) {
            $lines[] = array('line' => $value['key'], 'reason' => $note);
        }

        $drafts[] = $draft;
    }

    if (($set_id === 0) && (count($drafts) >= 2)) {
        $made = pg_supplier_feed_draft_set($feed, $mapping, $group['model'], $drafts, $user_id);

        if ($made === null) {
            $state['count']['failed'] += count($drafts);
            $lines[] = array('line' => $group['model'], 'reason' => lang('The product group could not be created.'));

            return;
        }

        foreach ($drafts as $index => $draft) {
            pg_supplier_feed_draft_item($feed, $state, $draft, $made[$index]);
        }

        return;
    }

    foreach ($drafts as $draft) {
        $value = $draft['value'];
        $row = pg_import_product_row(array(
            'sku' => $value['key'], 'name' => ($value['name'] !== '') ? $value['name'] : $value['key'], 'enabled' => false,
            'price' => $draft['price'], 'stock' => max(0, (int) $value['stock']), 'weight' => '', 'keywords' => '',
        ), $user_id);
        $row['brand'] = pg_supplier_feed_cut($value['brand'], 100);
        $row['full_description'] = pg_supplier_feed_draft_html($value['description']);
        $group_id = ($set_id > 0) ? $set_id : pg_supplier_feed_draft_group_id($value['category'], $mapping);

        $id = (int) pg_pb_create_product($row, array(
            'images'       => $draft['images'],
            'group_ids'    => ($group_id > 0) ? array($group_id) : array(),
            'attributes'   => array(),
            'submit_form'  => array(),
            'price_source' => 'supplier',
        ));

        if ($id <= 0) {
            $state['count']['failed']++;
            $lines[] = array('line' => $value['key'], 'reason' => lang('The product could not be created.'));
            continue;
        }

        if ($value['barcode'] !== '') {
            pg_assign_product_barcode($id, $value['barcode']);
        }

        pg_supplier_feed_draft_item($feed, $state, $draft, $id);
    }
}

/**
 * A new variant set of one model's drafts, switched off, under the category
 * of its first record that finds a group, else the feed's group.
 *
 * @param array  $feed
 * @param array  $mapping
 * @param string $model
 * @param array  $drafts value, buy, price, images
 * @param int    $user_id
 * @return int[]|null the products, in the drafts' order; null when none was made
 */
function pg_supplier_feed_draft_set($feed, $mapping, $model, $drafts, $user_id)
{
    $names = array();
    $parent = 0;
    $images = array();
    $description = '';
    $brand = '';
    $variants = array();

    foreach ($drafts as $draft) {
        $value = $draft['value'];
        $names[] = ($value['name'] !== '') ? $value['name'] : $value['key'];
        $parent = ($parent > 0) ? $parent : pg_supplier_feed_draft_group_id($value['category'], array());
        $images = ($images !== array()) ? $images : $draft['images'];
        $description = ($description !== '') ? $description : (string) $value['description'];
        $brand = ($brand !== '') ? $brand : (string) $value['brand'];
        $variants[] = array(
            'sku' => $value['key'], 'name' => end($names), 'price' => $draft['price'],
            'stock' => max(0, (int) $value['stock']), 'images' => $draft['images'],
        );
    }

    if ($parent === 0) {
        $parent = pg_supplier_feed_draft_group_id('', $mapping);
    }

    $set = pg_import_product_variant_set(
        array('sku' => $model, 'name' => pg_supplier_feed_set_name($names), 'enabled' => false, 'keywords' => '', 'weight' => '', 'price' => 0, 'stock' => null),
        $variants, array_fill(0, count($variants), array()), $parent, $images, $user_id);
    $set['full_description'] = pg_sanitize_rich_text($description, 'staff');
    $set['common']['brand'] = pg_supplier_feed_cut($brand, 100);

    foreach ($drafts as $index => $draft) {
        $set['variants'][$index]['barcode'] = (string) $draft['value']['barcode'];
    }

    $made = pg_catalog_variant_set_create($set, $user_id, array('submit_form' => array(), 'barcode_auto' => false, 'price_source' => 'supplier'));

    return (empty($made['errors']) && (count($made['product_ids']) === count($drafts))) ? array_map('intval', $made['product_ids']) : null;
}

/**
 * The item of a made draft points at it and follows it: buy price, stock.
 *
 * @param array $feed
 * @param array $state
 * @param array $draft
 * @param int   $product_id
 * @return void
 */
function pg_supplier_feed_draft_item($feed, &$state, $draft, $product_id)
{
    $value = $draft['value'];
    $buy = ($draft['buy'] !== null) ? ", buy_price = '" . (int) $draft['buy'] . "'" : '';

    db("UPDATE supplier_feed_items SET product_id = '" . (int) $product_id . "', active = '" . pg_supplier_feed_may_follow($feed['id'], $product_id) . "',
        stock = '" . max(0, (int) $value['stock']) . "', closed_by_feed = 0, changed_at = '" . time() . "'" . $buy . "
        WHERE feed_id = '" . (int) $feed['id'] . "' AND supplier_key = '" . escape($value['key']) . "'");

    $state['count']['added']++;
}

/**
 * A draft's buy price net of VAT when the feed's prices include it (the
 * record's VAT; a draft has no rate of its own yet). Null when not read.
 *
 * @param array $value
 * @param array $context
 * @return int|null
 */
function pg_supplier_feed_draft_buy($value, $context)
{
    $buy = isset($value['buy']) ? $value['buy'] : null;

    if (($buy !== null) && !empty($context['vat_included']) && (($value['vat'] ?? null) !== null)) {
        $buy = pg_supplier_feed_net_of_vat($buy, $value['vat']);
    }

    return ($buy === null) ? null : (int) $buy;
}

/**
 * A draft's selling price: the rule of its category or brand on the buy
 * price, 0 without a saved rule, a rate or a buy price.
 *
 * @param int|null $buy
 * @param array    $value
 * @param array    $context
 * @return int
 */
function pg_supplier_feed_draft_price($buy, $value, $context)
{
    if (($buy === null) || ($context['rules'] === null) || ((float) $context['rate'] <= 0)) {
        return 0;
    }

    $rule = pg_supplier_feed_rule_pick($context['rules'], $value['category'], $value['brand']);

    return is_array($rule) ? max(0, (int) pg_supplier_feed_price($buy, $rule, $context['rate'], $context['home_decimals'], $context['feed_decimals'])) : 0;
}

/**
 * The browse product group a category names, by its name or label, else the
 * feed's group for drafts (the Fields card), else none.
 *
 * @param string $category
 * @param array  $mapping  draft_group
 * @return int
 */
function pg_supplier_feed_draft_group_id($category, $mapping)
{
    foreach (pg_supplier_feed_category_names($category) as $name) {
        $name = pg_supplier_feed_cut($name, 100);
        $found = (int) db_value("SELECT id FROM product_groups
            WHERE display_type <> 'select' AND ((short_description = '" . escape($name) . "') OR (name = '" . escape($name) . "'))" . pg_catalog_bin_live_sql('product_groups') . "
            ORDER BY id LIMIT 1");

        if ($found > 0) {
            return $found;
        }
    }

    $group_id = isset($mapping['draft_group']) ? (int) $mapping['draft_group'] : 0;

    if (($group_id > 0) && ((int) db_value("SELECT COUNT(*) FROM product_groups WHERE id = '" . $group_id . "' AND display_type <> 'select'" . pg_catalog_bin_live_sql('product_groups')) > 0)) {
        return $group_id;
    }

    return 0;
}

/**
 * A description as the product stores it, cleaned as the panel's editor
 * content is.
 *
 * @param string $html
 * @return string
 */
function pg_supplier_feed_draft_html($html)
{
    $html = trim((string) $html);

    return ($html === '') ? '' : prepare_rich_text_editor_content_for_input(pg_sanitize_rich_text($html, 'staff'));
}

/**
 * The stored names of a record's pictures, in order; one that cannot be
 * fetched is a note. An address the model's records share is fetched once.
 *
 * @param array    $feed
 * @param string[] $urls
 * @param string[] $notes
 * @param array    $fetched address => pg_supplier_feed_picture_fetch(), kept for the model
 * @return string[]
 */
function pg_supplier_feed_draft_pictures($feed, $urls, &$notes, &$fetched)
{
    $names = array();

    foreach ($urls as $url) {
        if (!isset($fetched[$url])) {
            $fetched[$url] = pg_supplier_feed_picture_fetch($feed, $url);
        }

        $stored = $fetched[$url];

        if ($stored['ok']) {
            $names[] = $stored['name'];
        } else {
            $notes[] = lang(array('string' => 'The picture {var:1} was not fetched: {var:2}', 'vars' => array($url, $stored['reason'])));
        }
    }

    return array_values(array_unique($names));
}

/**
 * One picture into the file library, through the address check: no address
 * inside the network, the address pinned, the cap of one upload, only an
 * answer that is a picture.
 *
 * @param array  $feed
 * @param string $url
 * @return array ok, name, reason
 */
function pg_supplier_feed_picture_fetch($feed, $url)
{
    $url = pg_import_media_normal($url);

    if ($url === '') {
        return array('ok' => false, 'name' => '', 'reason' => lang('The address is not a web address.'));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $limits = pg_upload_limits();
    $max = ((int) $limits['file_max'] > 0) ? (int) $limits['file_max'] : PG_IMPORT_MEDIA_DEFAULT_MAX;
    $temp = PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_' . (int) $feed['id'] . '_picture.tmp';
    $handle = @fopen($temp, 'wb');

    if ($handle === false) {
        return array('ok' => false, 'name' => '', 'reason' => lang('The server could not write a temporary file.'));
    }

    $answer = pg_curl_run('GET', $url, array('sink' => $handle, 'cap' => $max, 'timeout' => PG_IMPORT_MEDIA_TIMEOUT, 'check_address' => true));

    fclose($handle);

    $refusal = pg_import_media_refusal($answer, $max, true);

    if ($refusal !== '') {
        @unlink($temp);

        return array('ok' => false, 'name' => '', 'reason' => $refusal);
    }

    $stored = pg_file_store_upload($temp, pg_import_media_name($url, (string) $answer['content_type']), (int) $answer['received'], array(
        'folder'         => (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1"),
        'description'    => '',
        'image_settings' => function_exists('pg_image_settings') ? pg_image_settings() : array(),
        'file_max'       => $max,
    ));

    @unlink($temp);

    if (empty($stored['ok'])) {
        return array('ok' => false, 'name' => '', 'reason' => (string) ($stored['message'] ?? lang('The file could not be stored.')));
    }

    db("UPDATE files SET user = '" . (int) $feed['created_by'] . "' WHERE id = '" . (int) $stored['file']['id'] . "'");

    return array('ok' => true, 'name' => (string) $stored['file']['name'], 'reason' => '');
}

/**
 * A text cut to a column's letters.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function pg_supplier_feed_cut($text, $max)
{
    $text = trim((string) $text);

    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
