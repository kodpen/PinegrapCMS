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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM gift_cards WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
$liveform = new liveform('edit_gift_card');

// A card bound to a customer is store credit: its owner, kind, reason and
// expiry, read once the upgrade has added the columns.
if (pg_credit_ready()) {
    $credit_select = ",
        gift_cards.user_id AS credit_user_id,
        gift_cards.kind AS credit_kind,
        gift_cards.reason AS credit_reason,
        gift_cards.issued_by AS credit_issued_by,
        gift_cards.expires_at AS credit_expires_at,
        credit_user.user_username AS credit_username,
        credit_user.user_role AS credit_user_role";
    $credit_join = "LEFT JOIN user AS credit_user ON gift_cards.user_id = credit_user.user_id";
} else {
    $credit_select = ", 0 AS credit_user_id";
    $credit_join = '';
}

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
        user.user_role,
        contacts.id AS contact_id,
        gift_cards.recipient_email_address,
        gift_cards.message,
        gift_cards.delivery_date" . $credit_select . "
    FROM gift_cards
    LEFT JOIN orders ON gift_cards.order_id = orders.id
    LEFT JOIN user ON orders.user_id = user.user_id
    LEFT JOIN contacts ON orders.contact_id = contacts.id
    " . $credit_join . "
    WHERE gift_cards.id = '" . e($_REQUEST['id']) . "'");

// If the form has not just been submitted, then output form.
if (!$_POST) {
    $request_uri = get_request_uri();

    // If the form has not been submitted yet, then pre-populate fields with data.
    if ($liveform->field_in_session('id') == false) {
        // The money mask takes the panel language's separators
        // (pg_money_mask_attributes()), so the balance is filled with
        // pg_format_input_amount() and read back with pg_normalize_amount().
        $liveform->assign_field_value('balance', pg_format_input_amount($gift_card['balance'] / 100));

        // The balance the screen opened with, so a save that leaves the
        // balance as it was does not write it back over an amount an order
        // spent meanwhile (submit_order() takes it off with balance - amount).
        $liveform->assign_field_value('balance_was', (string) (int) $gift_card['balance']);

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
        $output_status_class = 'status_enabled fg-success';
        $output_state = '';

    // Otherwise this gift card has expired or is used up, so use class that
    // shows red color and say which, as the list does.
    } else {
        $output_status_class = 'status_disabled fg-danger';
        $output_state = (($gift_card['expiration_date'] != '0000-00-00') && ($gift_card['expiration_date'] < date('Y-m-d')))
            ? pg_list_status_word(lang('Expired'))
            : pg_list_status_word(lang('No balance'));
    }

    $output_code = output_gift_card_code($gift_card['code']);

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

        // If we know the user that purchased the gift card and the viewer
        // may open that account, then link name to that user.
        if (($gift_card['user_id'] != '') && pg_can_open_user($user, $gift_card['user_role'])) {
            $output_from = '<a href="edit_user.php?id=' . $gift_card['user_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . $output_name . '</a>';

        // Otherwise if we know the contact that purchased the gift card and
        // the viewer may open it, then link name to that contact.
        } else if (($gift_card['contact_id'] != '') && pg_can_open_contact($user, $gift_card['contact_id'])) {
            $output_from = '<a href="edit_contact.php?id=' . $gift_card['contact_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . $output_name . '</a>';

        // Otherwise we don't know the user or the contact, or the viewer
        // may open neither, so just output the name without a link.
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
            '<div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Order Info') . '
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            <span class="translateable col fg-secondary">' . lang('Order') . ' #:</span>
                            <span class="col text-end">' . $output_order_number . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col fg-secondary">' . lang('From') . ':</span>
                            <span class="col text-end">' . $output_from . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col fg-secondary">' . lang('Recipient') . ':</span>
                            <span class="col text-end text-break"><a href="mailto:' . h($gift_card['recipient_email_address']) . '">' . h($gift_card['recipient_email_address']) . '</a></span>
                        </div>
                        <div class="row">
                            <span class="translateable col fg-secondary">' . lang('Message') . ':</span>
                            <span class="col text-end">' . nl2br(h($gift_card['message'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="translateable col fg-secondary">' . lang('Delivery Date') . ':</span>
                            <span class="col text-end">' . $output_delivery_date . '</span>
                        </div>
                    </div>
                </div>';

    // Otherwise, this gift card was created manually, so explain that.
    } else {
        $output_order_info_rows =
            '<div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Order Info') . '
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            <span class="col text-center">' . lang('This gift card was created manually, so there is no order info.') . '</span>
                        </div>
                    </div>
                </div>';
    }

    // Store credit names its customer, kind, reason and last day; a credit
    // card with no order has no "created manually" sentence.
    $credit_user_id = (int) $gift_card['credit_user_id'];

    if ($credit_user_id > 0) {
        if ((string) $gift_card['credit_username'] === '') {
            $output_owner = '#' . $credit_user_id;
        } else if (pg_can_open_user($user, $gift_card['credit_user_role'])) {
            $output_owner = '<a href="edit_user.php?id=' . $credit_user_id . '&amp;send_to=' . h(urlencode($request_uri)) . '#store_credit">' . h($gift_card['credit_username']) . '</a>';
        } else {
            $output_owner = h($gift_card['credit_username']);
        }

        $output_credit_info = pg_credit_staff_gift_card_info_html(array(
            'kind' => $gift_card['credit_kind'],
            'reason' => $gift_card['credit_reason'],
            'expires_at' => $gift_card['credit_expires_at']), $output_owner);

        $output_order_info_rows = $gift_card['order_id'] ? $output_credit_info . $output_order_info_rows : $output_credit_info;
    }

    // Store credit has no Delete: it is voided on the customer's screen.
    $delete_refusal = pg_gift_card_form_delete_refusal($gift_card);

    if ($delete_refusal === '') {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('gift card')))) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button>';
    } else {
        $void_link = (((string) $gift_card['credit_username'] !== '') && pg_can_open_user($user, $gift_card['credit_user_role']))
            ? ' <a href="edit_user.php?id=' . $credit_user_id . '&amp;send_to=' . h(urlencode($request_uri)) . '#store_credit">' . lang('Open the customer\'s store credit') . '</a>'
            : '';
        $output_delete_button = '<span class="small fg-secondary align-self-center ms-5 my-1">' . h($delete_refusal) . $void_link . '</span>';
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
        // A ledger: newest first, each row with the balance it left, so the
        // order is its meaning and it stays a plain table (no sorting).
        $output_redemption_history_rows = '';

        foreach ($redemptions as $redemption) {
            $output_redemption_history_rows .=
                '<tr>
                    <td class="align-middle"><a href="view_order.php?id=' . (int) $redemption['order_id'] . '&amp;send_to=' . h(urlencode($request_uri)) . '">' . h($redemption['order_number']) . '</a></td>
                    <td class="align-middle text-end">' . pg_format_money($redemption['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</td>
                    <td class="align-middle text-end">' . pg_format_money($redemption['new_balance'] / 100, BASE_CURRENCY_SYMBOL) . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $redemption['order_date'])) . '</td>
                </tr>';
        }

        $output_redemption_history =
            '<div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Redemption History') . '
                    </h2>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">' . lang('Order') . '</th>
                                        <th scope="col" class="text-end">' . lang('Amount') . '</th>
                                        <th scope="col" class="text-end">' . lang('Remaining Balance') . '</th>
                                        <th scope="col">' . lang('Redeemed On') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_redemption_history_rows . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

    } else {
        $output_redemption_history = '<div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Redemption History') . '
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            <span class="col text-center">' . lang('This gift card has not been redeemed yet.') . '</span>
                        </div>
                    </div>
                </div>';
    }
    
    // Edit screen layout (product_builder.php): the options and the
    // redemption history in the form column, the order the card came from
    // in a sticky status column, the save bar in its own sticky row.
    echo
    pg_page_shell([
        'title'=> lang('Edit Gift Card') . ' ' . $output_code,
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Gift Card'),
        'heading_description' => lang('Update the properties for this gift card and view the details.'),
        'cancel'=>array('enable'=>'true','url'=>'view_gift_cards.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Gift Cards'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_gift_cards.php'), array('label' => $output_code)),
        ]) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <form name="form" action="edit_gift_card.php" method="post">
        ' . get_token_field() . '
        ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id', 'value' => $_GET['id'])) . '
        ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'balance_was')) . '
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                ' . pg_gift_card_form_card($liveform, 'edit', array(
                    'symbol' => BASE_CURRENCY_SYMBOL,
                    'scripts' => get_date_picker_format() . pg_fields_script(),
                    'original_amount' => pg_format_money($gift_card['amount'] / 100, BASE_CURRENCY_SYMBOL),
                    'code' => $output_code,
                    'status_class' => $output_status_class,
                    'status_word' => $output_state)) . '
                ' . $output_redemption_history . '
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                <div class="position-sticky" style="top:3.3rem;">
                    ' . $output_order_info_rows . '
                </div>
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><i class="bi bi-floppy me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                            ' . $output_delete_button . '
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>
</main>' .
    output_footer();
    
    $liveform->remove_form();

// Otherwise the form has been submitted, so process it.
} else {

    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // If the user selected to delete this gift card, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // Store credit is voided on the customer's screen, never deleted.
        if (($refusal = pg_gift_card_form_delete_refusal($gift_card)) !== '') {
            $liveform->mark_error('', $refusal);
            go($_SERVER['PHP_SELF'] . '?id=' . (int) $gift_card['id']);
        }

        db("DELETE FROM gift_cards WHERE id = '" . e($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string'=>'gift card ({var:1}) was deleted','vars'=>pg_gift_card_log_code($gift_card['code']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_gift_cards = new liveform('view_gift_cards');
        $liveform_view_gift_cards->add_notice(lang('The gift card has been deleted.'));
        go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
        
    // Otherwise the user selected to save the gift card, so save it.
    } else {
        $expiration_date = $liveform->get_field_value('expiration_date');
        $notes = $liveform->get_field_value('notes');

        // A balance is required and 0 or above; a typed date must be valid.
        $balance = pg_gift_card_form_check($liveform, 'edit');

        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        // Convert balance into cents.
        $balance = (int) round($balance * 100);

        // The balance is written only when it was changed on this screen,
        // and then only over the amount the screen opened with: an order or
        // the API may have changed it since (pg_gift_card_form_write()). A
        // form without the field (opened before this check existed) writes
        // it as before.
        $balance_was = $liveform->get_field_value('balance_was');
        $balance_before = is_numeric($balance_was) ? (int) $balance_was : (int) $gift_card['balance'];

        $written = pg_gift_card_form_write($gift_card['id'], array(
            'balance' => $balance,
            'balance_was' => is_numeric($balance_was) ? (int) $balance_was : null,
            'expiration_date' => pg_gift_card_form_expiration($expiration_date),
            'notes' => $notes), USER_ID);

        if (!$written['ok']) {
            if ($written['field'] === 'balance') {
                $liveform->assign_field_value('balance_was', (string) $written['balance']);
                $liveform->mark_error('balance', lang(array(
                    'string' => 'The balance of this gift card changed while the screen was open: it is now {var:1}. Check it and save again.',
                    // Plain text: the field's own feedback escapes the message.
                    'vars'   => html_entity_decode(pg_format_money($written['balance'] / 100, BASE_CURRENCY_SYMBOL), ENT_QUOTES, 'UTF-8'))));
            } else {
                $liveform->mark_error('', lang('The value could not be saved.'));
            }

            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        // A changed balance of store credit reaches the audit trail, as
        // every other change of a customer's credit does.
        if (($balance_before !== $balance) && ((int) $gift_card['credit_user_id'] > 0)) {
            $credit_before = array(
                'user_id' => (int) $gift_card['credit_user_id'],
                'kind' => $gift_card['credit_kind'],
                'amount' => (int) $gift_card['amount'],
                'balance' => $balance_before,
                'reason' => $gift_card['credit_reason'],
                'issued_by' => (int) $gift_card['credit_issued_by'],
                'expires_at' => (int) $gift_card['credit_expires_at']);
            $credit_after = array('balance' => $balance) + $credit_before;

            pg_audit('update', 'store_credit', (int) $gift_card['id'], pg_credit_audit_fields($credit_before), pg_credit_audit_fields($credit_after), pg_credit_audit_label($gift_card['code']));
        }

        log_activity(lang(array('string'=>'gift card ({var:1}) was modified','vars'=>pg_gift_card_log_code($gift_card['code']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_gift_cards = new liveform('view_gift_cards');
        $liveform_view_gift_cards->add_notice(lang('The gift card has been saved.'));



        go(PATH . SOFTWARE_DIRECTORY . '/view_gift_cards.php');
    }
}
?>