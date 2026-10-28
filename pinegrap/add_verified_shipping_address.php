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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/verified_shipping_address_form.php');
$liveform = new liveform('add_verified_shipping_address');

// if the form has not been submitted
if (!$_POST) {

    // pre-select state from URL parameter if available and not already in session
    if (!$liveform->field_in_session('state_id') && isset($_GET['state_id'])) {
        $liveform->assign_field_value('state_id', $_GET['state_id']);
    }

    print
        pg_page_shell([
            'title'=> lang('Create Verified Shipping Address'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Verified Shipping Address'),
            'heading_description' => lang('Create a new verified shipping address and assign it to any existing state.'),
            'cancel'=>array('enable'=>'true','url'=>'view_verified_shipping_addresses.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Verified Shipping Addresses'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php'), array('label' => lang('Create Verified Shipping Address'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form action="add_verified_shipping_address.php" method="post">
                        ' . get_token_field() . '
                        ' . pg_verified_shipping_address_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang('Create') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    $liveform->remove_form();

} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    pg_verified_shipping_address_form_validate($liveform);

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_verified_shipping_address.php');
    }

    $values = pg_verified_shipping_address_form_values($liveform);

    db(
        "INSERT INTO verified_shipping_addresses (
            " . implode(",\n            ", array_keys($values)) . ",
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . implode("',\n            '", array_map('escape', $values)) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())");

    $name = pg_verified_shipping_address_form_name($values['company'], $values['address_1']);

    log_activity(lang(array('string' => 'verified shipping address ({var:1}) was created', 'vars' => array($name))), $_SESSION['sessionusername']);

    $liveform_view = new liveform('view_verified_shipping_addresses');
    $liveform_view->add_notice(lang('The verified shipping address has been created.'));

    $liveform->remove_form();

    go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');
}
