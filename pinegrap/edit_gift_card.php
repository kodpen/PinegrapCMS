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
$liveform = new liveform('edit_gift_card');

$gift_card = db_item(
    "SELECT
        gift_cards.id,
        gift_cards.code,
        gift_cards.amount,
        gift_cards.balance,
        gift_cards.expiration_date,
        gift_cards.notes,
        gift_cards.order_id,
        orders.order_number,
        gift_cards.from_name,
        user.user_id,
        contacts.id AS contact_id,
        gift_cards.recipient_email_address,
        gift_cards.message,
        gift_cards.delivery_date
    FROM gift_cards
    LEFT JOIN orders ON gift_cards.order_id = orders.id
    LEFT JOIN user ON orders.user_id = user.user_id
    LEFT JOIN contacts ON orders.contact_id = contacts.id
    WHERE gift_cards.id = '" . e($_REQUEST['id']) . "'");

// If the form has not just been submitted, then output form.
if (!$_POST) {
    $request_uri = get_request_uri();

    // If the form has not been submitted yet, then pre-populate fields with data.
    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('balance', number_format($gift_card['balance'] / 100, 2));

        if ($gift_card['expiration_date'] != '0000-00-00') {
            $liveform->assign_field_value('expiration_date', prepare_form_data_for_output($gift_card['expiration_date'], 'date'));
        }

        $liveform->assign_field_value('notes', $gift_card['notes']);
    }

    // If this gift card has a balance and has not expired,
    // then use class that shows green color.
    if (
        ($gift_card['balance'])
        &&
        (
            ($gift_card['expiration_date'] == '0000-00-00')
            || ($gift_card['expiration_date'] >= date('Y-m-d'))
        )
    ) {
        $output_status_class = 'status_enabled text-success';
    
    // Otherwise this gift card has expired, so use class that shows red color.
    } else {
        $output_status_class = 'status_disabled text-danger';
    }

    $output_order_info_rows = '';

    // If this gift card was created from an order, then output order info.
    if ($gift_card['order_id']) {
        $output_order_number = '';

        // If the order still exists (i.e. has not been deleted),
        // then output order number with link.
        if ($gift_card['order_number']) {
            $output_order_number = '<a href="view_order.php?id=' . $gift_card['order_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . h($gift_card['order_number']) . '</a>';

        // Otherwise the order has been deleted, so output message.
        } else {
            $output_order_number = lang('The order has been deleted.');
        }

        if ($gift_card['from_name'] != '') {
            $output_name = h($gift_card['from_name']);

        } else {
            $output_name = lang('Anonymous');
        }

        // If we know the user that purchased the gift card,
        // then link name to that user.
        if ($gift_card['user_id'] != '') {
            $output_from = '<a href="edit_user.php?id=' . $gift_card['user_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . $output_name . '</a>';

        // Otherwise if we know the contact that purchased the gift card,
        // then link name to that contact.
        } else if ($gift_card['contact_id'] != '') {
            $output_from = '<a href="edit_contact.php?id=' . $gift_card['contact_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . $output_name . '</a>';

        // Otherwise we don't know the user or the contact
        // so just output the name without a link.
        } else {
            $output_from = $output_name;
        }

        $output_delivery_date = '';

        // If there is a recipient, then output the delivery date.
        if ($gift_card['recipient_email_address'] != '') {
            if ($gift_card['delivery_date'] == '0000-00-00') {
                $output_delivery_date = lang('Immediate');

            } else {
                $output_delivery_date = get_absolute_time(array('timestamp' => strtotime($gift_card['delivery_date']), 'type' => 'date'));
            }
        }

        $output_order_info_rows =
            '<div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <div class="card my-4 h-100">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Order Info') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <span class="translateable col text-muted">' . lang('Order') . ' #:</span>
                            <span class="col text-end">' . $output_order_number . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col text-muted">' . lang('From') . ':</span>
                            <span class="col text-end">' . $output_from . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col text-muted">' . lang('Recipient') . ':</span>
                            <span class="col text-end"><a href="mailto:' . h($gift_card['recipient_email_address']) . '">' . h($gift_card['recipient_email_address']) . '</a></span>
                        </div>
                        <div class="row">
                            <span class="translateable col text-muted">' . lang('Message') . ':</span>
                            <span class="col text-end">' . nl2br(h($gift_card['message'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col text-muted">' . lang('Delivery Date') . ':</span>
                            <span class="col text-end">' . $output_delivery_date . '</span>
                        </div>
                    </div>
                </div>
            </div>';

    // Otherwise, this gift card was created manually, so explain that.
    } else {
        $output_order_info_rows =
            '<div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <div class="card my-4 h-100">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Order Info') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <span class="col text-center">' . lang('This gift card was created manually, so there is no order info.') . '</span>
                        </div>
                    </div>
                </div>
            </div>';
    }

    $output_redemption_history = '';

    $redemptions = db_items(
        "SELECT
            orders.id AS order_id,
            orders.order_number,
            applied_gift_cards.amount,
            applied_gift_cards.new_balance,
            orders.order_date
        FROM applied_gift_cards
        LEFT JOIN orders ON applied_gift_cards.order_id = orders.id
        WHERE
            (applied_gift_cards.gift_card_id = '" . e($gift_card['id']) . "')
            AND (orders.status != 'incomplete')
        ORDER BY orders.order_date DESC");

    // If there is at least one redemption, then output them.
    if ($redemptions) {
        $output_redemption_history_rows = '';

        
        foreach ($redemptions as $redemption) {
            $output_link_url = 'view_order.php?id=' . $redemption['order_id'] . '&amp;send_to=' . h(urlencode($request_uri));
            $output_redemption_history_rows .=
                '<tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                        <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                    </td>
                    <td class="align-middle">' . h($redemption['order_number']) . '</td>
                    <td class="align-middle text-end"><span class=" badge bg-secondary  fw-lighter">' . BASE_CURRENCY_SYMBOL . number_format($redemption['amount'] / 100, 2, '.', ',') . '</span></td>
                    <td class="align-middle text-end"><span class=" badge bg-primary  fw-lighter">' . BASE_CURRENCY_SYMBOL . number_format($redemption['new_balance'] / 100, 2, '.', ',') . '</span></td>
                    <td>' . get_relative_time(array('timestamp' => $redemption['order_date'])) . '</td>
                    <td></td>
                </tr>';
        }

        $output_redemption_history =
            '<div class="col-12 col-sm-12 col-md-8 col-lg-9">
                <div class="card my-4 h-100">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Redemption History') . '
                    </div>
                    <div class="card-header chart-buttons justify-content-end d-flex flex-wrap"></div>
                    <div class="card-body">
                        <div class="row">
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                        <th>' . lang('Order') . '</th>
                                        <th class="text-end">' . lang('Amount') . '</th>
                                        <th class="text-end">' . lang('Remaining Balance') . '</th>
                                        <th>' . lang('Redeemed') . '</th>
                                        <th class="noVis"></th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_redemption_history_rows . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>';

    } else {
        $output_redemption_history = '
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <div class="card my-4 h-100">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Redemption History') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <span class="col text-center">' . lang('This gift card has not been redeemed yet.') . '</span>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    echo
    pg_page_shell([
        'title'=> lang('Edit Gift Card'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Gift Card'),
        'cancel'=>array('enable'=>'true','url'=>'view_gift_cards.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Gift Cards'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_gift_cards.php'), array('label' => lang('Edit Gift Card'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break  ' . $output_status_class . '" data-bs-content="' . lang('Update the properties for this gift card and view the details.') . '" title="' . lang('Edit Gift Card') . '">' . output_gift_card_code($gift_card['code']) . '</h2>
                    </div>
                </div>
                <form name="form" action="edit_gift_card.php" method="post" >
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id', 'value' => $_GET['id'])) . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Gift Card Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 col-lg-3 col-xl-2 my-2">
                                            <label for="balance" class="form-label">' . lang('Balance') . '</label>
                                            <div class="input-group">
                                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'balance', 'name' => 'balance', 'class' => 'form-control text-end', 'maxlength'=>'12', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'currency', 'data-inputmask-groupSeparator'=>',', 'data-inputmask-digits'=>'2','data-inputmask-digitsOptional'=>'false', 'data-inputmask-placeholder'=>'0')) . '
                                                <label class="input-group-text" for="balance">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                            <div class="form-text text-end">' . lang(array('string'=>'Original Amount: {var:1}','vars'=>array(BASE_CURRENCY_SYMBOL . number_format($gift_card['amount'] / 100, 2) ))) . '</div>
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
                    <div class="row mb-5 g-3">
                        ' . $output_order_info_rows . '
                        ' . $output_redemption_history . '
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('gift card')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form();

// Otherwise the form has been submitted, so process it.
} else {

    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // If the user selected to delete this gift card, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        db("DELETE FROM gift_cards WHERE id = '" . e($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string'=>'gift card ({var:1}) was deleted','vars'=>output_gift_card_code($gift_card['code']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_gift_cards = new liveform('view_gift_cards');
        $liveform_view_gift_cards->add_notice(lang('The gift card has been deleted.'));
        go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
        
    // Otherwise the user selected to save the gift card, so save it.
    } else {
        $balance = $liveform->get_field_value('balance');
        $expiration_date = $liveform->get_field_value('expiration_date');
        $notes = $liveform->get_field_value('notes');

        // Remove commas from balance.
        $balance = str_replace(',', '', $balance);

        // If a balance was entered, and the value is not a number
        // greater than or equal to 0, then add error.
        if (
            ($balance != '')
            &&
            (
                (is_numeric($balance) == false)
                || ($balance < 0)
            )
        ) {
            $liveform->mark_error('balance', lang('Please enter a valid balance.'));
        }

        // If an expiration date was entered and it is not valid, then add error.
        if (($expiration_date != '') && (validate_date($expiration_date) == false)) {
            $liveform->mark_error('expiration_date', lang('Please enter a valid expiration date.'));
        }
        
        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        // Convert balance into cents.
        $balance = $balance * 100;
        
        // Update gift card properties.
        db(
            "UPDATE gift_cards
            SET
                balance = '" . e($balance) . "',
                expiration_date = '" . e(prepare_form_data_for_input($expiration_date, 'date')) . "',
                notes = '" . e($notes) . "',
                last_modified_user_id = '" . USER_ID . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string'=>'gift card ({var:1}) was modified','vars'=>output_gift_card_code($gift_card['code']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_gift_cards = new liveform('view_gift_cards');
        $liveform_view_gift_cards->add_notice(lang('The gift card has been saved.'));



        go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
    }
}
?>