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
$liveform = new liveform('add_verified_shipping_address');

// if the form has not been submitted
if (!$_POST) {

    // pre-select state from URL parameter if available and not already in session
    if (!$liveform->field_in_session('state_id') && isset($_GET['state_id'])) {
        $liveform->assign_field_value('state_id', $_GET['state_id']);
    }

    $current_state_id = $liveform->get_field_value('state_id');
    $output_state_options = '';
    foreach (get_state_options() as $label => $value) {
        $selected = ($current_state_id !== '' && $current_state_id == $value) ? ' selected' : '';
        $output_state_options .= '<option value="' . h($value) . '"' . $selected . '>' . $label . '</option>';
    }

    print
        pg_page_shell([
            'title'=> lang('Create Verified Shipping Address'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Verified Shipping Address'),
            'cancel'=>array('enable'=>'true','url'=>'view_verified_shipping_addresses.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Verified Shipping Addresses'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php'), array('label' => lang('Create Verified Shipping Address'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new verified shipping address and assign it to any existing state.') . '" title="' . lang('Create Verified Shipping Address') . '">[' . lang('Verified Shipping Address') . ']</h2>
                        </div>
                    </div>
                    <form action="add_verified_shipping_address.php" method="post">
                        ' . get_token_field() . '
                        <div class="row">
                            <div class="col-12 col-md-8 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Verified Shipping Address') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 my-2">
                                                <label for="company" class="form-label">' . lang('Company') . '</label>
                                                <input type="text" name="company" id="company" maxlength="50" value="' . h($liveform->get_field_value('company')) . '" class="form-control' . ($liveform->check_field_error('company') ? ' is-invalid' : '') . ' add-header-content-updater" />
                                            </div>
                                            <div class="col-12 my-2">
                                                <label for="address_1" class="form-label">' . lang('Address 1') . '</label>
                                                <input type="text" name="address_1" id="address_1" maxlength="50" value="' . h($liveform->get_field_value('address_1')) . '" class="form-control' . ($liveform->check_field_error('address_1') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 my-2">
                                                <label for="address_2" class="form-label">' . lang('Address 2') . '</label>
                                                <input type="text" name="address_2" id="address_2" maxlength="50" value="' . h($liveform->get_field_value('address_2')) . '" class="form-control' . ($liveform->check_field_error('address_2') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 col-lg-6 my-2">
                                                <label for="city" class="form-label">' . lang('City') . '</label>
                                                <input type="text" name="city" id="city" maxlength="50" value="' . h($liveform->get_field_value('city')) . '" class="form-control' . ($liveform->check_field_error('city') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 col-lg-4 my-2">
                                                <label for="state_id" class="form-label">' . lang('State') . '</label>
                                                <select style="width:100%" class="select2 form-select' . ($liveform->check_field_error('state_id') ? ' is-invalid' : '') . '" data-placeholder="' . lang('Click to select state') . '" id="state_id" name="state_id">' . $output_state_options . '</select>
                                            </div>
                                            <div class="col-12 col-lg-2 my-2">
                                                <label for="zip_code" class="form-label">' . lang('Zip Code') . '</label>
                                                <input type="text" name="zip_code" id="zip_code" maxlength="50" value="' . h($liveform->get_field_value('zip_code')) . '" class="form-control' . ($liveform->check_field_error('zip_code') ? ' is-invalid' : '') . '" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang('Creating') . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang('Create') . '</span></button>
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

    $liveform->validate_required_field('address_1', lang('Address 1 is required.'));
    $liveform->validate_required_field('city', lang('City is required.'));
    $liveform->validate_required_field('state_id', lang('State is required.'));
    $liveform->validate_required_field('zip_code', lang('Zip Code is required.'));

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_verified_shipping_address.php');
    }

    db(
        "INSERT INTO verified_shipping_addresses (
            company,
            address_1,
            address_2,
            city,
            state_id,
            zip_code,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('company')) . "',
            '" . escape($liveform->get_field_value('address_1')) . "',
            '" . escape($liveform->get_field_value('address_2')) . "',
            '" . escape($liveform->get_field_value('city')) . "',
            '" . escape($liveform->get_field_value('state_id')) . "',
            '" . escape($liveform->get_field_value('zip_code')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())");

    $name = $liveform->get_field_value('company') != '' ? $liveform->get_field_value('company') : $liveform->get_field_value('address_1');

    log_activity(lang(array('string' => 'verified shipping address ({var:1}) was created', 'vars' => array($name))), $_SESSION['sessionusername']);

    $liveform_view = new liveform('view_verified_shipping_addresses');
    $liveform_view->add_notice(lang('The verified shipping address has been created.'));

    $liveform->remove_form();

    go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');
}
