<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Channels: the ways the site sends (e-mail sender, SMS,
 * WhatsApp, İYS), each leading to its settings. Role <= 2, as the settings
 * themselves.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

$user = validate_user();
validate_area_access($user, 'manager');

$rows = array(
    array('title' => lang('E-mail sender'), 'text' => lang('The mail server and address every e-mail leaves from.'), 'url' => 'smtp_settings.php', 'icon' => 'bi-envelope-at', 'tone' => 'blue'),
    array('title' => lang('SMS'), 'text' => lang('The SMS provider and sender name.'), 'url' => pg_settings_link('contact', 'pgset-sms'), 'icon' => 'bi-chat-dots', 'tone' => 'amber'),
    array('title' => lang('WhatsApp'), 'text' => lang('The WhatsApp Business account and number.'), 'url' => pg_settings_link('contact', 'pgset-whatsapp'), 'icon' => 'bi-whatsapp', 'tone' => 'teal'),
    array('title' => lang('İYS'), 'text' => lang('Commercial message consent kept in step with İYS.'), 'url' => pg_settings_link('contact', 'pgset-iys'), 'icon' => 'bi-shield-check', 'tone' => 'violet'),
);

echo pg_messages_tab_page(
    'channels',
    $user,
    lang('The ways the site sends messages and where each is set.'),
    pg_messages_place_rows(lang('Channels'), $rows)
);
