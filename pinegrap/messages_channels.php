<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Channels: the ways the site sends (e-mail, SMS, WhatsApp,
 * İYS) as cards with their state, each leading to its settings. The e-mail
 * card's state is the sending domain's health. Role <= 2, as the settings
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

$urls = array(
    'email'    => 'smtp_settings.php',
    'sms'      => pg_settings_link('contact', 'pgset-sms'),
    'whatsapp' => pg_settings_link('contact', 'pgset-whatsapp'),
    'iys'      => pg_settings_link('contact', 'pgset-iys'),
);

echo pg_messages_tab_page(
    'channels',
    $user,
    lang('The ways the site sends messages and where each is set.'),
    pg_messages_channel_cards_html(pg_messages_channel_cards(pg_messages_channel_facts(), $urls))
);
