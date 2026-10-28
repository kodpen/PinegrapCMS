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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');
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
                    ' . pg_currency_form_card($liveform) . '
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
    
    // if code is already in use by a different currency, prepare error and forward user back to screen
    if (pg_currency_form_code_in_use($liveform->get_field_value('code'), null)) {
        $liveform->mark_error('code', lang('The code that you entered is already in use by another currency, please enter a different code.'));
        
        // forward user to add currency screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_currency.php');
        exit();
    }

    $exchange_rate = pg_currency_form_exchange_rate($liveform->get_field_value('exchange_rate'), $liveform->get_field_value('base'));
    
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
        pg_currency_form_clear_other_bases($currency_id);
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