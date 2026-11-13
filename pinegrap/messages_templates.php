<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates: e-mail designs, SMS texts and WhatsApp
 * templates in one tab, in look A's cards and rows. The SMS texts are
 * listed with their state and text (includes/fn/messages_templates.php)
 * and still edited on the SMS Messages card; e-mail designs and WhatsApp
 * templates are announced in a card of their own. The settings' texts and
 * the WhatsApp card are for role <= 2 only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_templates.php');

$user = validate_user();
validate_email_access($user);

$body = '';

// The SMS texts and the WhatsApp card sit in the settings, so they are for
// role <= 2, who may open them.
if ($user['role'] < 3) {
    $body .= pg_messages_template_sms_card(
        pg_messages_template_sms_rows(
            pg_sms_message_events(),
            pg_sms_message_settings(),
            defined('ERP_ENABLED') && ERP_ENABLED,
            function_exists('pg_booking_ready') && pg_booking_ready(),
            pg_sms_queue_list_kinds()
        ),
        pg_settings_link('contact', 'pgset-sms-messages')
    );
}

$body .= pg_messages_template_coming_card(($user['role'] < 3) ? pg_settings_link('contact', 'pgset-whatsapp') : '');

echo pg_messages_tab_page('templates', $user, lang('E-mail designs and message texts to start a send from.'), $body);
