<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates: e-mail designs, SMS texts and WhatsApp
 * templates in one tab. Until that list is built it leads to where each is
 * edited today; SMS texts and WhatsApp templates sit in the settings, so
 * only for role <= 2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

$user = validate_user();
validate_email_access($user);

$rows = array();

if ($user['role'] < 3) {
    $rows[] = array('title' => lang('SMS Messages'), 'text' => lang('The texts sent for orders and back-in-stock notices.'), 'url' => pg_settings_link('contact', 'pgset-sms-messages'), 'icon' => 'bi-chat-square-text', 'tone' => 'amber');
    $rows[] = array('title' => lang('WhatsApp'), 'text' => lang('The approved WhatsApp message templates.'), 'url' => pg_settings_link('contact', 'pgset-whatsapp'), 'icon' => 'bi-whatsapp', 'tone' => 'teal');
}

$body = pg_messages_place_rows(lang('Where the templates are edited'), $rows)
    . '<p class="pg-text-2">' . h(lang('E-mail designs for campaigns will be listed here.')) . '</p>';

echo pg_messages_tab_page('templates', $user, lang('E-mail designs and message texts to start a send from.'), $body);
