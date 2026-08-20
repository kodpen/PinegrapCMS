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

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
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

    $options = $liveform->get_field_value('options');

    if (($options != '') && ($options != '[]')) {
        $output_options = $options;
    } else {
        $output_options = '[{label: ""},{label: ""}]';
    }
    
    echo
    pg_page_shell([
        'title'=> lang('Edit Product Attribute'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Product Attribute'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_attributes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Attributes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_attributes.php'), array('label' => lang('Edit Product Attribute'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update the name, label, and options for this product attribute.') . '" title="' . lang('Edit Product Attribute') . '">[' . h($product_attribute['name']) . ']</h2>
                    </div>
                </div>
                <form  method="post" class="product_attribute_form">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id', 'value' => $_GET['id'])) . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'name' => 'name',
                                                'id' => 'name',
                                                'placeholder'=>lang('Attribute Name'),
                                                'class'=>'form-control add-header-content-updater',
                                                'maxlength' => '100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-8 my-2">
                                            <label for="label" class="form-label">' . lang('Label') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'name' => 'label',
                                                'id' => 'label',
                                                'placeholder'=>lang('Attribute Label'),
                                                'class'=>'form-control',
                                                'maxlength' => '255')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label class="form-label" >' . lang('Atribute Options') . '</label>
                                            <div class="options">
                                                <div class="option_list row" ></div>
                                                <button type="button" class="add_option btn btn-primary mt-2"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Option') . '</button>
                                            </div>
                                            <script>
                                                init_product_attribute_options({
                                                    options: ' . $output_options . ',
                                                    labels:{
                                                        "\'No Thanks\' Option":"' . lang('\'No Thanks\' Option') . '",
                                                        "Move Up":"' . lang('Move Up') . '",
                                                        "Move Down":"' . lang('Move Down') . '",
                                                        "Remove":"' . lang('Remove') . '"
                                                    }
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>         
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('product attribute')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
        
        log_activity('product attribute (' . $product_attribute['name'] . ') was deleted', $_SESSION['sessionusername']);
        
        $liveform_view_product_attributes = new liveform('view_product_attributes');
        $liveform_view_product_attributes->add_notice(lang('The product attribute has been deleted.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
        
    // Otherwise the user selected to save the product attribute, so save it.
    } else {
        $liveform->validate_required_field('name', lang('Name is required.'));

        // If there is not already an error for the name field,
        // and that name is already in use, then output error.
        if (
            ($liveform->check_field_error('name') == false)
            && (db_value("SELECT COUNT(*) FROM product_attributes WHERE (name = '" . e($liveform->get_field_value('name')) . "') AND (id != '" . e($liveform->get_field_value('id')) . "')") != 0)
        ) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }

        $options = decode_json($liveform->get_field_value('options'));

        if (count($options) == 0) {
            $liveform->mark_error('', lang('Please add an option.'));
        }
        
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
        
        log_activity('product attribute (' . $product_attribute['name'] . ') was modified', $_SESSION['sessionusername']);
        
        $liveform_view_product_attributes = new liveform('view_product_attributes');
        $liveform_view_product_attributes->add_notice(lang('The product attribute has been saved.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
    }
}
?>