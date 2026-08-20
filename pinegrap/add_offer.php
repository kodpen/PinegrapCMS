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

// get all offer actions (we will use this in several places below)
$query =
    "SELECT
        id,
        name
    FROM offer_actions
    ORDER BY name ASC";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$offer_actions = mysqli_fetch_items($result);

if (!$_POST) {
    $output_offer_actions = '';
    
    // loop through actions in order to output check boxes for each one
    foreach ($offer_actions as $offer_action) {
        $output_offer_actions .= '<div class="form-check"><input type="checkbox" id="offer_action_' . $offer_action['id'] . '" name="offer_action_' . $offer_action['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="offer_action_' . $offer_action['id'] . '">' . h($offer_action['name']) . '</label></div>';
    }

    if (DATE_FORMAT == 'month_day') {
        $output_start_date = date('n/j/Y');
        $output_end_date = '12/31/2099';
    } else {
        $output_start_date = date('j/n/Y');
        $output_end_date = '31/12/2099';
    }
    
    $output =
    pg_page_shell([
        'title'=> lang('Create Offer'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Offer'),
        'cancel'=>array('enable'=>'true','url'=>'view_offers.php'),
        'breadcrumb' => array(
            array('label' => lang('All Offers'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_offers.php'),
            array('label' => lang('Create Offer')),
        ),
    ]) . get_date_picker_format() . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new offer to calculate discounts based on a promotion code entered or a product combination found during checkout.') . '" title="' . lang('Create Offer') . '">[' . lang('New Offer') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_offer.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Offer Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="code" class="form-label">' . lang('Offer Code') . '</label>
                                            <input type="text" name="code" id="code" class="form-control add-header-content-updater" maxlength="50" />
                                            <div class="form-text text-end">' . lang('New Offer Code for Redemption & Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-8 my-2">
                                            <label for="description" class="form-label">' . lang('Message') . '</label>
                                            <input type="text" name="description" placeholder="' . lang('Offer Name') . '" maxlength="255" id="description" class="form-control " />
                                            <div class="form-text text-end">' . lang('Description to appear on Commerce pages') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" checked="checked" />
                                                <label class="form-check-label" for="status">' . lang('Enabled this offer') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>      
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Offer Terms & Conditions') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-6 col-lg-6 my-2">
                                            <div class="row">
                                                <div class="col-12 col-sm-12 col-lg-8 ">
                                                    <label for="offer_rule_id" class="form-label">' . lang('Offer Rule') . '</label>
                                                    <select name="offer_rule_id" id="offer_rule_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('offer rule')) )) . '-</option>' .  select_offer_rule() . '</select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2 collapse show" id="common_regions_access_row">
                                            <h5>' . lang('Offer Actions') . '</h5>
                                            <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                <div class="card-header border-0 bg-reset">
                                                    <div class="form-check form-switch">
                                                        <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                        <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                                                    </div>
                                                </div>
                                                <div class="card-body overflow-auto" style="max-height:300px">
                                                    ' . $output_offer_actions . '
                                                </div>
                                            </div>
                                            <div class="form-check my-2 form-switch">
                                                <input class="form-check-input" type="checkbox" id="require_code" name="require_code" value="1" checked="checked" />
                                                <label class="form-check-label" for="require_code">' . lang('Require Code') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Advanced Offer Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input collapse-switcher" type="checkbox" id="upsell" name="upsell" value="1" data-bs-target="#upsell_message_row" />
                                                <label class="form-check-label" for="upsell">' . lang('Display Up-sell Message') . '</label>
                                            </div>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="upsell_message_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                    <div class="col-12 col-md-6 col-lg-4 my-1">
                                                        <label class="form-label" for="upsell_message">'. lang('Up-sell Message') . '</label>
                                                        <input type="text" id="upsell_message" name="upsell_message" class="form-control" maxlength="255" >
                                                    </div>
                                                    <div class="col-12 my-1">
                                                        <label class="form-label">' . lang('Triggers') . '</label>
                                                        <div class="input-group number-controls">
                                                            <label for="upsell_trigger_subtotal" class="input-group-text">' . lang('Subtotal within of required subtotal.') . '</label>
                                                            <input type="text" name="upsell_trigger_subtotal" id="upsell_trigger_subtotal" class="form-control number-controls-disabled" style="min-width:100px" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                            <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                            <span class="input-group-text">' . lang('and/or') . '</span>
                                                            <label for="upsell_trigger_quantity" class="input-group-text">' . lang('Quantity within of required quantity.') . '</label>
                                                            <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                            <input class="form-control text-center border-start-0 border-end-0" type="text" name="upsell_trigger_quantity" id="upsell_trigger_quantity" style="min-width:100px" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                            <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 my-1">
                                                        <label class="form-label" for="upsell_action_button_label">'. lang('Action Button Label') . '</label>
                                                        <input type="text" id="upsell_action_button_label" name="upsell_action_button_label" class="form-control" maxlength="50" >
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 my-1">
                                                        <label for="upsell_action_page_id" class="form-label">' . lang('Action Page') . '</label>
                                                        <select name="upsell_action_page_id" id="upsell_action_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' .  select_page() . '</select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <label class="form-label" for="">'. lang('Scope') . '</label>
                                            <div class="form-check">
                                                <input value="order" class="form-check-input collapse-switcher" type="radio" id="order" name="scope" checked>
                                                <label class="form-check-label" for="order">'. lang('Order') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="recipient" class="form-check-input collapse-switcher" type="radio" id="recipient" name="scope" data-bs-target="#multiple_recipients_row">
                                                <label class="form-check-label" for="recipient">'. lang('Recipient') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="multiple_recipients_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="multiple_recipients" name="multiple_recipients" value="1" />
                                                                <label class="form-check-label" for="multiple_recipients">' . lang('Allow offer to be applied to multiple recipients') . '</label>
                                                                <div class="form-text">' . lang('only used if offer action adds a product') . '</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-8 my-3">
                                            <div class="row">
                                                <div class="col-12 col">
                                                    <h5 class="text-muted">' . lang('Offer Availability') . '</h5>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label for="start_date" class="form-label">' . lang('Start Date') . '</label>
                                                    <input type="text" name="start_date" id="start_date" class="form-control" value="' . $output_start_date . '" maxlength="10" autocomplete="off"/>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label for="end_date" class="form-label">' . lang('End Date') . '</label>
                                                    <input type="text" name="end_date" id="end_date" class="form-control " value="' . $output_end_date . '" maxlength="10" autocomplete="off"/>
                                                </div>
                                            <script>
                                                $("#start_date,#end_date").datepicker(datetimepicker_options);
                                            </script>
                                        </div>

                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="only_apply_best_offer" name="only_apply_best_offer" value="1" checked="checked" />
                                                <label class="form-check-label" for="only_apply_best_offer">' . lang('Only Apply Best Offer') . '</label>
                                                <div class="form-text">' . lang('For Offers that Share this Offer\'s Code') . '</div>
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

    print $output;

} else {
    validate_token_field();
    
    if($_POST['status'] == 1){
        $status = 'enabled';
    }else{
        $status = 'disabled';
    }
    
    if($_POST['upsell_trigger_subtotal']){
        // remove commas and spaces from price
        $price = str_replace(',', '', $_POST['upsell_trigger_subtotal']);
        $price = str_replace(' ', '',$price); 
        // convert price from dollars to cents
        $upsell_trigger_subtotal = $price * 100;
    }else{
        $upsell_trigger_subtotal = 0;
    }

    // If the offer code is blank, then output error.
    if (trim($_POST['code']) == '') {
        output_error(lang('Please enter an offer code') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // create offer
    $query = "INSERT INTO offers (
                code,
                description,
                require_code,
                status,
                start_date,
                end_date,
                offer_rule_id,
                upsell,
                upsell_message,
                upsell_trigger_subtotal,
                upsell_trigger_quantity,
                upsell_action_button_label,
                upsell_action_page_id,
                scope,
                multiple_recipients,
                only_apply_best_offer,
                user,
                timestamp)
            VALUES (
                '" . escape($_POST['code'] ?? '') . "',
                '" . escape($_POST['description'] ?? '') . "',
                '" . escape($_POST['require_code'] ?? '') . "',
                '" . escape($status) . "',
                '" . escape(prepare_form_data_for_input($_POST['start_date'], 'date')) . "',
                '" . escape(prepare_form_data_for_input($_POST['end_date'], 'date')) . "',
                '" . escape($_POST['offer_rule_id'] ?? '') . "',
                '" . escape($_POST['upsell'] ?? '') . "',
                '" . escape($_POST['upsell_message'] ?? '') . "',
                '" . escape($upsell_trigger_subtotal) . "',
                '" . escape($_POST['upsell_trigger_quantity'] ?? '') . "',
                '" . escape($_POST['upsell_action_button_label'] ?? '') . "',
                '" . escape($_POST['upsell_action_page_id'] ?? '') . "',
                '" . escape($_POST['scope'] ?? '') . "',
                '" . escape($_POST['multiple_recipients'] ?? '') . "',
                '" . escape($_POST['only_apply_best_offer'] ?? '') . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $offer_id = mysqli_insert_id(db::$con);
    
    // loop through actions in order to add records to database for selected actions
    foreach ($offer_actions as $offer_action) {
        // if the action was checked, then insert record
        if (($_POST['offer_action_' . $offer_action['id']] ?? '') == 1) {
            $query =
                "INSERT INTO offers_offer_actions_xref (
                    offer_id,
                    offer_action_id)
                VALUES (
                    '" . $offer_id . "',
                    '" . $offer_action['id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    log_activity(lang(array('string'=>'offer ({var:1}) was created','vars'=>$_POST['code'])), $_SESSION['sessionusername']);

    // forward user to view offers screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_offers.php');
}
?>