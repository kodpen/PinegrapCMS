<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - create an account.
 *
 * An opening balance can be entered here and is written as a movement rather
 * than onto the balance directly, so where the figure came from stays readable
 * a year later.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_account');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    // Defaults, applied only on the first visit so a failed save comes back
    // holding what the user typed rather than the defaults.
    if ($liveform->field_in_session('kind') == false) {
        $liveform->assign_field_value('kind', 'customer');
        $liveform->assign_field_value('status', 'active');
        $liveform->assign_field_value('is_person', '1');
        $liveform->assign_field_value('opening_amount', '0');
        $liveform->assign_field_value('opening_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
        $liveform->assign_field_value('currency', erp_base_currency());
        $liveform->assign_field_value('overdue_notify_customer', '1');
        $liveform->assign_field_value('invoice_mail', '1');
        $liveform->assign_field_value('contact_id', '0');
        $liveform->assign_field_value('country_code', erp_default_country_code());

        // Opened from a contact's screen: the form comes filled in from the
        // card, the way the order bridge would fill it, for the operator to
        // check before it is saved. A contact that already has an account is
        // sent to that account instead of getting a second one. One value
        // only: an array (?contact_id[]=x) cast to 1 and filled in the first
        // contact.
        $from_contact = is_scalar($_GET['contact_id'] ?? null) ? (int) $_GET['contact_id'] : 0;

        if ($from_contact > 0) {
            $existing_id = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $from_contact . "' ORDER BY id ASC LIMIT 1");

            if ($existing_id > 0) {
                $liveform->remove_form();
                $liveform_edit = new liveform('edit_erp_account');
                $liveform_edit->add_notice(lang('This contact already has an account; this is it.'));
                go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $existing_id);
            }

            // Opened from an order's documents card: what the card leaves
            // empty comes from that order's billing block, as the order's
            // first invoice would open the account (erp_account_data_for_contact()).
            // Only an order of this contact counts.
            $from_order = is_scalar($_GET['order_id'] ?? null) ? (int) $_GET['order_id'] : 0;
            $order = ($from_order > 0)
                ? db_item("SELECT * FROM orders WHERE id = '" . $from_order . "' AND contact_id = '" . $from_contact . "' LIMIT 1")
                : null;

            $prefill = erp_account_data_for_contact($from_contact, is_array($order) ? $order : null);

            if (is_array($prefill)) {
                foreach (array('title', 'tax_number', 'tax_office', 'email', 'phone', 'address', 'district', 'city', 'state', 'postcode') as $field) {
                    $liveform->assign_field_value($field, (string) ($prefill[$field] ?? ''));
                }
                $liveform->assign_field_value('is_person', $prefill['is_person'] ? '1' : '0');
                $liveform->assign_field_value('country_code', strtoupper(trim((string) ($prefill['country_code'] ?? erp_default_country_code()))));
                $liveform->assign_field_value('contact_id', (string) $from_contact);
            }
        }
    }

    $linked_contact = ((int) $liveform->get_field_value('contact_id') > 0) ? erp_contact_summary((int) $liveform->get_field_value('contact_id')) : null;

    echo
    pg_page_shell([
        'title' => lang('Create Account'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => lang('Create Account'),
        'heading_description' => lang('Add a customer or a supplier, with an opening balance if they already owe or are owed.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_accounts.php'),
        'breadcrumb' => array(
            array('label' => lang('Accounts'), 'url' => $list_url),
            array('label' => lang('Create Account')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_account.php" method="post">
                ' . get_token_field() . '
                ' . erp_account_form_cards($liveform, true, false, $linked_contact) . '
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><i class="bi bi-plus-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string' => 'Create')) . '</span></button>
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

    // Every field of the form is one value. One posted as an array counts as
    // not given: (string) wrote "Array" onto the card with a notice.
    pg_request_single_values(erp_account_form_single_fields());

    $liveform->add_fields_to_session();

    $liveform->validate_required_field('title', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));
    $country_code = erp_account_form_country($liveform);
    erp_account_form_check_formats($liveform, $country_code);
    erp_account_form_check_tax_number_taken($liveform, $country_code);

    list($payment_days, $overdue_notify_days) = erp_account_form_check_days($liveform);

    $opening_amount = trim((string) $liveform->get_field_value('opening_amount'));
    $opening_date = trim((string) $liveform->get_field_value('opening_date'));

    if (($opening_amount !== '') && (erp_kurus($opening_amount) === 0) && (preg_match('/^-?[0-9.,]+$/', $opening_amount) !== 1)) {
        $liveform->mark_error('opening_amount', lang('Please enter a valid amount.'));
    }

    if (($opening_date !== '') && (validate_date($opening_date) == false)) {
        $liveform->mark_error('opening_date', lang('Please enter a valid date.'));
    }

    // The account's currency: the base unless foreign currency is on and an
    // allowed code was chosen.
    $currency = erp_base_currency();
    if (erp_fx_enabled()) {
        $chosen = strtoupper(trim((string) $liveform->get_field_value('currency')));
        if ($chosen !== '') {
            if (!erp_fx_currency_allowed($chosen)) {
                $liveform->mark_error('currency', lang('That currency is not enabled for the ERP.'));
            }
            $currency = $chosen;
        }
    }

    // A foreign-currency opening figure is booked at the recorded rate of
    // its date; without one the account cannot be opened in that currency.
    $opening = erp_kurus($opening_amount);
    $opening_date_sql = ($opening_date !== '') ? prepare_form_data_for_input($opening_date, 'date') : date('Y-m-d');
    $opening_rate = array('rate' => 1.0, 'rate_date' => $opening_date_sql, 'source' => 'base');

    if (($opening !== 0) && ($currency !== erp_base_currency()) && ($liveform->check_form_errors() == false)) {
        $opening_rate = erp_fx_rate_for($currency, $opening_date_sql);
        if (!is_array($opening_rate)) {
            $liveform->mark_error('opening_amount', lang(array(
                'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                'vars' => array($currency, prepare_form_data_for_output($opening_date_sql, 'date')),
            )));
        }
    }

    // A contact that already belongs to another account refuses the save
    // before anything is written.
    $contact_id = max(0, (int) $liveform->get_field_value('contact_id'));

    if ($contact_id > 0) {
        $other = db_item("SELECT id, title FROM erp_accounts WHERE contact_id = '" . $contact_id . "' LIMIT 1");

        if (is_array($other)) {
            $liveform->mark_error('contact_id', lang(array('string' => 'This contact is already linked to the account "{var:1}". Unlink it there first.', 'vars' => h($other['title']))));
        } elseif ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $contact_id . "'") === 0) {
            $liveform->mark_error('contact_id', lang('The contact could not be found.'));
        }
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_account.php');
    }

    // An empty country choice takes the store's country.
    $result = erp_account_save(array('id' => 0) + erp_account_form_save_fields($liveform, $country_code, $currency, $contact_id,
        $payment_days, $overdue_notify_days, erp_default_country_code()) + array('created_by' => (int) $user['id']));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_account.php');
    }

    // The mirror on the contact, so its screen finds the account.
    if ($contact_id > 0) {
        erp_account_link_contact((int) $result['id'], $contact_id);
    }

    if ($opening !== 0) {
        $opened = erp_account_open(array(
            'account_id' => $result['id'],
            'amount' => $opening,
            'doc_date' => $opening_date_sql,
            'currency' => $currency,
            'exchange_rate' => $opening_rate['rate'],
            'exchange_rate_date' => $opening_rate['rate_date'],
            'exchange_rate_source' => $opening_rate['source'],
            'created_by' => (int) $user['id'],
        ));

        // The account itself is saved by now, so a refused opening balance is
        // reported against the account rather than losing the whole entry.
        if (!$opened['success']) {
            $liveform->remove_form();
            $liveform_edit = new liveform('edit_erp_account');
            $liveform_edit->mark_error('_error', $opened['error']);
            go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . (int) $result['id']);
        }
    }

    log_activity(lang(array('string' => 'erp account ({var:1}) was created', 'vars' => $liveform->get_field_value('title'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_accounts');
    $liveform_list->add_notice(lang('The account has been created.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
}
