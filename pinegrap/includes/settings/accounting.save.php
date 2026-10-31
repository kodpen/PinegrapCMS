<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Accounting screen writes: the ERP and the e-document provider.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

// The ERP module's cards that the pane drew and that came with the post: a
// card's fields missing from a hand-made request leave its settings alone.
$erp_settings_cards = array();

if (erp_settings_rules_editable() && isset($_POST['vat_exemption_code'])) {
    $erp_settings_cards[] = 'rules';
}

if (erp_late_fee_ready() && isset($_POST['late_fee_rate'], $_POST['late_fee_tax_rate'])) {
    $erp_settings_cards[] = 'late_fee';
}

if (erp_efatura_threshold_ready() && isset($_POST['erp_efatura_web_threshold'], $_POST['erp_efatura_general_threshold'])) {
    $erp_settings_cards[] = 'efatura_threshold';
}

// A refused value stops the whole save before anything is written.
foreach (erp_settings_cards_refusals($_POST, $erp_settings_cards) as $erp_settings_field => $erp_settings_error) {
    $liveform->mark_error($erp_settings_field, h($erp_settings_error));
}

if ($liveform->check_form_errors()) {
    return;
}

/**
 * Build the encrypted Parasut credentials blob for the settings save.
 *
 * The screen never renders a stored secret back into the form, so an empty box
 * means "leave it alone" and not "clear it" - otherwise every unrelated save on
 * the e-commerce settings page would wipe the connection. Clearing is done by
 * turning the integration off, not by blanking a field nobody filled in.
 *
 * @return string  "<ciphertext>:<iv>", or '' when nothing has ever been stored
 */
function pg_parasut_credentials_for_save()
{
    $current = ['client_secret' => '', 'password' => ''];

    $stored = (string) db_value("SELECT parasut_credentials_enc FROM config LIMIT 1");
    if (($stored !== '') && (strpos($stored, ':') !== false)) {
        list($cipher, $iv) = explode(':', $stored, 2);
        $json = decode_ssl_keys($cipher, $iv);
        $values = ($json === '') ? null : json_decode($json, true);
        if (is_array($values)) {
            $current['client_secret'] = (string) ($values['client_secret'] ?? '');
            $current['password'] = (string) ($values['password'] ?? '');
        }
    }

    $posted_secret = trim(post_value('parasut_client_secret'));
    $posted_password = trim(post_value('parasut_password'));

    if ($posted_secret !== '') {
        $current['client_secret'] = $posted_secret;
    }
    if ($posted_password !== '') {
        $current['password'] = $posted_password;
    }

    if (($current['client_secret'] === '') && ($current['password'] === '')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv(json_encode($current));

    return $cipher . ':' . $iv;
}

    // The e-document provider (4.61): the picked provider becomes the
    // active one and only its credentials are written - a box left empty
    // keeps the stored secret, like the Paraşüt fields above.
    //
    // Every value here is one string. The card always posts the provider
    // radio and each provider's boxes, so one that is missing or posted as
    // an array (edoc_provider[]=x) is not given: the provider stays as it is
    // and the stored credential is kept. (string) read an array as "Array",
    // which switched the provider off or stored "Array" as the key. A select
    // keeps to its own options; another value leaves the stored one.
    if (waf_table_has_column('erp_edoc_providers', 'provider') && is_string(post_value('edoc_provider'))) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

        $erp_edoc_picked = post_value('edoc_provider');

        if (($erp_edoc_picked !== '') && !array_key_exists($erp_edoc_picked, erp_edoc_drivers())) {
            $erp_edoc_picked = '';
        }

        if ($erp_edoc_picked !== '') {
            $erp_edoc_posted = array();

            foreach (erp_edoc_fields($erp_edoc_picked) as $erp_edoc_field) {
                $erp_edoc_value = post_value('edoc_' . $erp_edoc_picked . '_' . $erp_edoc_field['name']);

                if (!is_string($erp_edoc_value)) {
                    continue;
                }

                if (($erp_edoc_field['type'] === 'select') && !empty($erp_edoc_field['options'])
                    && !array_key_exists($erp_edoc_value, (array) $erp_edoc_field['options'])) {
                    continue;
                }

                $erp_edoc_posted[$erp_edoc_field['name']] = $erp_edoc_value;
            }

            if (!empty($erp_edoc_posted)) {
                erp_edoc_credentials_save($erp_edoc_picked, $erp_edoc_posted);
            }
        }

        if (erp_edoc_active() !== $erp_edoc_picked) {
            erp_edoc_activate($erp_edoc_picked);
            log_activity(lang(array('string' => 'erp e-document provider set to {var:1}', 'vars' => (($erp_edoc_picked === '') ? 'none' : $erp_edoc_picked))), $_SESSION['sessionusername']);
        }
    }

    // Foreign currency in the ERP (2026.4.4). The currency list is whatever
    // was ticked, kept to three-letter codes the store lists; the base is
    // never stored because it is always allowed.

    $sql_erp_fx = "";

    // The default payment term (2026.4.4, 4.53): whole days, ten years at most.
    $sql_erp_due = "";

    if (waf_table_has_column('config', 'erp_default_due_days')) {
        $sql_erp_due = "erp_default_due_days = '" . min(3650, max(0, (int) post_value('erp_default_due_days'))) . "',";
    }

    // The second tax's name (4.75); empty switches it off.
    if (waf_table_has_column('config', 'erp_tax2_name')) {
        $sql_erp_due .= "
            erp_tax2_name = '" . escape(mb_substr(trim((string) post_value('erp_tax2_name')), 0, 30)) . "',";
    }

    // What the till shows (4.74): 'gross' or 'net', nothing else.
    if (waf_table_has_column('config', 'local_sale_prices')) {
        $sql_erp_due .= "
            local_sale_prices = '" . ((post_value('local_sale_prices') === 'net') ? 'net' : 'gross') . "',";
    }

    // Whether the ERP's documents change stock counts (4.77).
    if (waf_table_has_column('config', 'erp_stock_documents')) {
        $sql_erp_due .= "
            erp_stock_documents = '" . ((post_value('erp_stock_documents') == 1) ? 1 : 0) . "',";
    }

    // Invoices by e-mail (4.97): on their own or not, and the covering text;
    // an empty text is stored as NULL so the built-in one is used.
    if (waf_table_has_column('config', 'erp_invoice_mail_auto')) {
        $erp_invoice_mail_message_value = mb_substr(trim((string) post_value('erp_invoice_mail_message')), 0, 2000);
        $sql_erp_due .= "
            erp_invoice_mail_auto = '" . ((post_value('erp_invoice_mail_auto') == 1) ? 1 : 0) . "',
            erp_invoice_mail_message = " . (($erp_invoice_mail_message_value === '') ? 'NULL' : "'" . escape($erp_invoice_mail_message_value) . "'") . ",";
    }

    // What a sale past a customer's credit limit does (4.98).
    if (waf_table_has_column('config', 'erp_credit_limit_mode')) {
        $sql_erp_due .= "
            erp_credit_limit_mode = '" . ((post_value('erp_credit_limit_mode') === 'block') ? 'block' : 'warn') . "',";
    }

    // The prices agreed with an account, in the store (10.59).
    if (waf_table_has_column('config', 'erp_storefront_account_prices')) {
        $sql_erp_due .= "
            erp_storefront_account_prices = '" . ((post_value('erp_storefront_account_prices') == 1) ? 1 : 0) . "',";
    }

    // Putting an order on the account at checkout (10.60).
    if (waf_table_has_column('config', 'erp_open_account_payment')) {
        $sql_erp_due .= "
            erp_open_account_payment = '" . ((post_value('erp_open_account_payment') == 1) ? 1 : 0) . "',";
    }

    // Notices on the bell and the devices (4.91); the amount arrives in whole
    // units of the base currency and is kept in kurus.
    if (waf_table_has_column('config', 'erp_notify_collections')) {
        $sql_erp_due .= "
            erp_notify_collections = '" . ((post_value('erp_notify_collections') == 1) ? 1 : 0) . "',
            erp_notify_collection_min = '" . (min(100000000, max(0, (int) post_value('erp_notify_collection_min'))) * 100) . "',
            erp_notify_low_stock = '" . ((post_value('erp_notify_low_stock') == 1) ? 1 : 0) . "',";
    }

    // Open quotes about to run out (8.58): days ahead kept within 1-30.
    if (waf_table_has_column('config', 'erp_notify_quote_expiry')) {
        $sql_erp_due .= "
            erp_notify_quote_expiry = '" . ((post_value('erp_notify_quote_expiry') == 1) ? 1 : 0) . "',
            erp_notify_quote_expiry_days = '" . min(30, max(1, (int) post_value('erp_notify_quote_expiry_days'))) . "',";
    }

    // A quote the bell announces also opens a Workspace follow-up.
    if (waf_table_has_column('config', 'erp_quote_expiry_followup')) {
        $sql_erp_due .= "
            erp_quote_expiry_followup = '" . ((post_value('erp_quote_expiry_followup') == 1) ? 1 : 0) . "',";
    }

    // How an order's shipping and surcharge are taxed on its invoice (4.76):
    // 'included', 'none', or empty for the store's country to decide.
    if (waf_table_has_column('config', 'erp_shipping_tax')) {
        $erp_shipping_tax_value = (string) post_value('erp_shipping_tax');
        if (!in_array($erp_shipping_tax_value, array('', 'included', 'none'), true)) {
            $erp_shipping_tax_value = '';
        }
        $sql_erp_due .= "
            erp_shipping_tax = '" . escape($erp_shipping_tax_value) . "',";
    }

    // Number shape and tax name (4.72). An unknown shape falls back to the
    // GİB one rather than to an empty value the numbering would not know.
    if (waf_table_has_column('config', 'erp_number_style')) {
        $erp_number_style_value = (string) post_value('erp_number_style');
        if (!in_array($erp_number_style_value, array('gib', 'year', 'continuous'), true)) {
            $erp_number_style_value = 'gib';
        }
        $sql_erp_due .= "
            erp_number_style = '" . escape($erp_number_style_value) . "',
            erp_tax_name = '" . escape(mb_substr(trim((string) post_value('erp_tax_name')), 0, 30)) . "',";
    }

    // When a web order's invoice is issued (2026.4.4): from the order screen,
    // or on its own once the order is paid or shipped. Written only when the
    // ERP card posted it, so a form without the card keeps the store's choice.
    if (waf_table_has_column('config', 'erp_auto_invoice_on') && is_string(post_value('erp_auto_invoice_on'))) {
        $erp_auto_invoice_value = (string) post_value('erp_auto_invoice_on');
        if (!in_array($erp_auto_invoice_value, array('off', 'order_paid', 'order_shipped'), true)) {
            $erp_auto_invoice_value = 'off';
        }
        $sql_erp_due .= "
            erp_auto_invoice_on = '" . escape($erp_auto_invoice_value) . "',";

        // Its two filters (11.0.58), drawn with the select, so a switch that
        // did not come is one left off. The amount is typed in whole units of
        // the base currency and kept in minor units.
        if (waf_table_has_column('config', 'erp_auto_invoice_tax_number_only') && waf_table_has_column('config', 'erp_auto_invoice_min_total')) {
            $sql_erp_due .= "
            erp_auto_invoice_tax_number_only = '" . ((post_value('erp_auto_invoice_tax_number_only') == 1) ? 1 : 0) . "',
            erp_auto_invoice_min_total = '" . (min(10000000, max(0, (int) post_value('erp_auto_invoice_min_total'))) * 100) . "',";
        }

        // Marketplace orders too (11.0.62), drawn with the select as well.
        if (waf_table_has_column('config', 'erp_auto_invoice_marketplace')) {
            $sql_erp_due .= "
            erp_auto_invoice_marketplace = '" . ((post_value('erp_auto_invoice_marketplace') == 1) ? 1 : 0) . "',";
        }
    }

    // The walk-in sales account (4.59): an account that exists, or none.
    if (waf_table_has_column('config', 'erp_walkin_account_id')) {
        $erp_walkin_account_id = (int) post_value('erp_walkin_account_id');
        if (($erp_walkin_account_id > 0) && ((int) db_value("SELECT COUNT(*) FROM erp_accounts WHERE id = '" . $erp_walkin_account_id . "'") === 0)) {
            $erp_walkin_account_id = 0;
        }
        $sql_erp_due .= "
            erp_walkin_account_id = '" . $erp_walkin_account_id . "',";
    }

    // Whether a created document is handed to the tax authority in the same
    // step (4.63). Written only when the block that carries the switch was
    // drawn, so a pane without the e-document card cannot switch it off by
    // simply not posting the box.
    if (waf_table_has_column('config', 'erp_edoc_autosend') && waf_table_has_column('erp_edoc_providers', 'provider')) {
        $sql_erp_due .= "
            erp_edoc_autosend = '" . ((post_value('erp_edoc_autosend') == 1) ? 1 : 0) . "',";
    }

    if (waf_table_has_column('config', 'erp_fx_enabled')) {
        $erp_fx_codes = array();
        $erp_fx_known = array();

        foreach ((array) db_items("SELECT code FROM currencies WHERE base != 1") as $erp_fx_row) {
            $erp_fx_known[] = strtoupper(trim((string) $erp_fx_row['code']));
        }

        foreach ((array) post_value('erp_fx_currencies') as $erp_fx_code) {
            $erp_fx_code = strtoupper(trim((string) $erp_fx_code));

            if (preg_match('/^[A-Z]{3}$/', $erp_fx_code) && in_array($erp_fx_code, $erp_fx_known, true) && !in_array($erp_fx_code, $erp_fx_codes, true)) {
                $erp_fx_codes[] = $erp_fx_code;
            }
        }

        $sql_erp_fx = "erp_fx_enabled = '" . ((post_value('erp_fx_enabled') == 1) ? 1 : 0) . "',
            erp_fx_currencies = '" . escape(substr(implode(',', $erp_fx_codes), 0, 64)) . "',
            erp_fx_auto_diff = '" . ((post_value('erp_fx_auto_diff') == 1) ? 1 : 0) . "',";
    }

    // Overdue receivable reminders (2026.4.4, 4.55). Whole days, ten years at
    // most; the recipient list keeps only well-formed addresses.
    $sql_erp_overdue = "";

    if (waf_table_has_column('config', 'erp_overdue_notify_days')) {
        $erp_overdue_recipients = array();

        foreach (explode(',', (string) post_value('erp_overdue_notify_recipients')) as $erp_overdue_address) {
            $erp_overdue_address = trim($erp_overdue_address);

            if (($erp_overdue_address !== '') && filter_var($erp_overdue_address, FILTER_VALIDATE_EMAIL) && !in_array($erp_overdue_address, $erp_overdue_recipients, true)) {
                $erp_overdue_recipients[] = $erp_overdue_address;
            }
        }

        $sql_erp_overdue = "erp_overdue_notify_days = '" . min(3650, max(0, (int) post_value('erp_overdue_notify_days'))) . "',
            erp_overdue_notify_panel = '" . ((post_value('erp_overdue_notify_panel') == 1) ? 1 : 0) . "',
            erp_overdue_notify_email = '" . ((post_value('erp_overdue_notify_email') == 1) ? 1 : 0) . "',
            erp_overdue_notify_push = '" . ((post_value('erp_overdue_notify_push') == 1) ? 1 : 0) . "',
            erp_overdue_notify_recipients = '" . escape(substr(implode(', ', $erp_overdue_recipients), 0, 500)) . "',
            erp_overdue_notify_frequency = '" . ((post_value('erp_overdue_notify_frequency') === 'weekly') ? 'weekly' : 'daily') . "',
            erp_overdue_notify_hour = '" . min(23, max(0, (int) post_value('erp_overdue_notify_hour'))) . "',";

        // The follow-up step (4.56) may not have run where 4.55 has.
        if (waf_table_has_column('config', 'erp_overdue_notify_customer')) {
            $sql_erp_overdue .= "
            erp_overdue_notify_customer = '" . ((post_value('erp_overdue_notify_customer') == 1) ? 1 : 0) . "',";
        }
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            enable_parasut = '" . escape(post_value('enable_parasut')) . "',
            parasut_tc_in_field = '" . escape(post_value('parasut_tc_in_field')) . "',
            parasut_client_id = '" . escape(trim(post_value('parasut_client_id'))) . "',
            parasut_credentials_enc = '" . escape(pg_parasut_credentials_for_save()) . "',
            parasut_username = '" . escape(trim(post_value('parasut_username'))) . "',
            parasut_company_id = '" . escape(function_exists('parasut_company_id_for_save') ? parasut_company_id_for_save(post_value('parasut_company_id'), defined('PARASUT_COMPANY_ID') ? PARASUT_COMPANY_ID : '') : trim((string) post_value('parasut_company_id'))) . "',
            parasut_default_product_id = '" . escape(trim(post_value('parasut_default_product_id'))) . "',
            parasut_default_warehouse_id = '" . escape(trim(post_value('parasut_default_warehouse_id'))) . "',
            erp_enabled = '" . escape(post_value('erp_enabled')) . "',
            erp_default_series = '" . escape(trim(post_value('erp_default_series'))) . "',
            erp_web_address = '" . escape(trim(post_value('erp_web_address'))) . "',
            " . $sql_erp_fx . "
            " . $sql_erp_due . "
            " . $sql_erp_overdue . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    $erp_settings_saved = erp_settings_cards_save($_POST, $erp_settings_cards);

    if (!$erp_settings_saved['success']) {
        $liveform->mark_error($erp_settings_saved['field'], h($erp_settings_saved['error']));
    }

    // A Paraşüt authorization code is exchanged by the card's Connect button;
    // one pasted and saved with the page is exchanged here as well, after the
    // boxes above are written, instead of being dropped without a word. A
    // refused code is a warning: the rest of the page has been saved. Notices
    // are printed as HTML, and the names and errors come from Paraşüt.
    $parasut_code = is_string(post_value('parasut_auth_code')) ? trim(post_value('parasut_auth_code')) : '';

    if (($parasut_code !== '') && function_exists('parasut_connect_with_code')) {
        $parasut_text = function ($key) {
            return is_string(post_value($key)) ? trim(post_value($key)) : '';
        };

        $parasut_connected = parasut_connect_with_code($parasut_code, $parasut_text('parasut_client_id'), $parasut_text('parasut_client_secret'), $parasut_text('parasut_company_id'));

        if (!empty($parasut_connected['success'])) {
            // A switch to the authorized user's one company is a warning.
            $parasut_report = parasut_connect_report($parasut_connected);

            foreach ($parasut_report['notices'] as $parasut_line) {
                $liveform->add_notice(h($parasut_line));
            }

            foreach ($parasut_report['warnings'] as $parasut_line) {
                $liveform->add_warning(h($parasut_line));
            }

            log_activity($parasut_report['log'], (string) ($_SESSION['sessionusername'] ?? ''));
        } else {
            $liveform->add_warning(h((string) $parasut_connected['error']));
        }
    }

