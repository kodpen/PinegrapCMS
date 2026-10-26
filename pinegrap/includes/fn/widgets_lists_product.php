<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the product sources of the lists inside a
// record (widgets_lists.php), drawn in the catalog detail widget.
//
// Loaded by functions.php through require_once, never on its own.
//
// A product list repeats what one product holds: its images, its attribute
// values, the products bought with it. The catalog detail renderer reads
// the rows of the sources its tree lists (pg_sw_list_wanted()) for the
// product it shows and publishes them (pg_sw_list_publish('product', …));
// the finish pass draws each span from them, keyed by ^^__id^^. The rows
// are built here from what the queries return, so the row shape is tested
// without a database.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The product sources, in the shape of pg_sw_list_sources(). None takes
// an arg: a product list lists what the product shown holds.
function pg_sw_list_product_sources()
{
    return array(
        'product.images' => array(
            'family'       => 'product',
            'key'          => '__id',
            'label'        => lang('Images of the product'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__gallery_url', lang('Image address')),
            ),
        ),
        'product.attributes' => array(
            'family'       => 'product',
            'key'          => '__id',
            'label'        => lang('Attributes of the product'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__attr_name', lang('Attribute')),
                array('__attr_value', lang('Attribute value')),
            ),
        ),
        'product.related' => array(
            'family'       => 'product',
            'key'          => '__id',
            'label'        => lang('Products bought together'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__related_name', lang('Product name')),
                array('__related_short_description', lang('Short Description')),
                array('__related_url', lang('Product address')),
                array('__related_image_url', lang('Image address')),
                array('__related_price', lang('Price')),
                array('__related_original_price', lang('Price before the discount')),
            ),
        ),
    );
}

// The rows of an images list: the product's main image first, then its
// other images in the order given, each address once. $main: the
// product's image_name; $extra: products_images_xref rows (file_name).
function pg_sw_list_product_image_rows($main, $extra)
{
    $base  = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $names = array((string)$main);
    foreach (is_array($extra) ? $extra : array() as $row) {
        if (is_array($row) && isset($row['file_name'])) $names[] = (string)$row['file_name'];
    }
    $out  = array();
    $seen = array();
    foreach ($names as $name) {
        if (trim($name) === '' || isset($seen[$name])) continue;
        $seen[$name] = true;
        $out[] = array('__gallery_url' => $base . encode_url_path($name));
    }
    return $out;
}

// The rows of an attributes list from the product's attribute rows (name,
// label, option_label, no_value), in the order given: the attribute's
// label (its name without one) and the option the product has. An option
// marked as no value, and an empty one, give no row.
function pg_sw_list_product_attr_rows($rows)
{
    $out = array();
    foreach (is_array($rows) ? $rows : array() as $row) {
        if (!is_array($row) || !empty($row['no_value'])) continue;
        $value = isset($row['option_label']) ? trim((string)$row['option_label']) : '';
        if ($value === '') continue;
        $label = isset($row['label']) ? trim((string)$row['label']) : '';
        if ($label === '' && isset($row['name'])) $label = trim((string)$row['name']);
        $out[] = array('__attr_name' => $label, '__attr_value' => $value);
    }
    return $out;
}

// The rows of a related products list from the cross-sell items
// (_civ_get_cross_sell_items(): the products ordered with this one, else
// those of its groups). The prices are plain text: the price paid, and
// the sticker price only when a discount applies.
function pg_sw_list_product_related_rows($items)
{
    $out = array();
    foreach (is_array($items) ? $items : array() as $it) {
        if (!is_array($it) || empty($it['id'])) continue;
        $out[] = array(
            '__related_name'              => isset($it['name']) ? (string)$it['name'] : '',
            '__related_short_description' => isset($it['short_description']) ? (string)$it['short_description'] : '',
            '__related_url'               => isset($it['url']) ? (string)$it['url'] : '#',
            '__related_image_url'         => isset($it['image_url']) ? (string)$it['image_url'] : '',
            '__related_price'             => isset($it['price_formatted']) ? (string)$it['price_formatted'] : '',
            '__related_original_price'    => (!empty($it['has_discount']) && isset($it['original_price_formatted'])) ? (string)$it['original_price_formatted'] : '',
        );
    }
    return $out;
}

// What the catalog detail renderer publishes for product $p (its id and
// image_name read): source => product id => rows, for each source in
// $sources (source => true). One query per source; $detail_page_id is the
// page a related product links to (0: none, as the cross-sell block).
function pg_sw_list_product_read($p, $sources, $detail_page_id)
{
    $out = array();
    $pid = (is_array($p) && isset($p['id'])) ? (int)$p['id'] : 0;
    if ($pid <= 0 || !is_array($sources)) return $out;

    if (isset($sources['product.images'])) {
        $extra = db_items(
            "SELECT file_name FROM products_images_xref
             WHERE product = '$pid' AND file_name != ''
             ORDER BY file_name ASC
             LIMIT 100"
        );
        $out['product.images'][$pid] = pg_sw_list_product_image_rows(isset($p['image_name']) ? $p['image_name'] : '', $extra);
    }
    if (isset($sources['product.attributes'])) {
        $attrs = db_items(
            "SELECT pa.name, pa.label, pao.label AS option_label, pao.no_value
             FROM products_attributes_xref pax
             INNER JOIN product_attributes pa ON pa.id = pax.attribute_id
             INNER JOIN product_attribute_options pao ON pao.id = pax.option_id
             WHERE pax.product_id = '$pid'
             ORDER BY pa.id ASC, pao.sort_order ASC
             LIMIT 100"
        );
        $out['product.attributes'][$pid] = pg_sw_list_product_attr_rows($attrs);
    }
    if (isset($sources['product.related'])) {
        $out['product.related'][$pid] = pg_sw_list_product_related_rows(_civ_get_cross_sell_items($pid, (int)$detail_page_id, 12));
    }
    return $out;
}
