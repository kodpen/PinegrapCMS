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
 *              2017–2026 Kodpen
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
 * edit_product_group.php; product editing and set editing are deliberately
 * separate screens.
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

// Where to land after saving or deleting: the screen that opened this one when
// it said so, the product list otherwise. Read before the POST branch so that
// both halves of the file agree, and re-read from the posted value because a
// save is a fresh request with no query string on it.
$send_to = pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/view_products.php');

// Whether that is the product list. Only then is the save's notice queued for
// it: a notice parked for a screen the save does not land on waits in the
// session and turns up on some later visit, long after the save it was about.
// A site page that opened this editor inline draws no panel notices at all.
$send_to_list = (basename((string) parse_url($send_to, PHP_URL_PATH)) === 'view_products.php');

if (!$product) {
    output_error(
        lang('Page not found.')
        . ' <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php">' . lang('All Products') . '</a>',
        404);
}

// A product in the Recycle Bin is no longer on any list, but its address
// still opens it. It is shown so the operator can see what is in the bin, and
// is not written: saving would switch it back on while it is in the bin (the
// bin keeps it off), and deleting it again would only restart its countdown.
// Restoring it in the Catalog Manager is the way back to editing it.
$product_binned = pg_catalog_product_binned($product_id);


/* ------------------------------------------------------------------ *
 * Save
 * ------------------------------------------------------------------ */

if ($_POST) {

    validate_token_field();

    if ($product_binned) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id . '&send_to=' . urlencode($send_to));
    }

    // Delete first: the rest of this block writes the product, and doing that
    // on the way to deleting it is work nobody sees and one more chance to
    // fail halfway.
    if (isset($_POST['submit_delete']) && (($_POST['submit_delete'] ?? '') === 'Delete')) {

        $deleted_name = $product['name'];

        // To the Recycle Bin, as the Catalog Manager deletes: a product deleted
        // by mistake has to be one restore away, not a database backup away.
        // Emptying the bin is what runs the permanent delete below.
        if (pg_catalog_bin_ready()) {

            pg_catalog_bin_product($product_id, $user['id']);

            $deleted_message = lang(array('string' => 'The product, {var:1}, was moved to the Recycle Bin.', 'vars' => h($deleted_name)));

        } else {

            pg_pb_delete_product($product_id);

            $deleted_message = lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($deleted_name)));
        }

        log_activity($deleted_message, $_SESSION['sessionusername']);

        if ($send_to_list) {
            $liveform_target = new liveform('view_products');
            $liveform_target->add_notice($deleted_message);
        }

        go($send_to);
    }

    // Contact group access for contributors, and the two e-mail addresses.
    // Kept in product_builder.php so the create screen runs the same ones.
    pg_pb_validate_product_post($user, $product_id);

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';

    if ($name === '') {
        $liveform->mark_error('name', lang(array('string' => '{var:1|c} is required', 'vars' => lang('Product ID / SKU'))));
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id . '&send_to=' . urlencode($send_to));
    }

    $result = pg_pb_update_product($product_id);

    log_activity(
        lang(array('string' => 'product ({var:1}) was updated', 'vars' => array($result['name']))),
        $_SESSION['sessionusername']);

    $notice = lang(array('string' => 'product ({var:1}) was updated', 'vars' => array($result['name'])));

    // Back to the list, the same place the create screen ends, unless the
    // operator asked to stay — editing a photo and a price in one sitting is
    // two visits otherwise. The notice goes with the operator either way.
    if (!empty($_POST['submit_save_and_stay'])) {
        $liveform->add_notice($notice);
        go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $product_id . '&send_to=' . urlencode($send_to));
    }

    if ($send_to_list) {
        $liveform_target = new liveform('view_products');
        $liveform_target->add_notice($notice);
    }

    go($send_to);
}


/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

pg_pb_render_product_screen($product, array(
    'mode'       => 'edit',
    'product_id' => $product_id,
    'recycled'   => $product_binned,
));
