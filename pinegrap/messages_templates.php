<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates: e-mail designs, SMS texts and WhatsApp
 * templates in one tab, in look A's cards and rows. The SMS texts are
 * listed with their state and text (includes/fn/messages_templates.php)
 * and still edited on the SMS Messages card. The WhatsApp templates are
 * listed as read from Meta, with their type, language, approval state and
 * a rejection's reason; Refresh reads them again. E-mail designs are
 * announced in a card of their own. The settings' texts, Refresh and the
 * WhatsApp card are for role <= 2 only.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
include_once('liveform.class.php');

$user = validate_user();
validate_email_access($user);

$liveform = new liveform('messages_templates');
$templates_manager = ((int) $user['role'] <= 2);
$templates_self = PATH . SOFTWARE_DIRECTORY . '/messages_templates.php';

if ($_POST) {

    validate_token_field();

    $templates_action = (isset($_POST['templates_action']) && is_string($_POST['templates_action'])) ? $_POST['templates_action'] : '';

    if (($templates_action === 'whatsapp_refresh') && $templates_manager) {

        // A refresh reads up to ten pages from Meta, so a site gets a
        // ceiling per hour in the firewall's rate buckets (fails open when
        // they are not there); the general job reads on its own anyway.
        if (function_exists('waf_rate_exceeded') && isset(db::$con) && db::$con
            && waf_rate_exceeded('site', 'whatsapp-templates', 30, 3600)) {
            $liveform->add_warning(lang('The templates were not read: the limit of thirty reads an hour was reached. Try again in the next hour.'));
        } else {
            $templates_read = pg_whatsapp_templates_refresh();

            if ($templates_read['ok']) {
                $liveform->add_notice(lang(array(
                    'string' => 'The templates were read from Meta: {var:1} added, {var:2} updated, {var:3} removed.',
                    'vars'   => array($templates_read['added'], $templates_read['changed'], $templates_read['removed']),
                )));
            } else {
                $liveform->add_warning($templates_read['error']);
            }
        }
    }

    go($templates_self);
}

$body = $liveform->output_errors() . $liveform->get_warnings() . $liveform->output_notices();

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

// The WhatsApp templates: what was read from Meta, for everyone who may
// open the tab; Refresh and the way to the card for role <= 2.
$templates_settings = $templates_manager ? pg_settings_link('contact', 'pgset-whatsapp') : '';
$templates_upgraded = pg_whatsapp_templates_ready();
$templates_set_up = pg_whatsapp_ready();
$templates_sync = $templates_upgraded ? pg_whatsapp_templates_sync() : array('checked' => 0, 'ok' => false, 'error' => '');
$templates_demo = function_exists('pg_demo') && pg_demo();

if (!$templates_upgraded) {
    $templates_sub = lang('The WhatsApp templates are not available yet; run the upgrade.');
} elseif ($templates_demo) {
    $templates_sub = lang('A demonstration site does not read WhatsApp templates.');
} elseif (!$templates_set_up) {
    $templates_sub = lang('Connect WhatsApp to read the account\'s message templates from Meta.');
} elseif ($templates_sync['checked'] > 0) {
    $templates_sub = lang(array(
        'string' => 'Read from Meta every six hours, last {var:1}.',
        'vars'   => array(get_relative_time(array('timestamp' => $templates_sync['checked'], 'format' => 'plain_text', 'mid_sentence' => true))),
    ));
} else {
    $templates_sub = lang('Read from Meta every six hours; not read yet.');
}

$body .= pg_messages_template_whatsapp_card(
    pg_messages_template_whatsapp_rows($templates_upgraded ? pg_whatsapp_templates_list() : array()),
    array(
        'sub' => $templates_sub,
        'error' => ($templates_upgraded && $templates_set_up && !$templates_sync['ok'] && ($templates_sync['checked'] > 0)) ? $templates_sync['error'] : '',
        'refresh' => ($templates_manager && $templates_upgraded && $templates_set_up && !$templates_demo) ? $templates_self : '',
        'token' => get_token_field(),
        'settings' => $templates_settings,
        'empty' => ($templates_upgraded && $templates_set_up) ? lang('No templates on the account yet. Templates made in WhatsApp Manager show here once Meta lists them.') : '',
    )
);

$body .= pg_messages_template_coming_card();

echo pg_messages_tab_page('templates', $user, lang('E-mail designs and message texts to start a send from.'), $body);

$liveform->remove_form();
