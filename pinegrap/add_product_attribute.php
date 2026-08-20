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
$liveform = new liveform('add_product_attribute');

// If the form has not been submitted, then output it.
if (!$_POST) {
    $options = $liveform->get_field_value('options');

    if (($options != '') && ($options != '[]')) {
        $output_options = $options;
    } else {
        $output_options = '[{label: ""},{label: ""}]';
    }

    echo
    pg_page_shell([
        'title'=> lang('Create Product Attribute'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Product Attribute'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_attributes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Attributes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_attributes.php'), array('label' => lang('Create Product Attribute'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new product attribute.') . '" title="' . lang('Create Product Attribute') . '">[' . lang('Product Attribute') . ']</h2>
                    </div>
                </div>
                <form  method="post" class="product_attribute_form">
                    ' . get_token_field() . '
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
                                <button type="submit" id="submit_create" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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

    // If there is not already an error for the name field,
    // and that name is already in use, then output error.
    if (
        ($liveform->check_field_error('name') == false)
        && (db_value("SELECT COUNT(*) FROM product_attributes WHERE name = '" . e($liveform->get_field_value('name')) . "'") != 0)
    ) {
        $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
    }

    $options = decode_json($liveform->get_field_value('options'));

    if (count($options) == 0) {
        $liveform->mark_error('', lang('Please add an option.'));
    }
    
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