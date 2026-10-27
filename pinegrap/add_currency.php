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
            'heading_description' => lang('Add a currency conversion that is selectable by customers.'),
            'cancel'=>array('enable'=>'true','url'=>'view_currencies.php')
        ,
            'breadcrumb' => array(array('label' => lang('Currencies'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_currencies.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))))),
        )
    )  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_currency.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Currency'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'code', 'id'=>'code', 'class'=>'form-control', 'maxlength'=>'3', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="symbol" class="form-label">' . lang('Symbol') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'symbol', 'id'=>'symbol', 'class'=>'form-control', 'maxlength'=>'10')) . '
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'exchange_rate', 'id'=>'exchange_rate', 'class'=>'form-control', 'maxlength'=>'12','inputmode'=>'decimal','style'=>'text-align: right;') + pg_money_mask_attributes(null, 5)) . '
                                        </div>

                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'base', 'name'=>'base', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                <label class="" for="base">' . lang('Base') . '</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
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
        WHERE (code = ?)";
    $result = db_q($query, array((string) $liveform->get_field_value('code'))) or output_error('Query failed.');
    
    // if code is already in use by a different currency, prepare error and forward user back to screen
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('code', lang('The code that you entered is already in use by another currency, please enter a different code.'));
        
        // forward user to add currency screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_currency.php');
        exit();
    }

    // The mask posts the rate in the panel language (1.234,56 or 1,234.56).
    $exchange_rate = pg_normalize_amount($liveform->get_field_value('exchange_rate'));

    // Five decimals, the column's scale and what update_exchange_rates.php
    // writes; a plain float cast would write a small rate as "6.0E-5".
    if (is_numeric($exchange_rate)) {
        $exchange_rate = number_format((float) $exchange_rate, 5, '.', '');
    }

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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('base'),
        (string) $liveform->get_field_value('code'),
        (string) $liveform->get_field_value('symbol'),
        (string) $exchange_rate,
        (string) $user['id'],
        (string) $user['id'])) or output_error('Query failed.');

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