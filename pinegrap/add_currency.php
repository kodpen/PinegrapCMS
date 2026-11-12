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
            'extra classes'=>'setting pg-measure-edit',
            'icon'=>'setting', 
            'heading'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))),
            'heading_description' => lang('Add a currency conversion that is selectable by customers.'),
            'cancel'=>array('enable'=>'true','url'=>'view_currencies.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Currencies'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_currencies.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Currency'))))),
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
                    ' . pg_currency_form_layout(
                        pg_currency_form_cards($liveform),
                        '
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                    ) . '
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
    $liveform->assign_field_value('code', pg_currency_form_code($liveform->get_field_value('code')));
    
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang(array('string'=>'{var:1} Name','vars'=>lang('Currency')))) )));
    $liveform->validate_required_field('code', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Code')) )));

    if (!$liveform->check_field_error('code') && (($code_error = pg_currency_form_code_error($liveform->get_field_value('code'))) !== '')) {
        $liveform->mark_error('code', $code_error);
    }
    
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

    // a non-base currency needs a rate above zero: checkout divides by it
    $exchange_rate_error = pg_currency_form_exchange_rate_error($liveform->get_field_value('exchange_rate'), $liveform->get_field_value('base'));

    if ($exchange_rate_error !== '') {
        $liveform->mark_error('exchange_rate', $exchange_rate_error);
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_currency.php');
        exit();
    }

    $exchange_rate = pg_currency_form_exchange_rate($liveform->get_field_value('exchange_rate'), $liveform->get_field_value('base'));
    
    // The shared write path (includes/fn/store_currencies.php) also keeps a
    // single base currency and announces the change.
    $currency_id = pg_store_currency_save(0, array(
        'name' => (string) $liveform->get_field_value('name'),
        'base' => (int) $liveform->get_field_value('base'),
        'code' => (string) $liveform->get_field_value('code'),
        'symbol' => (string) $liveform->get_field_value('symbol'),
        'exchange_rate' => (string) $exchange_rate,
    ), (int) $user['id']);

    if ($currency_id <= 0) {
        output_error('Query failed.');
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