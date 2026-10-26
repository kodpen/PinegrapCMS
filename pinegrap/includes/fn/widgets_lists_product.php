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
// record (widgets_lists.php), drawn in the catalog detail and listing widgets.
//
// Loaded by functions.php through require_once, never on its own.
//
// A product list repeats what one product holds: its images, its attribute
// values, the products bought with it. The catalog detail and listing
// renderers read the rows of the sources their tree lists
// (pg_sw_list_wanted()) for the products they show and publish them
// (pg_sw_list_publish('product', …)); the finish pass draws each span from
// them, keyed by ^^__id^^ (a group card in a listing reads none:
// pg_sw_list_key_guard()). The rows
// are built here from what the queries return, so the row shape is tested
// without a database.
//
// The crumbs of the catalog path (catalog.crumbs) are the page's, not a
// product's: their own family, keyed by ^^__has_breadcrumb^^ (1 when the
// widget draws a path), so a list of them stands anywhere in the widget.
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

// What a catalog renderer publishes for the products it shows ($products:
// rows with their id and image_name): source => product id => rows, for
// each source in $sources (source => true). One query per source for all
// the products, but the products bought together: one cross-sell lookup
// per product (only the detail view offers them). $detail_page_id is the
// page a related product links to (0: none, as the cross-sell block).
function pg_sw_list_product_read($products, $sources, $detail_page_id = 0)
{
    $out  = array();
    $main = array();
    foreach (is_array($products) ? $products : array() as $p) {
        $pid = (is_array($p) && isset($p['id'])) ? (int)$p['id'] : 0;
        if ($pid > 0) $main[$pid] = isset($p['image_name']) ? (string)$p['image_name'] : '';
    }
    if (!$main || !is_array($sources)) return $out;
    $ids = implode(',', array_keys($main));

    if (isset($sources['product.images'])) {
        $by = array();
        $rows = db_items(
            "SELECT product, file_name FROM products_images_xref
             WHERE product IN ($ids) AND file_name != ''
             ORDER BY product ASC, file_name ASC"
        );
        foreach (is_array($rows) ? $rows : array() as $row) $by[(int)$row['product']][] = $row;
        foreach ($main as $pid => $image) {
            $out['product.images'][$pid] = array_slice(pg_sw_list_product_image_rows($image, isset($by[$pid]) ? $by[$pid] : array()), 0, 100);
        }
    }
    if (isset($sources['product.attributes'])) {
        $by = array();
        $rows = db_items(
            "SELECT pax.product_id, pa.name, pa.label, pao.label AS option_label, pao.no_value
             FROM products_attributes_xref pax
             INNER JOIN product_attributes pa ON pa.id = pax.attribute_id
             INNER JOIN product_attribute_options pao ON pao.id = pax.option_id
             WHERE pax.product_id IN ($ids)
             ORDER BY pax.product_id ASC, pa.id ASC, pao.sort_order ASC"
        );
        foreach (is_array($rows) ? $rows : array() as $row) $by[(int)$row['product_id']][] = $row;
        foreach (array_keys($main) as $pid) {
            $out['product.attributes'][$pid] = array_slice(pg_sw_list_product_attr_rows(isset($by[$pid]) ? $by[$pid] : array()), 0, 100);
        }
    }
    if (isset($sources['product.related'])) {
        foreach (array_keys($main) as $pid) {
            $out['product.related'][$pid] = pg_sw_list_product_related_rows(_civ_get_cross_sell_items($pid, (int)$detail_page_id, 12));
        }
    }
    return $out;
}

// The crumbs source, in the shape of pg_sw_list_sources(): the catalog
// path the detail and listing widgets draw as a breadcrumb.
function pg_sw_list_crumb_sources()
{
    return array(
        'catalog.crumbs' => array(
            'family'       => 'crumbs',
            'key'          => '__has_breadcrumb',
            'label'        => lang('Crumbs of the catalog path'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__crumb_label', lang('Crumb')),
                array('__crumb_url', lang('Crumb address')),
                array('__crumb_kind', lang('Kind of crumb (link, text, active)')),
            ),
        ),
    );
}

// One crumb: its words, its address ('' for none) and its kind: 'link'
// (a link), 'text' (words without a link) or 'active' (where the visitor
// is).
function pg_sw_list_crumb_row($label, $url, $kind)
{
    return array(
        '__crumb_label' => (string)$label,
        '__crumb_url'   => (string)$url,
        '__crumb_kind'  => (string)$kind,
    );
}

// The crumbs of a catalog listing: "Main Catalog" linking to the listing's
// root, each group between the root and the open group ($groups, root
// side first: id, name, address_name) linking to its own view, the open
// group last as where the visitor is. At the root, "Main Catalog" alone is
// where the visitor is.
function pg_sw_list_crumb_rows_listing($groups, $root_url, $group_link_base)
{
    $groups = array_values(is_array($groups) ? $groups : array());
    if (!$groups) return array(pg_sw_list_crumb_row(lang('Main Catalog'), '', 'active'));
    $out  = array(pg_sw_list_crumb_row(lang('Main Catalog'), $root_url, 'link'));
    $last = count($groups) - 1;
    foreach ($groups as $i => $g) {
        $name = isset($g['name']) ? $g['name'] : '';
        if ($i === $last) {
            $out[] = pg_sw_list_crumb_row($name, '', 'active');
            continue;
        }
        $url = !empty($g['address_name']) ? $group_link_base . encode_url_path((string)$g['address_name']) : $root_url;
        $out[] = pg_sw_list_crumb_row($name, $url, 'link');
    }
    return $out;
}

// The crumbs of a catalog detail view: "Main Catalog" (a link to the
// catalog page when there is one), the product's groups root side first
// ($groups: id, name, address_name; a link to the group's view on the
// catalog page when both are known, else words), then the product's
// $product_name (null: none) as where the visitor is. On a group shown as
// one product ($on_group) that group is where the visitor is and no
// product follows.
function pg_sw_list_crumb_rows_item($groups, $catalog_root_url, $on_group, $product_name)
{
    $catalog_root_url = (string)$catalog_root_url;
    $out = array(pg_sw_list_crumb_row(lang('Main Catalog'), $catalog_root_url, $catalog_root_url !== '' ? 'link' : 'text'));
    $groups = array_values(is_array($groups) ? $groups : array());
    $last = count($groups) - 1;
    foreach ($groups as $i => $g) {
        $name = isset($g['name']) ? $g['name'] : '';
        $url  = ($catalog_root_url !== '' && !empty($g['address_name']))
              ? $catalog_root_url . '/' . encode_url_path((string)$g['address_name']) : '';
        if ($on_group && $i === $last) $out[] = pg_sw_list_crumb_row($name, '', 'active');
        elseif ($url !== '') $out[] = pg_sw_list_crumb_row($name, $url, 'link');
        else $out[] = pg_sw_list_crumb_row($name, '', 'text');
    }
    if (!$on_group && $product_name !== null) $out[] = pg_sw_list_crumb_row($product_name, '', 'active');
    return $out;
}
