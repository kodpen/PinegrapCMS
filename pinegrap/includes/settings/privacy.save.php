<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Visitors and privacy screen writes: visitor tracking, cookie consent and the checkout's legal documents.
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

    // The notice prints the cookie policy address as a link, so only a site
    // path or a web address is taken (escape_url()); anything else is refused
    // before a column of this screen is written.
    $cookie_consent_policy_url_input = trim((string) post_value('cookie_consent_policy_url'));

    if (($cookie_consent_policy_url_input !== '') && (escape_url($cookie_consent_policy_url_input) === false)) {
        $liveform->mark_error('cookie_consent_policy_url', lang('The cookie policy address must start with / or https://.'));
        return;
    }

    // Visitor record retention (2026.4.10, 10.3): 0, or a whole number of
    // days within the bounds the module sets. Checked here, before anything
    // on this screen is written. Only when the form carried the field: a
    // missing one would read as 0 and quietly lift the limit.
    $visitor_retention_ready = pg_visitor_retention_ready() && array_key_exists('visitor_retention_days', $_POST);
    $visitor_retention_days_input = $visitor_retention_ready ? pg_visitor_retention_days_from_input(post_value('visitor_retention_days')) : 0;

    if ($visitor_retention_ready && ($visitor_retention_days_input === null)) {
        $liveform->mark_error('visitor_retention_days', lang(array('string' => 'Keep visitor records for 0 days (no limit), or for {var:1} to {var:2} days.', 'vars' => array(PG_VISITOR_RETENTION_MIN_DAYS, PG_VISITOR_RETENTION_MAX_DAYS))));
        return;
    }

    // The days after which a visit's IP address goes (2026.4.10, 10.8), the
    // same way: only when the form carried the field.
    $visitor_ip_ready = $visitor_retention_ready && pg_visitor_ip_ready() && array_key_exists('visitor_ip_days', $_POST);
    $visitor_ip_days_input = $visitor_ip_ready ? pg_visitor_ip_days_from_input(post_value('visitor_ip_days')) : 0;

    if ($visitor_ip_ready && ($visitor_ip_days_input === null)) {
        $liveform->mark_error('visitor_ip_days', lang(array('string' => 'Remove IP addresses after 0 days (keep them with the record), or after {var:1} to {var:2} days.', 'vars' => array(PG_VISITOR_IP_MIN_DAYS, PG_VISITOR_IP_MAX_DAYS))));
        return;
    }

    // Checkout legal documents (11.0.1). A template naming a field that does
    // not exist would print nothing where a customer expects a fact, and a
    // template past the column's size would be cut; either refuses the whole
    // save before anything below writes.
    $sql_legal_documents = '';
    // The KVKK consent sentence: a config_text row, written after the config update.
    $legal_kvkk_text = null;

    if (pg_schema_has('config', 'ecommerce_legal_documents')) {
        $legal_values = array();

        foreach (pg_legal_document_types() as $legal_type => $legal_label) {
            $legal_field = 'ecommerce_' . $legal_type . '_template';
            // Browsers post a textarea's line breaks as CRLF.
            $legal_value = trim(str_replace("\r\n", "\n", (string) post_value($legal_field)));

            // The built-in template is not stored, so a later improvement to
            // it reaches a store that never changed it.
            if ($legal_value === trim(str_replace("\r\n", "\n", pg_legal_document_default_template($legal_type, lang(array('info' => true)))))) {
                $legal_value = '';
            }

            $legal_unknown = pg_legal_document_unknown_placeholders($legal_value);

            if ($legal_unknown) {
                $liveform->mark_error($legal_field, lang(array('string' => '{var:1}: unknown field {var:2}.', 'vars' => array($legal_label, '^^' . implode('^^, ^^', array_map('h', $legal_unknown)) . '^^'))));
            } elseif (strlen($legal_value) > 65535) {
                $liveform->mark_error($legal_field, lang(array('string' => '{var:1} is too long.', 'vars' => array($legal_label))));
            }

            $legal_values[$legal_field] = $legal_value;
        }

        // The KVKK notice page and the explicit consent sentence (11.0.48).
        $legal_kvkk_sql = '';

        if (pg_schema_has('config', 'ecommerce_kvkk_page_id') && pg_schema_has('config_text')) {
            $legal_kvkk_text = trim(str_replace("\r\n", "\n", (string) post_value('ecommerce_kvkk_consent_text')));

            if (strlen($legal_kvkk_text) > 65535) {
                $liveform->mark_error('ecommerce_kvkk_consent_text', lang(array('string' => '{var:1} is too long.', 'vars' => array(lang('Explicit Consent Sentence')))));
            }

            $legal_kvkk_sql = "
            ecommerce_kvkk_page_id = '" . max(0, (int) post_value('ecommerce_kvkk_page_id')) . "',";
        }

        if ($liveform->check_form_errors()) {
            return;
        }

        $sql_legal_documents = $legal_kvkk_sql . "ecommerce_legal_documents = '" . ((post_value('ecommerce_legal_documents') == 1) ? 1 : 0) . "',";

        foreach ($legal_values as $legal_field => $legal_value) {
            $sql_legal_documents .= "
            " . $legal_field . " = " . (($legal_value === '') ? 'NULL' : "'" . escape($legal_value) . "'") . ",";
        }
    }

    // A changed retention period or summary switch clears the general job's
    // last look, so the change applies from its next tick.
    $sql_visitor_retention_fields = '';

    if ($visitor_retention_ready) {

        $visitor_summary_input = (post_value('visitor_summary') == '1') ? 1 : 0;

        $visitor_retention_current = pg_visitor_retention_settings();

        if (!is_array($visitor_retention_current)
            || ($visitor_retention_current['days'] !== $visitor_retention_days_input)
            || ($visitor_retention_current['summary'] !== ($visitor_summary_input === 1))
            || ($visitor_ip_ready && ($visitor_retention_current['ip_days'] !== $visitor_ip_days_input))
        ) {
            pg_visitor_cleanup_reset();
        }

        $sql_visitor_retention_fields =
            "visitor_retention_days = '" . (int) $visitor_retention_days_input . "',
            visitor_summary = '" . $visitor_summary_input . "',"
            . ($visitor_ip_ready ? "
            visitor_ip_days = '" . (int) $visitor_ip_days_input . "'," : "");
    }

    $sql_cookie_consent_fields = '';

    if (waf_table_has_column('config', 'cookie_consent')) {
        $sql_cookie_consent_fields =
            "cookie_consent = '" . ((post_value('cookie_consent') == '1') ? 1 : 0) . "',
            cookie_consent_policy_url = '" . escape($cookie_consent_policy_url_input) . "',";
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            $sql_visitor_retention_fields
            $sql_cookie_consent_fields
            $sql_legal_documents
            visitor_tracking = '" . escape(post_value('visitor_tracking')) . "',
            tracking_code_duration = '" . e(post_value('tracking_code_duration')) . "',
            pay_per_click_flag = '" . escape(post_value('pay_per_click_flag')) . "',
            stats_url = '" . escape(post_value('stats_url')) . "',
            google_analytics = '" . escape(post_value('google_analytics')) . "',
            google_analytics_web_property_id = '" . escape(post_value('google_analytics_web_property_id')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    if ($legal_kvkk_text !== null) {
        pg_config_text_save('ecommerce_kvkk_consent_text', $legal_kvkk_text);
    }

    // The accessibility statement's texts (includes/fn/widgets_accessibility.php):
    // one config_text row as JSON; all empty removes it.
    if (array_key_exists('a11y_statement_card', $_POST) && pg_schema_has('config_text')) {
        $a11y_statement_post = array();

        foreach (pg_a11y_statement_fields() as $a11y_field => $a11y_max) {
            $a11y_statement_post[$a11y_field] = (string) post_value('a11y_statement_' . $a11y_field);
        }

        $a11y_statement_post = pg_a11y_statement_clean($a11y_statement_post);
        pg_config_text_save('a11y_statement', (implode('', $a11y_statement_post) === '') ? '' : json_encode($a11y_statement_post, JSON_UNESCAPED_UNICODE));
    }

    // Google Analytics figures (includes/fn/google_analytics.php): choose a
    // GA4 property, read its figures now or stop using it. The messages
    // carry Google's own words, so they are escaped whole.
    if (array_key_exists('ga_card', $_POST)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

        foreach (pg_ga_save_from_screen(array(
            'property' => (string) post_value('ga_property'),
            'refresh'  => (bool) post_value('ga_refresh'),
            'unlink'   => (bool) post_value('ga_unlink'),
        )) as $ga_message) {
            if ($ga_message[0] === 'error') {
                $liveform->add_error(h($ga_message[1]));
            } else {
                $liveform->add_notice(h($ga_message[1]));
            }
        }
    }
