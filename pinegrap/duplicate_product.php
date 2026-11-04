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

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

validate_token_field();

// A copy needs an existing product. An id that is missing, unknown or sent as
// an array (?id[]=x) stops on "not found": the copy was made from an empty row,
// or escape() failed on the array with an error page.
$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM products WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// A product in the Recycle Bin is not copied: the copy came out as a live
// product, in the binned one's groups, made from one the operator threw away.
if (pg_catalog_product_binned($id)) {
    output_error(lang('This product is in the Recycle Bin. Restore it from the Recycle Bin in the Catalog Manager before changing it.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('product_builder.php');

$new_product_id = duplicate_product($_GET['id']);

// A copy of a variant set member leaves the set: the set holds one member per
// attribute combination, and the copy carries the same combination, so the
// storefront picker could not tell the two apart. Only the links to variant
// sets (display_type 'select') go; the attribute options and the browsable
// group links stay, and the set screen adds the copy back when wanted.
// duplicate_product() keeps every link, because Duplicate Product Group
// relinks the copies it makes into the copied set.
if (pg_pb_variant_group_id($id) && $new_product_id) {
    db(
        "DELETE FROM products_groups_xref
        WHERE product = '" . e((int) $new_product_id) . "'
            AND product_group IN (SELECT id FROM product_groups WHERE display_type = 'select')");
}

$old_product_name = db_value("SELECT name FROM products WHERE id = '" . escape($_GET['id']) . "'");

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('product'), $old_product_name) )), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform_edit_product = new liveform('edit_product');
$liveform_edit_product->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('product') )) );

go(PATH . SOFTWARE_DIRECTORY . '/edit_product.php?id=' . $new_product_id);
?>