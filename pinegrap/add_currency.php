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
$liveform = new liveform('add_currency');

// if the form has not been submitted, print it out on the screen
if (!$_POST) {
    print
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))),
            'extra classes'=>'setting',
            'icon'=>'setting', 
            'heading'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))),
            'cancel'=>array('enable'=>'true','url'=>'view_currencies.php')
        ,
            'breadcrumb' => array(array('label' => lang('Currencies'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_currencies.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))))),
        )
    )  . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Add a currency conversion that is selectable by customers.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('currency'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_currency.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Currency'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-md-3 my-2">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'code', 'id'=>'code', 'class'=>'form-control', 'maxlength'=>'3', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-md-3 my-2">
                                            <label for="symbol" class="form-label">' . lang('Symbol') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'symbol', 'id'=>'symbol', 'class'=>'form-control', 'maxlength'=>'10')) . '
                                        </div>
                                        <div class="col-12 col-md-3 my-2">
                                            <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'exchange_rate', 'id'=>'exchange_rate', 'class'=>'form-control', 'maxlength'=>'11','inputmode'=>'numeric','data-inputmask-alias'=>'currency','style'=>'text-align: right;','data-inputmask-placeholder'=>'0')) . '
                                        </div>

                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'base', 'name'=>'base', 'value'=>'1', 'class'=>'form-check-input')) . '
                                                <label class="form-check-label" for="base">' . lang('Base') . '</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted, validate the information.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Name')) )));
    $liveform->validate_required_field('code', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Code')) )));
    
    // if there is an error, send the user back to the add currency screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_currency.php');
        exit();
    }
    
    // check to see if code is already in use by a different currency
    $query =
        "SELECT id
        FROM currencies
        WHERE (code = '" . escape($liveform->get_field_value('code')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if code is already in use by a different currency, prepare error and forward user back to screen
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('code', lang('The code that you entered is already in use by another currency, please enter a different code.'));
        
        // forward user to add currency screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_currency.php');
        exit();
    }

    $exchange_rate = $liveform->get_field_value('exchange_rate');

    // If this currency was set as the base currency then set exchange rate to 1.
    if ($liveform->get_field_value('base') == 1) {
        $exchange_rate = 1;
    }
    
    // insert currency information into the database.
    $query =
        "INSERT INTO currencies(
            name,
            base,
            code,
            symbol,
            exchange_rate,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('base')) . "',
            '" . escape($liveform->get_field_value('code')) . "',
            '" . escape($liveform->get_field_value('symbol')) . "',
            '" . escape($exchange_rate) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $currency_id = mysqli_insert_id(db::$con);

    // If this currency was set as the base currency then update all other currencies
    // in order to make sure that none are set as the base currency.
    if ($liveform->get_field_value('base') == 1) {
        db(
            "UPDATE currencies
            SET base = '0'
            WHERE
                (base = '1')
                AND (id != '" . $currency_id . "')");
    }

    // Log that the currency has been created.
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('Currency'),$liveform->get_field_value('name') ))), $_SESSION['sessionusername']);
    // Add a notice that the currency has been created, then send the user to the view currencies page.
    $liveform_view_currencies = new liveform('view_currencies');
    $liveform_view_currencies->add_notice(lang('The currency has been created.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_currencies.php');
    
    $liveform->remove_form();
}
?>