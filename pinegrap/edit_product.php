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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Edit one product.
 *
 * The same screen as add_product.php, drawn from stored values instead of
 * blank ones — pg_pb_render_product_screen() is the screen, and this file owns
 * the edit half: load the product, and write it back.
 *
 * One product, never a set. A product can belong to several groups and can be
 * a variant of one while being a plain member of another, so it has to be
 * editable in its own right; and the attribute matrix is deliberately absent,
 * because a screen that edits one variant and can also add variants lets the
 * operator create a variant of a variant. Variant sets are edited on
 * edit_product_group.php. See CLAUDE.md, "Ürün Düzenleme ve Set Düzenleme
 * Ayrı Ekranlardır".
 *
 * Replaces the screen of the same name; every column the old one wrote is
 * written here, which was checked column by column rather than by eye.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

$liveform = new liveform('edit_product');


/* ------------------------------------------------------------------ *
 * Which product
 * ------------------------------------------------------------------ */

$product_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$product    = pg_pb_load_product($product_id);

if (!$product) {
    output_error(
        lang('Page not found.')
        . ' <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php">' . lang('All Products') . '</a>',
        404);
}


/* ------------------------------------------------------------------ *
 * Save
 * ------------------------------------------------------------------ */

if ($_POST) {

    validate_token_field();

    // Delete first: the rest of this block writes the product, and doing that
    // on the way to deleting it is work nobody sees and one more chance to
    // fail halfway.
    if (isset($_POST['submit_delete']) && (($_POST['submit_delete'] ?? '') === 'Delete')) {

        $deleted_name = $product['name'];

        pg_pb_delete_product($product_id);

        log_activity(
            lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($deleted_name))),
            $_SESSION['sessionusername']);

        $liveform_target = new liveform('view_products');
        $liveform_target->add_notice(
            lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($deleted_name))));

        go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
    }

    // Contact group access for contributors, and the two e-mail addresses.
    // Kept in product_builder.php so the create screen runs the same ones.
    pg_pb_validate_product_post($user, $product_id);

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';

    if ($name === '') {
        $liveform->mark_error('name', lang(array('string' => '{var:1|c} is required', 'vars' => lang('Product ID / SKU'))));
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id);
    }

    $result = pg_pb_update_product($product_id);

    log_activity(
        lang(array('string' => 'product ({var:1}) was updated', 'vars' => array($result['name']))),
        $_SESSION['sessionusername']);

    $liveform->add_notice(
        lang(array('string' => 'product ({var:1}) was updated', 'vars' => array($result['name']))));

    // Back to the list, the same place the create screen ends, unless the
    // operator asked to stay — editing a photo and a price in one sitting is
    // two visits otherwise.
    if (!empty($_POST['submit_save_and_stay'])) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id);
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}


/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

pg_pb_render_product_screen($product, array(
    'mode'       => 'edit',
    'product_id' => $product_id,
));
