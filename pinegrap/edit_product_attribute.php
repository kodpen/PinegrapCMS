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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM product_attributes WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_attribute_form.php');
$liveform = new liveform('edit_product_attribute');

$product_attribute = db_item(
    "SELECT
        name,
        label
    FROM product_attributes
    WHERE id = '" . escape($_REQUEST['id']) . "'");

$options = db_items(
    "SELECT
        id,
        label,
        no_value
    FROM product_attribute_options
    WHERE product_attribute_id = '" . escape($_REQUEST['id']) . "'
    ORDER BY sort_order");

// If the form has not just been submitted, then output form.
if (!$_POST) {
    // If the form has not been submitted yet, then pre-populate fields with data.
    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('name', $product_attribute['name']);
        $liveform->assign_field_value('label', $product_attribute['label']);
        $liveform->assign_field_value('options', encode_json($options));
    }

    echo
    pg_page_shell([
        'title'=> lang('Edit Product Attribute'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Product Attribute'),
        'heading_description' => lang('Update the name, label, and options for this product attribute.'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_attributes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Attributes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_attributes.php'), array('label' => lang('Edit Product Attribute'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form  method="post" class="product_attribute_form">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id', 'value' => $_GET['id'])) . '
                    ' . pg_product_attribute_form_cards($liveform) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Create" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('product attribute')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
        output_footer();
    
    $liveform->remove_form();

// Otherwise the form has been submitted, so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // If the user selected to delete this product attribute, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        db("DELETE FROM product_attributes WHERE id = '" . escape($liveform->get_field_value('id')) . "'");
        db("DELETE FROM product_attribute_options WHERE product_attribute_id = '" . escape($liveform->get_field_value('id')) . "'");
        db("DELETE FROM products_attributes_xref WHERE attribute_id = '" . escape($liveform->get_field_value('id')) . "'");
        db("DELETE FROM product_groups_attributes_xref WHERE attribute_id = '" . escape($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string' => '{var:1} ({var:2}) was deleted', 'vars' => array(lang('product attribute'), $product_attribute['name']))), $_SESSION['sessionusername']);
        
        $liveform_view_product_attributes = new liveform('view_product_attributes');
        $liveform_view_product_attributes->add_notice(lang('The product attribute has been deleted.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
        
    // Otherwise the user selected to save the product attribute, so save it.
    } else {
        $liveform->validate_required_field('name', lang('Name is required.'));

        $options = pg_product_attribute_form_check($liveform, (string) $liveform->get_field_value('id'));
        
        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }
        
        db(
            "UPDATE product_attributes
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                label = '" . escape($liveform->get_field_value('label')) . "',
                last_modified_user_id = '" . USER_ID . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        // Loop through the options in order to update or add new ones.

        $sort_order = 0;
        $sql_delete_exception = "";

        foreach ($options as $option) {
            $sort_order++;

            // If there is an id for an existing option, then update option.
            if ($option['id']) {
                db(
                    "UPDATE product_attribute_options
                    SET
                        label = '" . e($option['label']) . "',
                        no_value = '" . e($option['no_value']) . "',
                        sort_order = '$sort_order'
                    WHERE id = '" . e($option['id']) . "'");

                $id = $option['id'];

            // Otherwise there is not an id, so create new option.
            } else {
                db(
                    "INSERT INTO product_attribute_options (
                        product_attribute_id,
                        label,
                        no_value,
                        sort_order)
                    VALUES (
                        '" . e($liveform->get_field_value('id')) . "',
                        '" . e($option['label']) . "',
                        '" . e($option['no_value']) . "',
                        '$sort_order')");

                $id = mysqli_insert_id(db::$con);
            }

            // Update delete exception so that later we don't delete this option.
            $sql_delete_exception .= " AND (id != '" . e($id) . "')";
        }

        // Get all options that we need to delete.
        $deleted_options = db_items(
            "SELECT id
            FROM product_attribute_options
            WHERE
                (product_attribute_id = '" . e($liveform->get_field_value('id')) . "')
                $sql_delete_exception");

        // Loop through all options that need to be deleted in order to delete them.
        foreach ($deleted_options as $option) {
            // Delete option.
            db("DELETE FROM product_attribute_options WHERE id = '" . $option['id'] . "'");

            // Delete product associations with this option.
            db("DELETE FROM products_attributes_xref WHERE option_id = '" . $option['id'] . "'");
        }
        
        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('product attribute'), $product_attribute['name']))), $_SESSION['sessionusername']);
        
        $liveform_view_product_attributes = new liveform('view_product_attributes');
        $liveform_view_product_attributes->add_notice(lang('The product attribute has been saved.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
    }
}
?>