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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_attribute_form.php');
$liveform = new liveform('add_product_attribute');

// If the form has not been submitted, then output it.
if (!$_POST) {
    echo
    pg_page_shell([
        'title'=> lang('Create Product Attribute'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Product Attribute'),
        'heading_description' => lang('Create a new product attribute.'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_attributes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Attributes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_attributes.php'), array('label' => lang('Create Product Attribute'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form  method="post" class="product_attribute_form">
                    ' . get_token_field() . '
                    ' . pg_product_attribute_form_cards($liveform) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_create" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Name')) )) );

    $options = pg_product_attribute_form_check($liveform, null);
    
    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_product_attribute.php');
    }
    
    db(
        "INSERT INTO product_attributes (
            name,
            label,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('label')) . "',
            '" . USER_ID . "',
            UNIX_TIMESTAMP(),
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    $id = mysqli_insert_id(db::$con);

    $sort_order = 0;

    foreach ($options as $option) {
        $sort_order++;

        db(
            "INSERT INTO product_attribute_options (
                product_attribute_id,
                label,
                no_value,
                sort_order)
            VALUES (
                '$id',
                '" . escape($option['label']) . "',
                '" . escape($option['no_value']) . "',
                '$sort_order')");
    }
    
    log_activity(lang(array('string'=>'product attribute ({var:1}) was created','vars'=>array($liveform->get_field_value('name')) )), $_SESSION['sessionusername']);
    
    $liveform_view_product_attributes = new liveform('view_product_attributes');
    $liveform_view_product_attributes->add_notice(lang('The product attribute has been created.'));

    $liveform->remove_form();

    go(PATH . SOFTWARE_DIRECTORY . '/view_product_attributes.php');
}
?>