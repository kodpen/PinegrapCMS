<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Communication cards: live chat, SMS, WhatsApp and
 * İYS. The campaign texts and MailChimp are E-mail sending's
 * (includes/settings/mail_sending.php).
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
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

// ── Chat ──
$pg_settings_cards[] = '
                        <div id="pgset-chat" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Chat') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">' . pg_settings_tile('chat-dots', 'team') . '
                                                <div class="switch"><input value="1"' . $chat_enabled_checked . ' class="collapse-switcher" type="checkbox" id="chat_enabled" name="chat_enabled" data-bs-target="#live_chat_row"/></div>
                                                <label class="" for="chat_enabled">' . lang('Enable Live Chat') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="live_chat_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">
                                                        <div class="pg-f-md">
                                                            <label for="chat_operator_user_id" class="form-label">' . lang('Site Chat Operator') . '</label>
                                                            <select name="chat_operator_user_id" id="chat_operator_user_id" class="form-control" data-pg-combobox>' . $output_chat_operator_options . '</select>' . pg_fields_script() . '
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="chat_retention_days" class="form-label">' . lang('Chat Retention Period') . '</label>
                                                            <select name="chat_retention_days" id="chat_retention_days" class="form-control">' . $output_chat_retention_options . '</select>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $chat_site_enabled_checked . ' class="collapse-switcher" type="checkbox" id="chat_site_enabled" name="chat_site_enabled" data-bs-target="#live_chat_site_row"/></div>
                                                                <label class="" for="chat_site_enabled">' . lang('Enable Site Chat Bubble') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 " id="live_chat_site_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-5">
                                                                    <div class="col-12 ">
                                                                        <label for="chat_widget_title" class="form-label">' . lang('Chat Widget Label') . '</label>
                                                                        <input type="text" name="chat_widget_title" id="chat_widget_title" class="form-control" value="' . h($chat_widget_title) . '" maxlength="100" placeholder="' . h(lang('Live Support')) . '" />
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <label for="chat_welcome_message" class="form-label">' . lang('Chat Welcome Message') . '</label>
                                                                        <input type="text" name="chat_welcome_message" id="chat_welcome_message" class="form-control" value="' . h($chat_welcome_message) . '" maxlength="500" />
                                                                    </div>
                                                                    <div class="pg-f-sm">
                                                                        <label for="chat_widget_theme" class="form-label">' . lang('Widget Theme') . '</label>
                                                                        <select name="chat_widget_theme" id="chat_widget_theme" class="form-control">' . $output_chat_theme_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-sm">
                                                                        <label for="chat_widget_color" class="form-label">' . lang('Widget Color') . '</label>
                                                                        <input type="color" name="chat_widget_color" id="chat_widget_color" class="form-control form-control-color w-100" value="' . h($chat_widget_color) . '" />
                                                                    </div>
                                                                    <div class="pg-f-sm">
                                                                        <label for="chat_widget_icon" class="form-label">' . lang('Widget Icon') . '</label>
                                                                        <select name="chat_widget_icon" id="chat_widget_icon" class="form-control">' . $output_chat_icon_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <div class="form-field">
                                                                            <div class="switch"><input value="1"' . $chat_allow_images_checked . ' class="" type="checkbox" id="chat_allow_images" name="chat_allow_images"/></div>
                                                                            <label class="" for="chat_allow_images">' . lang('Allow image attachments') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <div class="form-field">
                                                                            <div class="switch"><input value="1"' . $chat_allow_files_checked . ' class="" type="checkbox" id="chat_allow_files" name="chat_allow_files"/></div>
                                                                            <label class="" for="chat_allow_files">' . lang('Allow file attachments') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <div class="form-field">
                                                                            <div class="switch"><input value="1"' . $chat_allow_audio_checked . ' class="" type="checkbox" id="chat_allow_audio" name="chat_allow_audio"/></div>
                                                                            <label class="" for="chat_allow_audio">' . lang('Allow voice messages and audio files') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label for="chat_visitor_image_limit" class="form-label">' . lang('Visitor Image Limit') . '</label>
                                                                        <input type="number" name="chat_visitor_image_limit" id="chat_visitor_image_limit" class="form-control" value="' . (int) $chat_visitor_image_limit . '" min="1" max="20" />
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <label for="chat_upload_folder_id" class="form-label">' . lang('Folder for chat attachments') . '</label>
                                                                        <select name="chat_upload_folder_id" id="chat_upload_folder_id" class="form-control">' . select_folder($chat_upload_folder_id, 0) . '</select>
                                                                        <div class="form-text">' . lang('Everything people send in the chat is filed here, so it can be found in the File Manager afterwards.') . '</div>
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <div class="form-field">
                                                                            <div class="switch"><input value="1"' . $chat_captcha_checked . ' class="" type="checkbox" id="chat_captcha" name="chat_captcha"/></div>
                                                                            <label class="" for="chat_captcha">' . lang('Require puzzle captcha for visitors') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <div class="form-field">
                                                                            <div class="switch"><input value="1"' . $chat_offline_email_checked . ' class="" type="checkbox" id="chat_offline_email" name="chat_offline_email"/></div>
                                                                            <label class="" for="chat_offline_email">' . lang('Email the operator when a message arrives while offline') . '</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';

// ── SMS ──
// The account is kept with the connected accounts' applications, the password
// sealed (includes/fn/sms.php).
$pg_settings_cards[] = '
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
                        </div>';

$pg_settings_scripts[] = pg_sms_settings_script();

// ── SMS messages ──
// The order's own text messages: a switch and a template per event
// (includes/fn/sms_messages.php).
$pg_settings_cards[] = '
                        <div id="pgset-sms-messages" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('SMS Messages') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_sms_message_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── WhatsApp ──
// The site's own WhatsApp Business account on Meta's Cloud API is set up on
// WhatsApp's page of Connections; the card shows the connection and keeps
// the Workspace's Conversations field (includes/fn/whatsapp.php).
$pg_settings_cards[] = '
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
                        </div>';

// ── WhatsApp messages ──
// The order's own WhatsApp messages: a switch, an approved template and what
// fills each of its variables per event (includes/fn/whatsapp_messages.php).
$pg_settings_cards[] = '
                        <div id="pgset-whatsapp-messages" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('WhatsApp Messages') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">' . pg_whatsapp_message_settings_fields() . '
                                    </div>
                                </div>
                            </div>
                        </div>';
$pg_settings_scripts[] = pg_whatsapp_message_settings_script();

// ── İYS ──
// The İYS account and the consent sync are set up on İYS's page of
// Connections; the card shows the connection (includes/iys/settings.php).
$pg_settings_cards[] = '
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
                        </div>';
