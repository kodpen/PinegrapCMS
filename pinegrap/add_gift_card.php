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

// Increase execution time because this script can be used to create many gift cards.
ini_set('max_execution_time', '9999');

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
$liveform = new liveform('add_gift_card');

// If the form has not been submitted, then output it.
if (!$_POST) {
    // If the form has not been submitted yet, then prefill default value.
    if ($liveform->field_in_session('amount') == false) {
        // Prefill the expiration date with the number of validity days set
        // in the settings, if any.
        $expiration_date = pg_gift_card_form_default_expiration(ECOMMERCE_GIFT_CARD_VALIDITY_DAYS, DATE_FORMAT, time());

        if ($expiration_date !== '') {
            $liveform->assign_field_value('expiration_date', $expiration_date);
        }

        $liveform->set('limit', isset($_GET['limit']) ? $_GET['limit'] : '');

    }

    // If the user has disabled the quantity limit via the query string, then deal with that.
    // This is a feature for certain sites that need to create large batches of gift cards at once.
    if ($liveform->get('limit') == 'false') {
        $quantity_max = '';

    // Otherwise the user has not disabled the quantity limit,
    // so set the default quantity limit to 1,000.
    } else {
        $quantity_max = '1000';
    }

    echo
    pg_page_shell([
        'title'=> lang('Create Gift Card'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Gift Card'),
        'heading_description' => lang('Create one or more new gift cards by entering an amount.  The code will be generated for you.'),
        'cancel'=>array('enable'=>'true','url'=>'view_gift_cards.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Gift Cards'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_gift_cards.php'), array('label' => lang('Create Gift Card'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_gift_card.php" method="post" >
                    ' . get_token_field() . '
                    ' . $liveform->field(array(
                        'type' => 'hidden',
                        'name' => 'limit')) . '
                    ' . pg_gift_card_form_card($liveform, 'add', array(
                        'symbol' => BASE_CURRENCY_SYMBOL,
                        'scripts' => get_date_picker_format() . pg_fields_script(),
                        'quantity_max' => $quantity_max)) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
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

// Otherwise the form has been submitted so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    $expiration_date = $liveform->get_field_value('expiration_date');
    $notes = $liveform->get_field_value('notes');
    $quantity = $liveform->get_field_value('quantity');

    $amount = pg_gift_card_form_check($liveform, 'add');

    $liveform->validate_required_field('quantity', lang(array('string'=>'{var:1} is required','vars'=>lang('Quantity'))) );

    // Remove commas from quantity.
    $quantity = str_replace(',', '', $quantity);

    // If there is not already an error for the quantity field,
    // and value is not a number greater than 0, then add error.
    if (
        ($liveform->check_field_error('quantity') == false)
        &&
        (
            (is_numeric($quantity) == false)
            || ($quantity <= 0)
        )
    ) {
        $liveform->mark_error('quantity', lang('Please enter a valid quantity.'));
    }

    // If there is not already an error for the quantity field,
    // and the quantity is too high, and the limit has not been disabled
    // via the query string, then add error.
    if (
        ($liveform->check_field_error('quantity') == false)
        and ($quantity > 1000)
        and ($liveform->get('limit') != 'false')
    ) {
        $liveform->mark_error('quantity', lang('Sorry, the maximum quantity is 1,000.'));
    }
    
    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_gift_card.php');
    }

    // Convert amount into cents.
    $amount = (int) round($amount * 100);

    // Create a gift card for each quantity.
    for ($i = 1; $i <= $quantity; $i++) { 
        $code = generate_gift_card_code();

        db(
            "INSERT INTO gift_cards (
                code,
                amount,
                balance,
                notes,
                expiration_date,
                created_user_id,
                created_timestamp,
                last_modified_user_id,
                last_modified_timestamp)
            VALUES (
                '" . $code . "',
                '" . e($amount) . "',
                '" . e($amount) . "',
                '" . e($notes) . "',
                '" . e(prepare_form_data_for_input($expiration_date, 'date')) . "',
                '" . USER_ID . "',
                UNIX_TIMESTAMP(),
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");
    }
    $liveform->remove_form();
    $liveform_view_gift_cards = new liveform('view_gift_cards');

    // If one gift card was created, then prepare log and notice for that situation.
    if ($quantity == 1) {
        log_activity(lang(array('string'=>'gift card ({var:1}) was created','vars'=>output_gift_card_code($code))), $_SESSION['sessionusername']);
        
        $liveform_view_gift_cards->add_notice(lang(array('string'=>'The gift card has been created. You may now give the code ({var:1}) to the customer.','vars'=>output_gift_card_code($code) )) );
        
    // Otherwise more than 1 gift card was created, so prepare log and notice for that situation.
    } else {
        log_activity(lang(array('string'=>'{var:1} gift cards were created','vars'=>pg_format_number($quantity, 0))), $_SESSION['sessionusername']);
        
        $liveform_view_gift_cards->add_notice(lang('The gift cards have been created.'));
    }

    

    go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
}