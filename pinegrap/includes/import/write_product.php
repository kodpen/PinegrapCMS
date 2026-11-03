<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product writer of the content import framework. An item's data (see
 * includes/import/woocommerce.php) is a simple product, written by the
 * product screens' pg_pb_create_product(), or a variable one, written as a
 * variant set by pg_catalog_variant_set_create().
 *
 * - A SKU another product holds is not written again: a simple product is
 *   skipped, a variant left out with a note.
 * - Categories become browse product groups under the mapped group, found by
 *   name under their parent first; attributes and their options are found
 *   by name and made when missing.
 * - Pictures are fetched by the import's media fetch (includes/import/
 *   media.php) when this site has it; a picture that fails is a note.
 * - Undo moves the product to the Recycle Bin, the Catalog Manager's delete,
 *   and with it its variant set's group once no product of it is left, and
 *   a category group this import made once it holds nothing.
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
 * Writes one product item.
 *
 * @param array $item
 * @param array $import pg_import_get()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_product($item, $import)
{
    $data = (array) ($item['data'] ?? array());

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The store is turned off on this site.'));
    }

    if ((string) ($data['error'] ?? '') !== '') {
        return array('result' => 'failed', 'id' => 0, 'reason' => (string) $data['error']);
    }

    if (!in_array($data['type'] ?? '', array('simple', 'variable'), true)) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang(array('string' => 'Only simple and variable products are imported; this one is {var:1}.', 'vars' => array((string) ($data['source_type'] ?? '')))));
    }

    if (!function_exists('pg_pb_create_product')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $notes = array_values((array) ($data['notes'] ?? array()));
    $user_id = (int) ($import['user_id'] ?? 0);
    $variants = array();

    foreach ((array) ($data['variants'] ?? array()) as $variant) {
        $holder = pg_product_name_holder($variant['sku']);

        if ($holder !== null) {
            $notes[] = lang(array('string' => 'Variation {var:1} is left out: product {var:2} holds its SKU already.', 'vars' => array($variant['name'], (int) $holder['id'])));
            continue;
        }

        $variants[] = $variant;
    }

    if ($data['type'] === 'simple') {
        $holder = pg_product_name_holder($data['sku']);

        if ($holder !== null) {
            return array('result' => 'skipped', 'id' => 0, 'reason' => lang(array('string' => 'Product {var:1} holds this SKU already.', 'vars' => array((int) $holder['id']))));
        }
    } elseif (!$variants) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('Other products hold the SKUs of all its variations already.'));
    }

    $related = pg_import_related_ids($import);
    $mapped = (int) ($import['mapping']['product_group'] ?? 0);
    $group_ids = array();

    foreach ((array) ($data['categories'] ?? array()) as $path) {
        $group_ids[] = pg_import_product_category($path, $mapped, $user_id);
    }

    $group_ids = array_values(array_unique(array_filter($group_ids)));

    $images = pg_import_product_images($import, $related, (array) ($data['images'] ?? array()), $notes);

    // One variation left is one product, named as its parent.
    if (($data['type'] === 'variable') && (count($variants) === 1)) {
        $only = $variants[0];
        $data = array_merge($data, array('sku' => $only['sku'], 'price' => $only['price'], 'compare_at' => $only['compare_at'], 'stock' => $only['stock']));
        $images = array_merge(pg_import_product_images($import, $related, $only['images'], $notes), $images);
        $variants = array();
    }

    if (!$variants) {
        $row = pg_import_product_row($data, $user_id);
        $row['full_description'] = pg_import_product_html($import, $related, (string) $data['description'], $notes);
        $row['details'] = pg_import_product_html($import, $related, (string) $data['details'], $notes);

        $id = (int) pg_pb_create_product($row, array(
            'images'       => $images,
            'group_ids'    => $group_ids ?: (($mapped > 0) ? array($mapped) : array()),
            'attributes'   => array(),
            'submit_form'  => array(),
            'price_source' => 'import',
        ));

        if ($id <= 0) {
            return array('result' => 'failed', 'id' => 0, 'reason' => lang('The product could not be created.'));
        }

        pg_import_product_compare_at($id, $data['compare_at']);

        return array('result' => 'made', 'id' => $id, 'reason' => implode(' ', $notes));
    }

    $pairs = array();

    foreach ($variants as $index => $variant) {
        $pairs[$index] = array();

        foreach ($variant['options'] as $axis => $value) {
            $pairs[$index][] = pg_import_product_option($axis, $value, $user_id);
        }

        $variants[$index]['images'] = pg_import_product_images($import, $related, $variant['images'], $notes);
    }

    $set = pg_import_product_variant_set($data, $variants, $pairs, $group_ids ? $group_ids[0] : $mapped, $images, $user_id);
    $set['full_description'] = pg_import_product_html($import, $related, (string) $data['description'], $notes);
    $set['details'] = pg_import_product_html($import, $related, (string) $data['details'], $notes);

    $created = pg_catalog_variant_set_create($set, $user_id, array('submit_form' => array(), 'barcode_auto' => false, 'price_source' => 'import'));

    if (!empty($created['errors']) || !$created['product_ids']) {
        return array('result' => 'failed', 'id' => 0, 'reason' => (string) ($created['errors'][0]['message'] ?? lang('The product group could not be created.')));
    }

    foreach ($created['product_ids'] as $index => $product_id) {
        pg_import_product_compare_at($product_id, $variants[$index]['compare_at']);

        // A variation switched off in the shop stays off.
        if (empty($variants[$index]['enabled']) && !empty($data['enabled'])) {
            db("UPDATE products SET enabled = '0' WHERE id = '" . (int) $product_id . "'");
        }

        // The framework lists the first product; the others are listed here,
        // so undo reaches every product of the set.
        if ($index > 0) {
            db("INSERT IGNORE INTO import_items SET
                import_id = '" . (int) $import['id'] . "',
                kind = 'product',
                item_id = '" . (int) $product_id . "',
                source_key = '" . e(mb_substr($variants[$index]['source_key'], 0, 191)) . "'");
        }
    }

    return array('result' => 'made', 'id' => (int) $created['product_ids'][0], 'reason' => implode(' ', $notes));
}

/**
 * The product columns of a simple product item, the starting row the
 * Workspace and the external API write, then the item's own. Pure but for
 * the decimal cell reader of the product import.
 *
 * @param array $data
 * @param int   $user_id
 * @return array
 */
function pg_import_product_row($data, $user_id)
{
    $tracked = ($data['stock'] !== null);
    $weight = ((string) $data['weight'] !== '') ? pg_products_import_decimal_cell('weight', (string) $data['weight']) : null;

    return array(
        'name' => (string) $data['sku'], 'short_description' => mb_substr((string) $data['name'], 0, 100),
        'enabled' => !empty($data['enabled']) ? '1' : '0', 'price' => (int) $data['price'],
        'taxable' => '1', 'shippable' => '1', 'free_shipping' => '0',
        'title' => '', 'meta_description' => '', 'brand' => '', 'gtin' => '', 'mpn' => '', 'keywords' => (string) $data['keywords'],
        'weight' => ($weight !== null) ? $weight : 0, 'length' => 0, 'width' => 0, 'height' => 0,
        'inventory' => $tracked ? '1' : '0', 'inventory_quantity' => $tracked ? (int) $data['stock'] : 0,
        'user' => (int) $user_id, 'full_description' => '', 'meta_keywords' => '', 'notes' => '', 'details' => '',
        'seo_analysis' => '', 'code' => '', 'out_of_stock_message' => '', 'order_receipt_message' => '', 'add_comment_message' => '',
        'gift_card_email_body' => '', 'google_product_category' => '', 'tax_rate' => null,
    );
}

/**
 * The input of pg_catalog_variant_set_create() for a variable product
 * item. Pure but for the decimal cell reader.
 *
 * @param array $data      the item's data
 * @param array $variants  the variations written
 * @param array $pairs     per variation, its attribute_id / option_id pairs
 * @param int   $parent_id the group the set goes under, 0 for the top
 * @param array $images    the stored pictures of the parent
 * @param int   $user_id
 * @return array
 */
function pg_import_product_variant_set($data, $variants, $pairs, $parent_id, $images, $user_id)
{
    $common = pg_import_product_row($data, $user_id);

    foreach (array('name', 'short_description', 'price', 'inventory', 'inventory_quantity', 'gtin', 'full_description', 'details', 'title', 'meta_description') as $own) {
        unset($common[$own]);
    }

    $rows = array();
    $axes = array();
    $base = null;

    foreach ($variants as $index => $variant) {
        $tracked = ($variant['stock'] !== null);
        $rows[] = array(
            'name'               => (string) $variant['sku'],
            'short_description'  => mb_substr((string) $variant['name'], 0, 100),
            'price'              => (int) $variant['price'],
            'price_manual'       => true,
            'inventory'          => $tracked ? '1' : '0',
            'inventory_quantity' => $tracked ? (int) $variant['stock'] : 0,
            'images'             => (array) $variant['images'],
            'attributes'         => $pairs[$index],
            'recurring'          => array(),
            'gtin'               => '',
            'barcode'            => '',
        );

        foreach ($pairs[$index] as $pair) {
            $axes[$pair['attribute_id']] = array('id' => $pair['attribute_id'], 'default_option_id' => 0);
        }

        $base = ($base === null) ? (int) $variant['price'] : min($base, (int) $variant['price']);
    }

    return array(
        'name'              => (string) $data['sku'],
        'short_description' => mb_substr((string) $data['name'], 0, 100),
        'full_description'  => '',
        'details'           => '',
        'parent_id'         => (int) $parent_id,
        'enabled'           => !empty($data['enabled']),
        'keywords'          => (string) $data['keywords'],
        'images'            => array_values($images),
        'title'             => '',
        'meta_description'  => '',
        'attribute_meta'    => array_values($axes),
        'common'            => $common,
        'zone_ids'          => array(),
        'price_base'        => (int) $base,
        'price_rules'       => null,
        'variants'          => $rows,
    );
}

/**
 * A description as the product stores it: its pictures fetched when this
 * site has the import's media fetch, then cleaned as the panel's editor
 * content is.
 *
 * @param array    $import
 * @param int[]    $related
 * @param string   $html
 * @param string[] $notes   grows by a note per picture that stays at its old address
 * @return string
 */
function pg_import_product_html($import, $related, $html, &$notes)
{
    if (trim($html) === '') {
        return '';
    }

    if (pg_import_product_media() && function_exists('pg_import_media_localize')) {
        $local = pg_import_media_localize($import, $related, $html);

        if (is_array($local)) {
            $html = (string) ($local['html'] ?? $html);
            $notes = array_merge($notes, (array) ($local['notes'] ?? array()));
        }
    }

    return prepare_rich_text_editor_content_for_input(pg_sanitize_rich_text($html, 'staff'));
}

/**
 * The stored names of an item's pictures, in order; a picture that cannot
 * be fetched is a note.
 *
 * @param array    $import
 * @param int[]    $related
 * @param string[] $urls
 * @param string[] $notes
 * @return string[]
 */
function pg_import_product_images($import, $related, $urls, &$notes)
{
    if (!$urls) {
        return array();
    }

    if (!pg_import_product_media()) {
        $notes[] = lang('The pictures were not fetched: this site cannot fetch import media yet.');

        return array();
    }

    $names = array();

    foreach ($urls as $url) {
        $stored = pg_import_media_fetch($import, $related, $url, array('images_only' => true));

        if (!empty($stored['ok'])) {
            $names[] = (string) $stored['name'];
        } else {
            $notes[] = lang(array('string' => 'The picture {var:1} was not fetched: {var:2}', 'vars' => array($url, (string) ($stored['reason'] ?? ''))));
        }
    }

    return array_values(array_unique($names));
}

// Whether this site has the import's media fetch.
function pg_import_product_media()
{
    $file = PG_FUNCTIONS_DIR . '/includes/import/media.php';

    if (!function_exists('pg_import_media_fetch') && is_file($file)) {
        require_once($file);
    }

    return function_exists('pg_import_media_fetch');
}

/**
 * The product group of a category path, each step found by its name under
 * the one above (the mapped group, else the top) or made there.
 *
 * @param string[] $path
 * @param int      $mapped
 * @param int      $user_id
 * @return int
 */
function pg_import_product_category($path, $mapped, $user_id)
{
    $parent = ($mapped > 0) ? $mapped : (int) pg_catalog_group_top_id();

    foreach ($path as $name) {
        $name = mb_substr(trim((string) $name), 0, 100);
        $found = (int) db_value("SELECT id FROM product_groups
            WHERE parent_id = '" . (int) $parent . "' AND display_type <> 'select'
            AND ((short_description = '" . e($name) . "') OR (name = '" . e($name) . "'))" . pg_catalog_bin_live_sql('product_groups') . "
            ORDER BY id LIMIT 1");

        if ($found <= 0) {
            $found = (int) pg_catalog_group_create(array(
                'name'              => get_unique_name(array('name' => $name, 'type' => 'product_group')),
                'enabled'           => '1',
                'parent_id'         => $parent,
                'short_description' => $name,
            ), $user_id);
        }

        if ($found <= 0) {
            return 0;
        }

        $parent = $found;
    }

    return $parent;
}

/**
 * The attribute_id / option_id pair of an axis and its value, the attribute
 * and the option found by name or made.
 *
 * @param string $axis
 * @param string $value
 * @param int    $user_id
 * @return array{attribute_id:int,option_id:int}
 */
function pg_import_product_option($axis, $value, $user_id)
{
    $axis = mb_substr(trim((string) $axis), 0, 100);
    $value = mb_substr(trim((string) $value), 0, 100);

    $attribute_id = (int) db_value("SELECT id FROM product_attributes WHERE name = '" . e($axis) . "' ORDER BY id LIMIT 1");

    if ($attribute_id <= 0) {
        db("INSERT INTO product_attributes (name, label, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES ('" . e($axis) . "', '" . e($axis) . "', '" . (int) $user_id . "', UNIX_TIMESTAMP(), '" . (int) $user_id . "', UNIX_TIMESTAMP())");
        $attribute_id = (int) mysqli_insert_id(db::$con);
    }

    $option_id = (int) db_value("SELECT id FROM product_attribute_options
        WHERE product_attribute_id = '" . $attribute_id . "' AND label = '" . e($value) . "' ORDER BY id LIMIT 1");

    if ($option_id <= 0) {
        $sort_order = 1 + (int) db_value("SELECT MAX(sort_order) FROM product_attribute_options WHERE product_attribute_id = '" . $attribute_id . "'");
        db("INSERT INTO product_attribute_options (product_attribute_id, label, no_value, sort_order)
            VALUES ('" . $attribute_id . "', '" . e($value) . "', '0', '" . $sort_order . "')");
        $option_id = (int) mysqli_insert_id(db::$con);
    }

    return array('attribute_id' => $attribute_id, 'option_id' => $option_id);
}

// The compare-at price of a made product, when the site has the column.
function pg_import_product_compare_at($product_id, $cents)
{
    if (($cents === null) || !function_exists('pg_compare_at_price_ready') || !pg_compare_at_price_ready()) {
        return;
    }

    $value = pg_compare_at_price_value((int) $cents);

    if (is_int($value)) {
        db("UPDATE products SET compare_at_price = '" . $value . "' WHERE id = '" . (int) $product_id . "'");
    }
}

/**
 * Undo of one made product: into the Recycle Bin, then its variant set's
 * group when no live product is left in it, and a category group this
 * import's person made since the import started when it holds nothing.
 *
 * @param int $item_id
 */
function pg_import_unwrite_product($item_id)
{
    $item_id = (int) $item_id;

    if (!pg_catalog_bin_ready()) {
        return;
    }

    $import = db_item("SELECT imports.user_id, imports.started_at FROM import_items
        JOIN imports ON imports.id = import_items.import_id
        WHERE import_items.kind = 'product' AND import_items.item_id = '" . $item_id . "'
        ORDER BY import_items.id DESC LIMIT 1");
    $user_id = (int) ($import['user_id'] ?? 0);
    $groups = (array) db_items("SELECT product_groups.id, product_groups.parent_id, product_groups.display_type, product_groups.user, product_groups.timestamp
        FROM products_groups_xref JOIN product_groups ON product_groups.id = products_groups_xref.product_group
        WHERE products_groups_xref.product = '" . $item_id . "'");

    pg_catalog_bin_product($item_id, $user_id);

    while ($groups) {
        $group = array_shift($groups);
        $made = ((int) $group['user'] === $user_id) && is_array($import) && ((int) $group['timestamp'] >= (int) $import['started_at']);

        if (($group['display_type'] !== 'select') && !$made) {
            continue;
        }

        $products = (int) db_value("SELECT COUNT(*) FROM products_groups_xref
            JOIN products ON products.id = products_groups_xref.product
            WHERE products_groups_xref.product_group = '" . (int) $group['id'] . "'" . pg_catalog_bin_live_sql('products'));
        $children = (int) db_value("SELECT COUNT(*) FROM product_groups WHERE parent_id = '" . (int) $group['id'] . "'" . pg_catalog_bin_live_sql('product_groups'));

        if (($products === 0) && ($children === 0) && (pg_catalog_bin_group((int) $group['id'], $user_id) !== false)) {
            // A category made for this group alone may now be empty too.
            $parent = db_item("SELECT id, parent_id, display_type, user, timestamp FROM product_groups WHERE id = '" . (int) $group['parent_id'] . "'");

            if (is_array($parent)) {
                $groups[] = $parent;
            }
        }
    }
}
