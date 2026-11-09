<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Variant Sets list (view_products.php?mode=variant_sets, drawn by
 * pg_pb_variant_sets_screen() in product_builder.php) on the list table
 * driver, printed whole (browser mode): its columns and the cells of one
 * set. Enable, Disable and Delete post the selected ids as group_ids[] to
 * variant_set_action.php, which checks each one again.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/fn/list_table.php.
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
 * The list's name: its pg_list_state() key, its columns preferences and its
 * place in pg_list_table_printed_lists().
 */
const PG_VARIANT_SET_LIST = 'view_products_variant_sets';

/**
 * The columns of the list, as pg_list_table_printed() takes them. The name
 * leads (it opens the set); the image and the row's buttons do not sort.
 * Pure but for lang().
 *
 * @return array key => spec
 */
function pg_variant_set_list_columns()
{
    return array(
        'name' => array('heading' => lang('Name'), 'kind' => 'link'),
        'image' => array('heading' => lang('Image'), 'kind' => 'image', 'width' => '4.5rem'),
        'short_description' => array('heading' => lang('Short Description')),
        'variants' => array('heading' => lang('Variants'), 'kind' => 'badge'),
        'attributes' => array('heading' => lang('Product Attributes'), 'sortable' => false),
        'price' => array('heading' => lang('Unit Price'), 'kind' => 'money'),
        'parent' => array('heading' => lang('Parent Product Group')),
        'enabled' => array('heading' => lang('Enabled'), 'kind' => 'badge'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    );
}

/**
 * The cells of one variant set. A set with no products left is shown as a
 * warning, not a quiet zero: its products were deleted out from under it.
 * One price when the variants agree, a span when they do not, as the
 * catalog shows it. Pure but for lang(), prepare_amount() and
 * get_relative_time().
 *
 * @param array $set        the screen's row: id, name, enabled,
 *                          short_description, image_name, timestamp,
 *                          parent_name, username, variant_count,
 *                          min_price, max_price (minor units)
 * @param array $attributes the names of the attributes the set varies on
 * @return array key => pg_list_cell()
 */
function pg_variant_set_list_cells(array $set, array $attributes)
{
    $id = (int) $set['id'];
    $count = (int) $set['variant_count'];
    $min = (int) $set['min_price'];
    $max = (int) $set['max_price'];
    $none = '<span class="fg-secondary">&mdash;</span>';

    // A set is edited on the group screen, which lists its products and links
    // to each one; a product editor would offer to add a variant to a variant.
    $name = '<a href="edit_product_group.php?id=' . $id . '" data-pg-open>' . h((string) $set['name']) . '</a>';

    $image = ((string) $set['image_name'] !== '')
        ? '<img src="' . OUTPUT_PATH . h((string) $set['image_name']) . '" alt="" class="rounded-4" loading="lazy" style="width:2.5rem;height:2.5rem;object-fit:cover;" onerror="this.style.display=\'none\'" />'
        : '<span class="d-inline-flex align-items-center justify-content-center bg-1 rounded-4 fg-secondary" style="width:2.5rem;height:2.5rem;"><i class="bi bi-image" aria-hidden="true"></i></span>';

    $variants = ($count === 0)
        ? '<span class="badge theme-warning">' . lang('No products') . '</span>'
        : '<span class="badge theme-primary">' . lang(array(
            'string' => '{var:1} variant{suffix:1}',
            'vars' => array($count),
            'suffix' => ($count === 1) ? '' : 's')) . '</span>';

    $labels = '';

    foreach ($attributes as $label) {
        $labels .= '<span class="badge badge-subtle theme-secondary border me-1">' . h((string) $label) . '</span>';
    }

    // prepare_amount() is printed as it is: the currency symbol is a setting,
    // often an HTML entity (&#8378;), which h() would print as text.
    if ($count === 0) {
        $price = $none;
    } elseif ($min === $max) {
        $price = prepare_amount($min / 100);
    } else {
        $price = prepare_amount($min / 100) . ' &ndash; ' . prepare_amount($max / 100);
    }

    $enabled = !empty($set['enabled']);

    $duplicate = '<a class="btn btn-sm btn-text theme-secondary" href="duplicate_product_group.php?id=' . $id . '" title="' . h(lang('Duplicate')) . '" aria-label="' . h(lang('Duplicate')) . '"><i class="bi bi-files" aria-hidden="true"></i></a>';

    return array(
        'name' => pg_list_cell($name, 'align-middle text-nowrap'),
        'image' => pg_list_cell($image),
        'short_description' => pg_list_cell(h((string) $set['short_description'])),
        'variants' => pg_list_cell($variants, 'align-middle', array('data-pg-sort-value' => $count)),
        'attributes' => pg_list_cell(($labels !== '') ? $labels : $none),
        'price' => pg_list_cell($price, 'align-middle', array('data-pg-sort-value' => ($count === 0) ? -1 : $min)),
        'parent' => pg_list_cell(((string) $set['parent_name'] !== '') ? h((string) $set['parent_name']) : '<span class="fg-secondary">' . lang('None') . '</span>'),
        'enabled' => pg_list_cell('<span class="badge ' . ($enabled ? 'theme-success">' . lang('Enabled') : 'theme-secondary">' . lang('Disabled')) . '</span>', 'align-middle', array('data-pg-sort-value' => $enabled ? 1 : 0)),
        'last_modified' => pg_list_table_when_cell($set['timestamp'], (string) ($set['username'] ?? '')),
        'actions' => pg_list_cell($duplicate),
    );
}
