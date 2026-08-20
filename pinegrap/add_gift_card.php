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

// Increase execution time because this script can be used to create many gift cards.
ini_set('max_execution_time', '9999');

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('add_gift_card');

// If the form has not been submitted, then output it.
if (!$_POST) {
    // If the form has not been submitted yet, then prefill default value.
    if ($liveform->field_in_session('amount') == false) {
        // If a number of validity days has been entered in the settings,
        // then prefill expiration date field with an appropriate date.
        if (ECOMMERCE_GIFT_CARD_VALIDITY_DAYS) {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'n/j';

            // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j/n';
            }
            
            // Set the default expiration date to the number of validity days
            // from today's date.
            $expiration_date = date($month_and_day_format . '/Y', strtotime('+' . ECOMMERCE_GIFT_CARD_VALIDITY_DAYS . ' day'));

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
        'extra_classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Gift Card'),
        'cancel'=>array('enable'=>'true','url'=>'view_gift_cards.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Gift Cards'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_gift_cards.php'), array('label' => lang('Create Gift Card'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('Create one or more new gift cards by entering an amount.  The code will be generated for you.') . '" title="' . lang('Create Gift Card') . '">[' . lang('New Gift Card') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_gift_card.php" method="post" >
                    ' . get_token_field() . '
                    ' . $liveform->field(array(
                        'type' => 'hidden',
                        'name' => 'limit')) . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Gift Card Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 col-lg-3 col-xl-2 my-2">
                                            <label for="amount" class="form-label">' . lang('Amount') . '</label>
                                            <div class="input-group">
                                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'amount', 'name' => 'amount', 'class' => 'form-control text-end', 'maxlength'=>'12', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'currency', 'data-inputmask-groupSeparator'=>',', 'data-inputmask-digits'=>'2','data-inputmask-digitsOptional'=>'false', 'data-inputmask-placeholder'=>'0', 'required'=>'required')) . '
                                                <label class="input-group-text" for="amount">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                           
                                            <script>$("#amount").focus()</script>
                                        </div>
                                        <div class="col-12 col-sm-4 col-xl-3 my-2">
                                            <label for="expiration_date" class="form-label">' . lang('Expiration Date') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'id' => 'expiration_date',
                                                'name' => 'expiration_date',
                                                'size' => '10',
                                                'maxlength' => '10',
                                                'class'=>'form-control',
                                                'autocomplete'=>'off')) . '
                                                ' . get_date_picker_format() . '
                                            <div class="form-text text-end">' . lang('leave blank for no expiration') . '</div>
                                            <script>$("#expiration_date").datepicker(datetimepicker_options);</script>
                                        </div>
                                        <div class="col-12 col-sm-4 col-xl-3 my-2">
                                            <label for="quantity" class="form-label">' . lang('Quantity') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'quantity',
                                                    'id' => 'quantity',
                                                    'value' => '1',
                                                    'min' => '1',
                                                    'max' => $quantity_max,
                                                    'class' => 'form-control text-center border-start-0 border-end-0',
                                                    'inputmode'=>'numeric',
                                                    'data-inputmask-alias'=>'decimal',
                                                    'data-inputmask-placeholder'=>'0')) . '
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                            </div>
                                            
                                            <div class="text-end form-text">' . lang('increase quantity to create multiple gift cards at once') . '</div>
                                        </div>
                                        <div class="col-12 col-xl-8 my-2">
                                            <label for="notes" class="form-label">' . lang('Notes') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'textarea',
                                                'name' => 'notes',
                                                'id' => 'notes',
                                                'class' => 'form-control')) . '
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

// Otherwise the form has been submitted so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    $amount = $liveform->get_field_value('amount');
    $expiration_date = $liveform->get_field_value('expiration_date');
    $notes = $liveform->get_field_value('notes');
    $quantity = $liveform->get_field_value('quantity');
    
    $liveform->validate_required_field('amount', lang(array('string'=>'{var:1} is required','vars'=>lang('Amount'))) );
    $liveform->validate_required_field('quantity', lang(array('string'=>'{var:1} is required','vars'=>lang('Quantity'))) );

    // Remove commas from amount and quantity.
    $amount = str_replace(',', '', $amount);
    $quantity = str_replace(',', '', $quantity);

    // If there is not already an error for the amount field,
    // and value is not a number greater than 0, then add error.
    if (
        ($liveform->check_field_error('amount') == false)
        &&
        (
            (is_numeric($amount) == false)
            || ($amount <= 0)
        )
    ) {
        $liveform->mark_error('amount', lang('Please enter a valid amount.'));
    }

    // If an expiration date was entered and it is not valid, then add error.
    if (($expiration_date != '') && (validate_date($expiration_date) == false)) {
        $liveform->mark_error('expiration_date', lang('Please enter a valid expiration date.'));
    }

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
    $amount = $amount * 100;

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
        log_activity(lang(array('string'=>'{var:1} gift cards were created','vars'=>number_format($quantity))), $_SESSION['sessionusername']);
        
        $liveform_view_gift_cards->add_notice(lang('The gift cards have been created.'));
    }

    

    go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
}