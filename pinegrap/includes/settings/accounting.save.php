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

if (isset($_POST['stock_plan_safety_days'], $_POST['stock_plan_cover_days'], $_POST['stock_plan_dead_days'])) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');

    if (erp_stock_plan_cache_ready()) {
        $erp_settings_cards[] = 'stock_plan';
    }
}

// A refused value stops the whole save before anything is written.
foreach (erp_settings_cards_refusals($_POST, $erp_settings_cards) as $erp_settings_field => $erp_settings_error) {
    $liveform->mark_error($erp_settings_field, h($erp_settings_error));
}

// The invoice series starts every document number: Latin capitals and
// digits only (the tax authority refuses anything else on an e-document),
// stored upper-cased. Empty is refused while the module is on, as issuing
// would then stop at "No invoice series is set."
$erp_default_series_value = strtoupper(trim((string) post_value('erp_default_series')));

if (
    (post_value('erp_default_series') !== null)
    && (!preg_match('/^[A-Z0-9]{0,10}$/', $erp_default_series_value) || (($erp_default_series_value === '') && (post_value('erp_enabled') == 1)))
) {
    $liveform->mark_error('erp_default_series', lang('Enter the invoice series in 1 to 10 Latin letters or digits, for example PGF.'));
}

// The overdue reminder recipients: every address is checked, and a wrong one
// is named rather than dropped without a word.
foreach (explode(',', (string) post_value('erp_overdue_notify_recipients')) as $erp_overdue_address) {
    foreach (pg_email_field_errors(array('erp_overdue_notify_recipients' => trim($erp_overdue_address))) as $erp_overdue_error) {
        $liveform->mark_error('erp_overdue_notify_recipients', $erp_overdue_error);
        break 2;
    }
}

if ($liveform->check_form_errors()) {
    return;
}

    // The e-document provider (4.61): the picked provider becomes the
    // active one while it is connected; its credentials are kept on its page
    // of Connections. One not connected is refused with a warning and the
    // provider in use stays; the rest of the card is saved.
    //
    // The provider is one string: one missing or posted as an array
    // (edoc_provider[]=x) is not given, and the provider stays as it is.
    if (waf_table_has_column('erp_edoc_providers', 'provider') && is_string(post_value('edoc_provider'))) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

        $erp_edoc_picked = post_value('edoc_provider');

        if (($erp_edoc_picked !== '') && !array_key_exists($erp_edoc_picked, erp_edoc_drivers())) {
            $erp_edoc_picked = '';
        }

        $erp_edoc_info = ($erp_edoc_picked !== '') ? erp_edoc_info($erp_edoc_picked) : null;
        $erp_edoc_pick = erp_edoc_pick_decide($erp_edoc_picked, erp_edoc_active(), ($erp_edoc_picked !== '') && erp_edoc_connected($erp_edoc_picked),
            is_array($erp_edoc_info) ? $erp_edoc_info['label'] : $erp_edoc_picked);

        if ($erp_edoc_pick['refusal'] !== '') {
            $liveform->add_warning(h($erp_edoc_pick['refusal']));
        } elseif ($erp_edoc_pick['activate']) {
            erp_edoc_activate($erp_edoc_picked);
            log_activity(lang(array('string' => 'erp e-document provider set to {var:1}', 'vars' => (($erp_edoc_picked === '') ? 'none' : $erp_edoc_picked))), $_SESSION['sessionusername']);
        }
    }

    // The order screen's Paraşüt buttons are switched on only while Paraşüt
    // is connected; switching them off is always taken.
    $parasut_enable = (post_value('enable_parasut') == 1) ? 1 : 0;
    $parasut_status = parasut_connection_status();

    if ($parasut_enable && !(defined('ENABLE_PARASUT') && ((int) ENABLE_PARASUT === 1))
        && empty($parasut_status['connected']) && empty($parasut_status['renewable'])) {
        $parasut_enable = 0;
        $liveform->add_warning(h(lang(array(
            'string' => '{var:1} is not connected, so it was not switched on. Connect it on its page of Connections, then switch it on here.',
            'vars' => array('Paraşüt'),
        ))));
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
            erp_notify_collection_min = '" . (min(100000000, max(0, (int) round((float) str_replace(',', '.', (string) post_value('erp_notify_collection_min'))))) * 100) . "',
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
        // the base currency and kept in minor units; a typed fraction is
        // rounded, never cut.
        if (waf_table_has_column('config', 'erp_auto_invoice_tax_number_only') && waf_table_has_column('config', 'erp_auto_invoice_min_total')) {
            $sql_erp_due .= "
            erp_auto_invoice_tax_number_only = '" . ((post_value('erp_auto_invoice_tax_number_only') == 1) ? 1 : 0) . "',
            erp_auto_invoice_min_total = '" . (min(10000000, max(0, (int) round((float) str_replace(',', '.', (string) post_value('erp_auto_invoice_min_total'))))) * 100) . "',";
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
            enable_parasut = '" . $parasut_enable . "',
            parasut_tc_in_field = '" . escape(post_value('parasut_tc_in_field')) . "',
            parasut_default_product_id = '" . escape(trim(post_value('parasut_default_product_id'))) . "',
            parasut_default_warehouse_id = '" . escape(trim(post_value('parasut_default_warehouse_id'))) . "',
            erp_enabled = '" . escape(post_value('erp_enabled')) . "',
            erp_default_series = '" . escape($erp_default_series_value) . "',
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

    // The daily stock record starts the night the module is on: its job joins
    // the general job's list when the general job runs the jobs.
    if ((post_value('erp_enabled') == 1) && defined('PG_ERP_ENTRY')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');
        erp_stock_plan_job_switch_on();
    }

