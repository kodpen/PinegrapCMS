<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The e-mails a submitted custom form sends: the copy for the person who
 * sent it (and a watcher named through the add watcher field) and the
 * administrator's e-mail. custom_form.php sends them when the form is
 * submitted; a record that owes a fee (includes/fn/form_payments.php) is
 * unpaid then and sends them when its order is paid
 * (pg_form_payment_mark_paid()), with the recipients read back from the
 * stored record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The custom_form_pages columns the form's e-mails read.
 *
 * @return string[]
 */
function pg_cf_form_mails_columns()
{
    return array(
        'submitter_email', 'submitter_email_from_email_address', 'submitter_email_subject',
        'submitter_email_format', 'submitter_email_body', 'submitter_email_page_id',
        'administrator_email', 'administrator_email_to_email_address', 'administrator_email_bcc_email_address',
        'administrator_email_subject', 'administrator_email_format', 'administrator_email_body',
        'administrator_email_page_id', 'offer');
}

/**
 * Who gets the form's e-mails now. Pure. Nothing while the record owes a
 * fee: it is unpaid, and the e-mails go when it is paid. The sender's copy
 * goes to the sender's address and to a watcher, one e-mail each (they do
 * not see each other's address); the administrator's e-mail to the
 * addresses of the options chosen and the To address, sent when it has a
 * To or a Bcc.
 *
 * @param array $settings pg_cf_form_mails_columns() values
 * @param array $mail     submitter_email_address, watcher_email_address,
 *                        administrator_email_addresses (from the chosen
 *                        options), fee_owed
 * @return array submitter (addresses), administrator (To addresses),
 *               administrator_send (bool)
 */
function pg_cf_form_mails_plan(array $settings, array $mail)
{
    $plan = array('submitter' => array(), 'administrator' => array(), 'administrator_send' => false);

    if (!empty($mail['fee_owed'])) {
        return $plan;
    }

    if ((int) ($settings['submitter_email'] ?? 0) === 1) {
        foreach (array('submitter_email_address', 'watcher_email_address') as $key) {
            if ((string) ($mail[$key] ?? '') !== '') {
                $plan['submitter'][] = (string) $mail[$key];
            }
        }
    }

    if ((int) ($settings['administrator_email'] ?? 0) === 1) {
        $to = array_values(array_map('strval', (array) ($mail['administrator_email_addresses'] ?? array())));
        $to_address = (string) ($settings['administrator_email_to_email_address'] ?? '');

        if (($to_address !== '') && !in_array($to_address, $to, true)) {
            $to[] = $to_address;
        }

        if ((count($to) > 0) || ((string) ($settings['administrator_email_bcc_email_address'] ?? '') !== '')) {
            $plan['administrator'] = $to;
            $plan['administrator_send'] = true;
        }
    }

    return $plan;
}

/**
 * The administrator addresses the chosen options of a pick list, radio
 * button or check box add, in the order found, each once. An option may
 * hold several, separated by commas. Pure.
 *
 * @param string[] $option_addresses form_field_options.email_address values
 * @return string[]
 */
function pg_cf_option_admin_addresses(array $option_addresses)
{
    $addresses = array();

    foreach ($option_addresses as $value) {
        foreach (explode(',', (string) $value) as $address) {
            $address = trim($address);

            if (!in_array($address, $addresses, true)) {
                $addresses[] = $address;
            }
        }
    }

    return $addresses;
}

/**
 * The sender's address of a stored record, found as custom_form.php finds
 * it at submit. Pure. The value of the field connected to the contact's
 * e-mail address, else the signed-in submitter's own address, else the
 * value of the form's only e-mail address field.
 *
 * @param string $contact_field_value
 * @param string $user_email_address
 * @param array  $email_field_values the values of the form's e-mail address fields, one per field
 * @return string
 */
function pg_cf_record_submitter_address($contact_field_value, $user_email_address, array $email_field_values)
{
    if ((string) $contact_field_value !== '') {
        return (string) $contact_field_value;
    }

    if ((string) $user_email_address !== '') {
        return (string) $user_email_address;
    }

    return (count($email_field_values) === 1) ? (string) reset($email_field_values) : '';
}

/**
 * The form's e-mail settings, or null for a page that is no custom form.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_cf_form_mails_settings($page_id)
{
    $row = db_item(
        "SELECT " . implode(', ', pg_cf_form_mails_columns()) . "
        FROM custom_form_pages
        WHERE page_id = '" . e((int) $page_id) . "'
        LIMIT 1");

    return $row ? $row : null;
}

/**
 * Sends the e-mails of a record whose fee has just been paid. The
 * recipients are read back from the record: the sender's address
 * (pg_cf_record_submitter_address()), the administrators of the options
 * chosen, the offer's key code. A watcher named through the add watcher
 * field is not stored and gets no copy; a new account's set password link
 * is given only while the session that opened it is the one paying.
 *
 * @param array $record forms row: id, page_id, user_id, contact_id, reference_code
 * @return void
 */
function pg_cf_form_mails_send_for_record(array $record)
{
    $form_id = (int) ($record['id'] ?? 0);
    $page_id = (int) ($record['page_id'] ?? 0);
    $settings = pg_cf_form_mails_settings($page_id);

    if (($form_id <= 0) || ($settings === null)) {
        return;
    }

    $contact_field_value = (string) db_value(
        "SELECT form_data.data
        FROM form_data
        INNER JOIN form_fields ON form_fields.id = form_data.form_field_id
        WHERE form_data.form_id = '" . e($form_id) . "'
            AND form_fields.contact_field = 'email_address'
            AND form_data.data != ''
        LIMIT 1");

    $user_email_address = ((int) ($record['user_id'] ?? 0) > 0)
        ? (string) db_value("SELECT user_email FROM user WHERE user_id = '" . e((int) $record['user_id']) . "'")
        : '';

    $email_field_values = array();

    foreach (db_items(
        "SELECT form_fields.id, form_data.data
        FROM form_fields
        LEFT JOIN form_data ON form_data.form_field_id = form_fields.id AND form_data.form_id = '" . e($form_id) . "'
        WHERE form_fields.page_id = '" . e($page_id) . "' AND form_fields.type = 'email address'") as $field) {
        $email_field_values[(int) $field['id']] = (string) $field['data'];
    }

    $option_addresses = db_values(
        "SELECT form_field_options.email_address
        FROM form_data
        INNER JOIN form_fields ON form_fields.id = form_data.form_field_id
        INNER JOIN form_field_options ON form_field_options.form_field_id = form_data.form_field_id
            AND form_field_options.value = form_data.data
        WHERE form_data.form_id = '" . e($form_id) . "'
            AND form_fields.type IN ('pick list', 'radio button', 'check box')
            AND form_field_options.email_address != ''
        ORDER BY form_fields.sort_order, form_data.id");

    $key_code = '';

    if ($settings['offer'] && ((string) ($record['reference_code'] ?? '') !== '')) {
        $key_code = (string) db_value("SELECT code FROM key_codes WHERE code = '" . e((string) $record['reference_code']) . "' LIMIT 1");
    }

    pg_cf_form_mails_send($page_id, $form_id, $settings, array(
        'submitter_email_address'       => pg_cf_record_submitter_address($contact_field_value, $user_email_address, $email_field_values),
        'watcher_email_address'         => '',
        'administrator_email_addresses' => pg_cf_option_admin_addresses((array) $option_addresses),
        'contact_id'                    => (int) ($record['contact_id'] ?? 0),
        'key_code'                      => $key_code,
        'fee_owed'                      => false,
    ));
}

/**
 * Sends a submitted form's e-mails (pg_cf_form_mails_plan() decides who
 * gets them).
 *
 * @param int   $page_id  the custom form page
 * @param int   $form_id  the record (forms.id)
 * @param array $settings pg_cf_form_mails_columns() values
 * @param array $mail     submitter_email_address, watcher_email_address,
 *                        administrator_email_addresses, contact_id,
 *                        key_code, fee_owed
 * @return void
 */
function pg_cf_form_mails_send($page_id, $form_id, array $settings, array $mail)
{
    $page_id = (int) $page_id;
    $form_id = (int) $form_id;
    $plan = pg_cf_form_mails_plan($settings, $mail);

    // The form's WhatsApp message to its sender goes when its e-mails do:
    // not while a fee is owed, once the record is paid.
    if (empty($mail['fee_owed'])) {
        pg_whatsapp_form_message($page_id, $form_id);
    }

    $submitter_email_address = (string) ($mail['submitter_email_address'] ?? '');
    $contact_id = (int) ($mail['contact_id'] ?? 0);
    $key_code = (string) ($mail['key_code'] ?? '');
    $offer = !empty($settings['offer']);

    $submitter_email_from_email_address = (string) $settings['submitter_email_from_email_address'];
    $submitter_email_subject = (string) $settings['submitter_email_subject'];
    $submitter_email_format = (string) $settings['submitter_email_format'];
    $submitter_email_body = (string) $settings['submitter_email_body'];
    $submitter_email_page_id = (int) $settings['submitter_email_page_id'];
    $administrator_email_bcc_email_address = (string) $settings['administrator_email_bcc_email_address'];
    $administrator_email_subject = (string) $settings['administrator_email_subject'];
    $administrator_email_format = (string) $settings['administrator_email_format'];
    $administrator_email_body = (string) $settings['administrator_email_body'];
    $administrator_email_page_id = (int) $settings['administrator_email_page_id'];

    // One e-mail to each recipient of the sender's copy: they do not see each
    // other's address.
    foreach ($plan['submitter'] as $to_email_address) {

        if ($submitter_email_from_email_address != '') {
            $from_email_address = $submitter_email_from_email_address;
        } else {
            $from_email_address = EMAIL_ADDRESS;
        }
        
        // The signed links (^^email_preferences_url^^, ^^confirm_subscription_url^^)
        // go into the sender's own copy only; a watcher's copy gets them empty.
        if ($to_email_address == $submitter_email_address) {
            pg_cf_mail_links_context(array(
                'email_address' => $submitter_email_address,
                'contact_id' => $contact_id,
                'custom_form_page_id' => $page_id,
                'form_id' => $form_id));
        } else {
            pg_cf_mail_links_context(false);
        }

        $mail_link_values = pg_cf_mail_link_values();

        // The link fields are filled into the template before the submitted
        // values go in, so a value holding "[[" or "]]" cannot open or close
        // a block around other text. $submitter_email_subject stays the
        // template for the next recipient.
        $subject = pg_cf_fill_mail_links($submitter_email_subject, $mail_link_values, 'plain_text');

        // check the subject line for variable data and replace any variables with content from the submitted form
        $subject = get_variable_submitted_form_data_for_content($page_id, $form_id, $subject, $prepare_for_html = FALSE);

        // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
        // with the key code that was created, or a blank value if no key code was created.
        if ($offer) {
            $subject = replace_variables(array(
                'content' => $subject,
                'fields' => array(array(
                    'name' => 'key_code',
                    'data' => $key_code,
                    'type' => '')),
                'format' => 'plain_text'));
        }

        // if the format of the e-mail should be plain text, then prepare that
        if ($submitter_email_format == 'plain_text') {

            // Check the body for variable data and replace any variables with content from the submitted form.
            // An empty body becomes the list of the form's fields (pg_cf_email_body_or_default()).
            // The link fields go into the template first (see the subject).
            $body = get_variable_submitted_form_data_for_content($page_id, $form_id, pg_cf_fill_mail_links(pg_cf_email_body_or_default($page_id, $submitter_email_body, true), $mail_link_values, 'plain_text'), $prepare_for_html = FALSE);

            // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
            // with the key code that was created, or a blank value if no key code was created.
            if ($offer) {
                $body = replace_variables(array(
                    'content' => $body,
                    'fields' => array(array(
                        'name' => 'key_code',
                        'data' => $key_code,
                        'type' => '')),
                    'format' => 'plain_text'));
            }

            // If a user account was created via the auto-registration feature,
            // and this is the email for the submitter (i.e. not the email for the watcher)
            // then name the account and give the link to set its password.
            if ($to_email_address == $submitter_email_address) {
                $body .= pg_cf_new_account_plain_text(pg_cf_new_account_email($form_id), pg_cf_new_account_link($form_id), true);
            }

        // else the format of the e-mail should be HTML, so prepare that
        } else {

            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

            // A new account's set password link goes into the sender's copy only.
            pg_cf_new_account_recipient($to_email_address == $submitter_email_address);

            $body = get_page_content($submitter_email_page_id, $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true, array('form_id' => $form_id));

            pg_cf_new_account_recipient(true);

            // The sender's copy reads in the language they wrote in.
            if (function_exists('pg_tr_email')) {
                $body = pg_tr_email($body);
            }

            // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
            // with the key code that was created, or a blank value if no key code was created.
            if ($offer) {
                $body = replace_variables(array(
                    'content' => $body,
                    'fields' => array(array(
                        'name' => 'key_code',
                        'data' => $key_code,
                        'type' => '')),
                    'format' => 'html'));
            }

            // Bare link fields outside the form item view (other regions of the
            // e-mail page); the body already holds the submitted values, so no
            // [[...]] block is evaluated here.
            $body = pg_cf_fill_mail_link_tokens($body, $mail_link_values, 'html');

        }
        
        email(array(
            'to' => $to_email_address,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => $from_email_address,
            'subject' => $subject,
            'format' => $submitter_email_format,
            'body' => $body,
            // The sender's copy may carry the set password link or a signed
            // link, which must stay out of the failure log and the
            // delivery-failed notice.
            'secret' => (($to_email_address == $submitter_email_address) && (pg_cf_new_account_email($form_id) !== ''))
                || pg_cf_mail_links_in($subject . $body, $mail_link_values),
            'source' => 'form:' . (int) $form_id,
            'queue' => true));

        pg_cf_mail_links_context(false);

    }

    if ($plan['administrator_send']) {
        // The signed links belong to the sender alone: empty in the administrator's
        // e-mail, filled into the template before the submitted values go in.
        $administrator_email_subject = pg_cf_fill_mail_links($administrator_email_subject, array(), 'plain_text');

        // check the subject line for variable data and replace any variables with content from the submitted form
        $administrator_email_subject = get_variable_submitted_form_data_for_content($page_id, $form_id, $administrator_email_subject, $prepare_for_html = FALSE);

        // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
        // with the key code that was created, or a blank value if no key code was created.
        if ($offer) {
            $administrator_email_subject = replace_variables(array(
                'content' => $administrator_email_subject,
                'fields' => array(array(
                    'name' => 'key_code',
                    'data' => $key_code,
                    'type' => '')),
                'format' => 'plain_text'));
        }

        // if the format of the e-mail should be plain text, then prepare that
        if ($administrator_email_format == 'plain_text') {
            // Check the body for variable data and replace any variables with content from the submitted form.
            // An empty body becomes the list of the form's fields (pg_cf_email_body_or_default()).
            $body = get_variable_submitted_form_data_for_content($page_id, $form_id, pg_cf_fill_mail_links(pg_cf_email_body_or_default($page_id, $administrator_email_body), array(), 'plain_text'), $prepare_for_html = FALSE);

            // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
            // with the key code that was created, or a blank value if no key code was created.
            if ($offer) {
                $body = replace_variables(array(
                    'content' => $body,
                    'fields' => array(array(
                        'name' => 'key_code',
                        'data' => $key_code,
                        'type' => '')),
                    'format' => 'plain_text'));
            }

            // If a user account was created via the auto-registration feature,
            // then say so. The set password link belongs to the visitor alone.
            $body .= pg_cf_new_account_plain_text(pg_cf_new_account_email($form_id), '', false);

        // else the format of the e-mail should be HTML, so prepare that
        } else {

            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
            
            // The administrator's e-mail in the site's own language, whatever
            // language the form was sent from.
            if (function_exists('pg_tr_suspend')) {
                pg_tr_suspend(true);
            }

            $body = get_page_content($administrator_email_page_id, $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true, array('form_id' => $form_id));

            if (function_exists('pg_tr_suspend')) {
                pg_tr_suspend(false);
            }

            // If this form grants an offer, then replace ^^key_code^^ mail-merge field,
            // with the key code that was created, or a blank value if no key code was created.
            if ($offer) {
                $body = replace_variables(array(
                    'content' => $body,
                    'fields' => array(array(
                        'name' => 'key_code',
                        'data' => $key_code,
                        'type' => '')),
                    'format' => 'html'));
            }

            $body = pg_cf_fill_mail_link_tokens($body, array(), 'html');

        }

        // In the past we would set the from info to the submitter's address (if one existed),
        // however this caused issues with mail providers using DMARC (e.g. Yahoo, AOL),
        // so we now send it from this site's info and add a reply to for the submitter (if exists).
        
        email(array(
            'to' => $plan['administrator'],
            'bcc' => $administrator_email_bcc_email_address,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'reply_to' => $submitter_email_address,
            'subject' => $administrator_email_subject,
            'format' => $administrator_email_format,
            'body' => $body,
            'source' => 'form:' . (int) $form_id,
            'queue' => true));

    }
}
