<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates: e-mail designs, SMS texts and WhatsApp
 * templates in one tab. The SMS texts are listed with their state and text
 * (includes/fn/messages_templates.php) and still edited on the SMS
 * Messages card; WhatsApp templates lead to their card, and e-mail
 * designs are announced until campaigns have their design page. The
 * settings' texts and templates are for role <= 2 only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_templates.php');

$user = validate_user();
validate_email_access($user);

$body = '';

// The SMS texts sit in the settings, so they are listed for role <= 2, who
// may open the card that edits them.
if ($user['role'] < 3) {
    $templates_url = pg_settings_link('contact', 'pgset-sms-messages');
    $templates_list = pg_list_table_printed(array(
        'list' => 'messages_templates_sms',
        'columns' => pg_messages_template_columns(),
        'rows' => pg_messages_template_sms_rows(
            pg_sms_message_events(),
            pg_sms_message_settings(),
            defined('ERP_ENABLED') && ERP_ENABLED,
            function_exists('pg_booking_ready') && pg_booking_ready(),
            $templates_url
        ),
    ));

    $body .= '
            <section class="pg-card mb-5">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <h2 class="pg-title-section mb-0">' . h(lang('SMS texts')) . '</h2>
                    <div class="pg-toolbar-grow"></div>
                    ' . pg_list_table_search_box('messages_templates_sms') . '
                    <a class="btn btn-sm btn-outline theme-secondary rounded-pill" href="' . h($templates_url) . '">' . h(lang('Edit the texts')) . '</a>
                </div>
                <p class="pg-text-2">' . h(lang('The texts the site sends on its own. A text left empty on the card is the built-in one, in the site\'s language.')) . '</p>
                ' . pg_list_table_html($templates_list['source'], $templates_list['page'], lang('SMS texts')) . '
            </section>';

    $body .= pg_messages_place_rows(lang('Where the templates are edited'), array(
        array('title' => lang('WhatsApp'), 'text' => lang('The approved WhatsApp message templates.'), 'url' => pg_settings_link('contact', 'pgset-whatsapp'), 'icon' => 'bi-whatsapp', 'tone' => 'teal'),
    ));
}

$body .= '<p class="pg-text-2">' . h(lang('E-mail designs for campaigns will be listed here.')) . '</p>'
    . pg_js_bundle_tags('assets/js/pg_list_table.min.js');

echo pg_messages_tab_page('templates', $user, lang('E-mail designs and message texts to start a send from.'), $body);
