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

$stored_options = db_items(
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
        $liveform->assign_field_value('options', encode_json($stored_options));
    }

    // Products and product groups using the attribute: the Summary and the
    // Delete confirmation read the same counts.
    $usage = pg_product_attribute_form_delete_counts($id);

    echo
    pg_page_shell([
        'title'=> lang('Edit Product Attribute'),
        'extra classes'=>'products pg-measure-edit',
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
                    ' . pg_product_attribute_form_layout(
                        pg_product_attribute_form_cards($liveform, $usage),
                        '
                                        <button type="submit" id="submit_save" name="submit_save" value="Create" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . h(implode(' ', array_map('lang', pg_product_attribute_form_delete_confirm(...$usage)))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>') . '
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

        // Update the options of this attribute and add the new ones. An id
        // that is not one of this attribute's options is added as a new
        // option, so a posted id never reaches another attribute's option.
        $attribute_id = (int) $liveform->get_field_value('id');
        $existing_ids = array();

        foreach ($stored_options as $stored_option) {
            $existing_ids[(int) $stored_option['id']] = true;
        }

        $sort_order = 0;
        $kept_ids = array();

        foreach ($options as $option) {
            $sort_order++;

            if ($option['id'] && isset($existing_ids[$option['id']])) {
                db_q(
                    "UPDATE product_attribute_options
                    SET
                        label = ?,
                        no_value = ?,
                        sort_order = ?
                    WHERE (id = ?) AND (product_attribute_id = ?)",
                    array($option['label'], $option['no_value'], $sort_order, $option['id'], $attribute_id));

                $kept_ids[] = (int) $option['id'];

            } else {
                db_q(
                    "INSERT INTO product_attribute_options (
                        product_attribute_id,
                        label,
                        no_value,
                        sort_order)
                    VALUES (?, ?, ?, ?)",
                    array($attribute_id, $option['label'], $option['no_value'], $sort_order));

                $kept_ids[] = (int) mysqli_insert_id(db::$con);
            }
        }

        // Remove the options that were taken off: their product links go, and
        // a product group that preselected one preselects none instead of an
        // option that is gone (as the external API does).
        $removed_ids = array();

        foreach (array_keys($existing_ids) as $existing_id) {
            if (!in_array($existing_id, $kept_ids, true)) {
                $removed_ids[] = (int) $existing_id;
            }
        }

        if ($removed_ids) {
            $in = implode(',', $removed_ids);

            db_q("DELETE FROM product_attribute_options WHERE (id IN (" . $in . ")) AND (product_attribute_id = ?)", array($attribute_id));
            db("DELETE FROM products_attributes_xref WHERE option_id IN (" . $in . ")");
            db_q("UPDATE product_groups_attributes_xref SET default_option_id = 0 WHERE (attribute_id = ?) AND (default_option_id IN (" . $in . "))", array($attribute_id));
        }

        // The log names the attribute by its new name, with the old one when
        // the name changed, so a search for either finds the change.
        $old_name = (string) $product_attribute['name'];
        $new_name = (string) $liveform->get_field_value('name');
        $log_name = ($old_name === $new_name) ? $new_name : $old_name . ' → ' . $new_name;

        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('product attribute'), $log_name))), $_SESSION['sessionusername']);
        
        $liveform_view_product_attributes = new liveform('view_product_attributes');
        $liveform_view_product_attributes->add_notice(lang('The product attribute has been saved.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
    }
}
?>