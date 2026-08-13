<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Edit a variant set — v2.
 *
 * A variant set is one thing to the operator, so it is one screen: the group's
 * own fields on top, the products it holds underneath. Editing five colours of
 * the same shirt should not mean opening five screens.
 *
 * Entry points:
 *   ?group_id=N  the set itself
 *   ?id=N        a product; if it belongs to a select group we switch to that
 *                group, because editing one variant in isolation is what this
 *                screen exists to replace
 *
 * Scope of this round: the group's own fields, and the per-variant fields that
 * differ between variants (SKU, description, price, stock, enabled). Everything
 * else on a product — memberships, gift cards, recurring payments, product
 * forms — is still edited from edit_product.php, and each row links there
 * rather than pretending to offer it.
 *
 * Development screen. edit_product.php and edit_product_group.php are untouched.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

$liveform = new liveform('edit_product2');


/* ------------------------------------------------------------------ *
 * Resolve what we were asked to edit
 * ------------------------------------------------------------------ */

$group_id = isset($_REQUEST['group_id']) ? (int) $_REQUEST['group_id'] : 0;

if (!$group_id && isset($_REQUEST['id'])) {

    $product_id = (int) $_REQUEST['id'];
    $group_id   = pg_pb_variant_group_id($product_id);

    // A product with no variant set has nothing extra to show here; the full
    // product screen is the right place for it.
    if (!$group_id) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id);
    }
}

if (!$group_id) {
    output_error(lang('Page not found.') . ' <a href="view_products2.php">' . lang('Variant Sets') . '</a>.');
}

$group = db_items(
    "SELECT
        id,
        name,
        enabled,
        parent_id,
        short_description,
        full_description,
        details,
        code,
        keywords,
        image_name,
        display_type,
        address_name,
        title,
        meta_description
    FROM product_groups
    WHERE id = '" . (int) $group_id . "'
    LIMIT 1");

$group = $group ? $group[0] : NULL;

if (!$group) {
    output_error(lang('Page not found.') . ' <a href="view_products2.php">' . lang('Variant Sets') . '</a>.');
}

// The screen assumes a variant set. A browse group reaching this URL is not an
// error the operator caused, so send them where it does belong instead of
// showing a half-working screen.
if ($group['display_type'] !== 'select') {
    go(PATH . SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . (int) $group['id']);
}


/* ------------------------------------------------------------------ *
 * Save
 * ------------------------------------------------------------------ */

if ($_POST) {

    validate_token_field();

    if (trim($_POST['name']) === '') {
        output_error(
            lang(array('string' => '{var:1} is required', 'vars' => array(lang('Product Group Name')))) .
            '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $images = pg_pb_selected_images();

    pg_pb_update_row('product_groups', array(
        'name'              => trim($_POST['name']),
        'enabled'           => empty($_POST['enabled']) ? '' : '1',
        'parent_id'         => isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0,
        'short_description' => trim($_POST['short_description']),
        'full_description'  => prepare_rich_text_editor_content_for_input($_POST['full_description']),
        'details'           => prepare_rich_text_editor_content_for_input($_POST['details']),
        'keywords'          => $_POST['keywords'],
        'image_name'        => $images ? $images[0] : '',
        'title'             => trim($_POST['title']),
        'meta_description'  => trim($_POST['meta_description']),
    ), 'id', $group_id);

    // Images are a list, not a field: rewrite rather than diff.
    db("DELETE FROM product_groups_images_xref WHERE product_group = '" . (int) $group_id . "'");

    foreach (array_slice($images, 1) as $image) {
        pg_pb_insert_row('product_groups_images_xref', array(
            'product_group' => $group_id,
            'file_name'     => $image,
        ));
    }

    // Only products that actually belong to this set may be written through
    // this form — the ids come from the browser and a crafted post could
    // otherwise reach any product row.
    $member_ids = array();

    foreach (pg_pb_group_products($group_id) as $member) {
        $member_ids[(int) $member['id']] = TRUE;
    }

    $updated = 0;

    if (!empty($_POST['variant']) && is_array($_POST['variant'])) {

        foreach ($_POST['variant'] as $variant_id => $variant) {

            $variant_id = (int) $variant_id;

            if (!isset($member_ids[$variant_id]) || !is_array($variant)) {
                continue;
            }

            $name = isset($variant['name']) ? trim($variant['name']) : '';

            // A blank SKU makes the product unidentifiable in the order table,
            // so keep the stored one rather than blanking it.
            if ($name === '') {
                continue;
            }

            pg_pb_update_row('products', array(
                'name'               => $name,
                'short_description'  => isset($variant['short_description']) ? trim($variant['short_description']) : '',
                'price'              => pg_pb_price_to_cents(isset($variant['price']) ? $variant['price'] : 0),
                'inventory'          => empty($variant['inventory']) ? '' : '1',
                'inventory_quantity' => isset($variant['inventory_quantity']) ? trim($variant['inventory_quantity']) : '',
                'enabled'            => empty($variant['enabled']) ? '' : '1',
            ), 'id', $variant_id);

            $updated++;
        }
    }

    log_activity(
        lang(array(
            'string' => 'product group ({var:1}) was updated',
            'vars'   => array(trim($_POST['name'])))),
        $_SESSION['sessionusername']);

    $liveform->add_notice(
        lang(array(
            'string' => '{var:1} variant{suffix:1} updated.',
            'vars'   => array($updated),
            'suffix' => ($updated === 1) ? '' : 's')));

    go(PATH . SOFTWARE_DIRECTORY . '/edit_product2.php?group_id=' . $group_id);
}


/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

$products = pg_pb_group_products($group_id);

// Extra images beyond the cover.
//
// No ORDER BY: product_groups_images_xref is (product_group, file_name) and
// nothing else — no id, no sort_order. Rows come back in insertion order, which
// is the order the picker wrote them, and that is the only ordering available.
// Adding "ORDER BY id" here throws "Unknown column 'id' in 'order clause'".
// products_images_xref has the same shape.
$extra_images = db_items(
    "SELECT file_name
    FROM product_groups_images_xref
    WHERE product_group = '" . (int) $group_id . "'");

$images = array();

if ($group['image_name'] != '') {
    $images[] = $group['image_name'];
}

foreach ($extra_images as $extra_image) {
    $images[] = $extra_image['file_name'];
}

// Which attributes this set varies on, so the header can say what the variants
// actually differ by.
$set_attributes = db_items(
    "SELECT
        product_groups_attributes_xref.attribute_id,
        product_groups_attributes_xref.default_option_id,
        product_attributes.name
    FROM product_groups_attributes_xref
    LEFT JOIN product_attributes ON product_groups_attributes_xref.attribute_id = product_attributes.id
    WHERE product_groups_attributes_xref.product_group_id = '" . (int) $group_id . "'
    ORDER BY product_groups_attributes_xref.sort_order");

$output_set_attributes = '';

foreach ($set_attributes as $set_attribute) {
    if ($set_attribute['name'] != '') {
        $output_set_attributes .= '<span class="badge text-bg-light border me-1">' . h($set_attribute['name']) . '</span>';
    }
}

if ($output_set_attributes === '') {
    $output_set_attributes = '<span class="text-muted">' . lang('None') . '</span>';
}

// Per-variant option labels, resolved in one query rather than one per row.
$option_labels = array();

if ($products) {

    $product_ids = array();

    foreach ($products as $product) {
        $product_ids[] = (int) $product['id'];
    }

    $option_rows = db_items(
        "SELECT
            products_attributes_xref.product_id,
            product_attribute_options.label
        FROM products_attributes_xref
        LEFT JOIN product_attribute_options ON products_attributes_xref.option_id = product_attribute_options.id
        WHERE products_attributes_xref.product_id IN (" . implode(',', $product_ids) . ")
        ORDER BY products_attributes_xref.sort_order");

    foreach ($option_rows as $option_row) {
        if ($option_row['label'] != '') {
            $option_labels[$option_row['product_id']][] = $option_row['label'];
        }
    }
}

$output_variant_rows = '';

foreach ($products as $product) {

    $product_id = (int) $product['id'];
    $field      = 'variant[' . $product_id . ']';

    $combo = isset($option_labels[$product_id])
        ? implode(' / ', $option_labels[$product_id])
        : lang('None');

    $output_variant_rows .=
        '<div class="border rounded p-3 mb-3">
            <div class="row align-items-end">

                <div class="col-12 col-xl-2 mb-2">
                    <div class="fw-semibold">' . h($combo) . '</div>
                    <a href="edit_product.php?id=' . $product_id . '" class="small text-decoration-none">
                        <i class="bi bi-box-arrow-up-right me-1"></i>' . lang('All Settings') . '</a>
                </div>

                <div class="col-12 col-sm-6 col-xl-2 mb-2">
                    <label class="form-label" for="v_name_' . $product_id . '">' . lang('Product ID / SKU') . '</label>
                    <input type="text" class="form-control" id="v_name_' . $product_id . '" name="' . $field . '[name]" value="' . h($product['name']) . '" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3 mb-2">
                    <label class="form-label" for="v_short_' . $product_id . '">' . lang('Short Description') . '</label>
                    <input type="text" class="form-control" id="v_short_' . $product_id . '" name="' . $field . '[short_description]" value="' . h($product['short_description']) . '" />
                </div>

                <div class="col-6 col-sm-4 col-xl-2 mb-2">
                    <label class="form-label" for="v_price_' . $product_id . '">' . lang('Unit Price') . '</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" class="form-control" id="v_price_' . $product_id . '" name="' . $field . '[price]" value="' . h(pg_pb_cents_to_price($product['price'])) . '" />
                        <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                    </div>
                </div>

                <div class="col-6 col-sm-4 col-xl-2 mb-2">
                    <label class="form-label" for="v_stock_' . $product_id . '">' . lang('Inventory Quantity') . '</label>
                    <input type="number" min="0" class="form-control" id="v_stock_' . $product_id . '" name="' . $field . '[inventory_quantity]" value="' . h($product['inventory_quantity']) . '" />
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="v_inv_' . $product_id . '" name="' . $field . '[inventory]" value="1"' . ($product['inventory'] ? ' checked="checked"' : '') . ' />
                        <label class="form-check-label small" for="v_inv_' . $product_id . '">' . lang('Inventory Tracking') . '</label>
                    </div>
                </div>

                <div class="col-12 col-sm-4 col-xl-1 mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="v_enabled_' . $product_id . '" name="' . $field . '[enabled]" value="1"' . ($product['enabled'] ? ' checked="checked"' : '') . ' />
                        <label class="form-check-label" for="v_enabled_' . $product_id . '">' . lang('Enabled') . '</label>
                    </div>
                </div>

            </div>
        </div>';
}

if (!$products) {
    $output_variant_rows =
        '<div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ' . lang('This variant set has no products. They may have been deleted.') . '
        </div>';
}

$output_parent_field = '';

if (pg_pb_has_product_groups()) {
    $output_parent_field =
        '<div class="col-12 col-lg-6 my-2">
            <label for="parent_id" class="form-label">' . lang('Parent Product Group') . '</label>
            <select style="width:100%" class="select2 form-select" id="parent_id" name="parent_id">
                <option value="0">-' . lang('None') . '-</option>
                ' . get_product_group_options((int) $group['parent_id'], 0, (int) $group['id'], 0, array(), FALSE) . '
            </select>
        </div>';
}

print
pg_page_shell(array(
    'title'         => lang('Variant Set'),
    'extra classes' => 'products',
    'icon'          => 'store',
    'heading'       => lang('Variant Set'),
    'cancel'        => array('enable' => 'true', 'url' => 'view_products2.php'),
    'breadcrumb'    => array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products2.php'),
        array('label' => $group['name'])),
)) .
get_wysiwyg_editor_code(array('full_description', 'details')) . '

<div class="row">
    <div class="col-12">

        ' . $liveform->output_errors() . '
        ' . $liveform->get_warnings() . '
        ' . $liveform->output_notices() . '

        <div class="row mb-2 flex-wrap">
            <div class="col-12 text-center text-md-start">
                <h2 class="d-inline-block text-break">' . h($group['name']) . '</h2>
                <div class="text-muted">
                    ' . lang('Product Attributes') . ': ' . $output_set_attributes . '
                    <span class="ms-2 badge text-bg-primary">' .
                        lang(array(
                            'string' => '{var:1} variant{suffix:1}',
                            'vars'   => array(count($products)),
                            'suffix' => (count($products) === 1) ? '' : 's')) .
                    '</span>
                </div>
            </div>
        </div>

        <form name="form" id="pg_pb_edit_form" action="edit_product2.php" method="post">
            ' . get_token_field() . '
            <input type="hidden" name="group_id" value="' . (int) $group_id . '" />

            <div class="row">

                <!-- ------------------------------------------------- set -->
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('New Product Group Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 col-lg-6 my-2">
                                    <label for="name" class="form-label">*' . lang('Product Group Name') . '</label>
                                    <input type="text" name="name" id="name" class="form-control" value="' . h($group['name']) . '" required />
                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                </div>

                                ' . $output_parent_field . '

                                <div class="col-12 col-lg-6 my-2">
                                    <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                    <input type="text" name="short_description" id="short_description" class="form-control" maxlength="100" value="' . h($group['short_description']) . '" />
                                </div>

                                <div class="col-12 col-lg-6 my-2 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"' . ($group['enabled'] ? ' checked="checked"' : '') . ' />
                                        <label class="form-check-label" for="enabled">' . lang('Enabled') . '</label>
                                    </div>
                                </div>

                                <div class="col-12 my-2">
                                    <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                    <textarea id="full_description" name="full_description">' . h($group['full_description']) . '</textarea>
                                </div>

                                <div class="col-12 my-2">
                                    <label for="details" class="form-label">' . lang('Details') . '</label>
                                    <textarea id="details" name="details">' . h($group['details']) . '</textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ---------------------------------------------- images -->
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Image Options') . '
                        </div>
                        <div class="card-body">
                            ' . pg_pb_render_image_picker($images) . '
                        </div>
                    </div>
                </div>

                <!-- -------------------------------------------- variants -->
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Variants') . '
                        </div>
                        <div class="card-body">
                            ' . $output_variant_rows . '
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------------- seo -->
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('SEO') . '
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 col-lg-6 my-2">
                                    <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                    <input type="text" name="title" id="title" class="form-control" value="' . h($group['title']) . '" />
                                </div>
                                <div class="col-12 col-lg-6 my-2">
                                    <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                    <input type="text" name="keywords" id="keywords" class="form-control" maxlength="255" value="' . h($group['keywords']) . '" />
                                </div>
                                <div class="col-12 my-2">
                                    <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                    <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255">' . h($group['meta_description']) . '</textarea>
                                </div>
                                <div class="col-12 col-lg-6 my-2">
                                    <label class="form-label">' . lang('Catalog Name') . '</label>
                                    <input type="text" class="form-control" value="' . h($group['address_name']) . '" readonly />
                                    <div class="form-text">' . lang('Changing this would break links that already point at the product.') . '</div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="' . lang('Action') . '">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="submit" name="submit_update" value="Update" class="btn my-1 btn-success" data-loading-content="' . lang('Updating') . '">
                            <i class="bi bi-check-circle me-2"></i><span class="btn-text">' . lang('Update') . '</span>
                        </button>
                    </div>
                </div>
            </nav>

        </form>
    </div>
</div>
</main>' .
output_footer();
