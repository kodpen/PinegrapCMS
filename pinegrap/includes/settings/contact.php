<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Communication cards: chat, chat on the site, SMS, SMS
 * messages, WhatsApp, WhatsApp messages and İYS. The campaign texts are
 * E-mail sending's (includes/settings/mail_sending.php); Mailchimp is set
 * up on its page of Connections.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/communication_settings_fold.php.
 * A switch's fields sit under it, drawn whatever its state. Fills
 * $pg_settings_cards with the one list of rows. Which cards belong here is
 * said once, in includes/settings/registry.php; this file holds them, and
 * contact.save.php writes what they edit, every field under its old name.
 * The variables they read are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/iys/settings.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_inbox.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/communication_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_contact_fold_order().
$contact_parts = array();

// A pg-switch with its label and one line of help, as every switch of the
// pane is drawn.
$contact_switch = function ($name, $checked, $label, $help = '') {
    return '
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-5">
                                                <input type="checkbox" class="pg-switch" value="1"' . $checked . ' id="' . $name . '" name="' . $name . '"' . (($help !== '') ? ' aria-describedby="' . $name . '_help"' : '') . '/>
                                                <label class="pg-text-2" for="' . $name . '">' . h($label) . '</label>
                                            </div>' . (($help !== '') ? '
                                            <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="' . $name . '_help">' . $help . '</p>' : '') . '
                                        </div>';
};

// ── Chat ──
// One switch for every chat: the panel's chat between team members and the
// site's (CHAT_ENABLED, init.php), and the folder their attachments go to.
$contact_parts['chat'] = array('html' => '
                        <div id="pgset-chat" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Chat') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-12">' . $contact_switch('chat_enabled', $chat_enabled_checked, lang('Chat'), h(lang('Team members chat with each other in the panel, and visitors on the site when the bubble is on. Off, no chat shows anywhere.'))) . '
                                        <div class="col-12">
                                            <label for="chat_upload_folder_id" class="form-label">' . lang('Folder for chat attachments') . '</label>
                                            <select name="chat_upload_folder_id" id="chat_upload_folder_id" class="form-control" aria-describedby="chat_upload_folder_id_help">' . select_folder($chat_upload_folder_id, 0) . '</select>
                                            <div class="form-text" id="chat_upload_folder_id_help">' . lang('Everything people send in the chat is filed here, so it can be found in the File Manager afterwards.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_chat($chat_enabled == 1);

// ── Chat on the site ──
// The bubble: who answers visitors, its texts and look, and what visitors
// who are not signed in may send (pg_chat_site_enabled(), chat.php). Drawn
// whatever Chat's switch says; the row's line says when nothing shows.
$contact_operator_name = ($chat_operator_user_id > 0) ? pg_user_picker_label($chat_operator_user_id) : '';

$contact_parts['chat-site'] = array('html' => '
                        <div id="pgset-chat-site" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Chat on the site') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-12">' . (($chat_enabled == 1) ? '' : '
                                        <div class="col-12"><p class="pg-field-help pg-text-meta fg-secondary mb-0">' . h(lang('Chat is off: nothing shows on the site')) . '</p></div>')
                                        . $contact_switch('chat_site_enabled', $chat_site_enabled_checked, lang('Chat bubble on the site')) . '
                                        <div class="pg-f-md">
                                            <label for="chat_operator_user_id" class="form-label">' . lang('Who answers visitors') . '</label>
                                            <select name="chat_operator_user_id" id="chat_operator_user_id" class="form-control" data-pg-combobox aria-describedby="chat_operator_user_id_help">' . $output_chat_operator_options . '</select>' . pg_fields_script() . '
                                        </div>
                                        <div class="col-12 mt-3">
                                            <div class="form-text mt-0" id="chat_operator_user_id_help">' . h(lang('Visitors who are not signed in see the bubble only while someone is chosen here. Signed-in members write to the team from their own chat list.')) . '</div>
                                        </div>' . $contact_switch('chat_offline_email', $chat_offline_email_checked, lang('Email the operator when a message arrives while offline')) . '
                                        <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('The bubble') . '</h3></div>
                                        <div class="col-12 md:col-6">
                                            <label for="chat_widget_title" class="form-label">' . lang('Bubble label') . '</label>
                                            <input type="text" name="chat_widget_title" id="chat_widget_title" class="form-control" value="' . h($chat_widget_title) . '" maxlength="100" placeholder="' . h(lang('Live Support')) . '" />
                                        </div>
                                        <div class="col-12 md:col-6">
                                            <label for="chat_welcome_message" class="form-label">' . lang('Welcome message') . '</label>
                                            <input type="text" name="chat_welcome_message" id="chat_welcome_message" class="form-control" value="' . h($chat_welcome_message) . '" maxlength="500" />
                                        </div>
                                        <div class="col-12 md:col-4">
                                            <label for="chat_widget_theme" class="form-label">' . lang('Theme') . '</label>
                                            <select name="chat_widget_theme" id="chat_widget_theme" class="form-control">' . $output_chat_theme_options . '</select>
                                        </div>
                                        <div class="col-12 md:col-4">
                                            <label for="chat_widget_color" class="form-label">' . lang('Colour') . '</label>
                                            <input type="color" name="chat_widget_color" id="chat_widget_color" class="form-control form-control-color w-100" value="' . h($chat_widget_color) . '" />
                                        </div>
                                        <div class="col-12 md:col-4">
                                            <label for="chat_widget_icon" class="form-label">' . lang('Icon') . '</label>
                                            <select name="chat_widget_icon" id="chat_widget_icon" class="form-control">' . $output_chat_icon_options . '</select>
                                        </div>
                                        <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Visitors who are not signed in') . '</h3></div>'
                                        . $contact_switch('chat_allow_images', $chat_allow_images_checked, lang('Images'))
                                        . $contact_switch('chat_allow_files', $chat_allow_files_checked, lang('Files'))
                                        . $contact_switch('chat_allow_audio', $chat_allow_audio_checked, lang('Voice messages')) . '
                                        <div class="pg-f-xs">
                                            <label for="chat_visitor_image_limit" class="form-label">' . lang('Images per conversation') . '</label>
                                            <input type="number" name="chat_visitor_image_limit" id="chat_visitor_image_limit" class="form-control" value="' . (int) $chat_visitor_image_limit . '" min="1" max="20" />
                                        </div>'
                                        . $contact_switch('chat_captcha', $chat_captcha_checked, lang('Ask a puzzle before the first message'), h(lang('Forms and sign-in have their own puzzle:')) . ' <a href="#settings/security/pgset-captcha">' . h(lang('Accounts and sign-in')) . '</a>') . '
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('Team members and signed-in members may always send files.')) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_chat_site($chat_enabled == 1, $chat_site_enabled == 1, $contact_operator_name);

// ── SMS ──
$contact_sms_providers = pg_sms_providers();
$contact_sms_provider = pg_sms_settings_provider();
$contact_sms_app = pg_sms_app($contact_sms_provider);

// The switch and the provider it sends through; each provider's account is
// set up on its page of Connections, and the card shows its connection
// (includes/fn/sms.php).
$contact_parts['sms'] = array('html' => '
                        <div id="pgset-sms" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('SMS') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_sms_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_sms($contact_sms_app['enabled'], pg_sms_app_complete($contact_sms_app), $contact_sms_providers[$contact_sms_provider]['label']);

$pg_settings_scripts[] = pg_sms_settings_script();

// ── SMS messages ──
$contact_sms_counts = array(0, 0);
$contact_erp_on = defined('ERP_ENABLED') && ERP_ENABLED;
$contact_booking_on = function_exists('pg_booking_ready') && pg_booking_ready();
$contact_sms_definitions = pg_sms_message_events();

foreach (pg_sms_message_settings() as $contact_event => $contact_event_row) {
    if (isset($contact_sms_definitions[$contact_event]) && pg_sms_message_event_shown($contact_sms_definitions[$contact_event], $contact_erp_on, $contact_booking_on)) {
        $contact_sms_counts[0] += $contact_event_row['on'] ? 1 : 0;
        $contact_sms_counts[1]++;
    }
}

// The order's own text messages: a switch and a template per event
// (includes/fn/sms_messages.php).
$contact_parts['sms-messages'] = array('html' => '
                        <div id="pgset-sms-messages" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('SMS messages') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_sms_message_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_messages(pg_sms_ready(), $contact_sms_counts[0], $contact_sms_counts[1]);

// ── WhatsApp ──
// The site's own WhatsApp Business account on Meta's Cloud API is set up on
// WhatsApp's page of Connections; the card shows the connection and keeps
// the Workspace's Conversations field (includes/fn/whatsapp.php).
$contact_parts['whatsapp'] = array('html' => '
                        <div id="pgset-whatsapp" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    <span lang="en">' . lang('WhatsApp') . '</span>
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_whatsapp_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_connection(pg_whatsapp_ready());

// ── WhatsApp messages ──
$contact_wa_counts = array(0, 0);

foreach (pg_whatsapp_message_settings() as $contact_event_row) {
    $contact_wa_counts[0] += !empty($contact_event_row['on']) ? 1 : 0;
    $contact_wa_counts[1]++;
}

// The order's own WhatsApp messages: a switch, an approved template and what
// fills each of its variables per event (includes/fn/whatsapp_messages.php).
$contact_parts['whatsapp-messages'] = array('html' => '
                        <div id="pgset-whatsapp-messages" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('WhatsApp messages') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_whatsapp_message_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_messages(pg_whatsapp_ready(), $contact_wa_counts[0], $contact_wa_counts[1]);
$pg_settings_scripts[] = pg_whatsapp_message_settings_script();

// ── İYS ──
$contact_iys_app = pg_iys_settings_before();

// The İYS account and the consent sync are set up on İYS's page of
// Connections; the card shows the connection (includes/iys/settings.php).
$contact_parts['iys'] = array('html' => '
                        <div id="pgset-iys" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('İYS') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_iys_settings_card_html() . '
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_contact_fold_connection($contact_iys_app['enabled'] && pg_iys_app_complete($contact_iys_app));

// ── The rows ──
$contact_rows = array();

foreach (pg_contact_fold_order() as $contact_key) {
    if (isset($contact_parts[$contact_key])) {
        $contact_rows[] = array('key' => $contact_key) + pg_contact_fold_face($contact_key) + $contact_parts[$contact_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($contact_rows);
