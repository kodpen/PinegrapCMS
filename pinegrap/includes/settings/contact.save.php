<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Communication screen writes: live chat, SMS,
 * WhatsApp and İYS.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_inbox.php');



    $sql_chat_settings = '';

    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'chat_enabled')
    ) {
        $chat_post_retention = (int) post_value('chat_retention_days');

        if (!in_array($chat_post_retention, array(7, 15, 30, 60, 90, 180), true)) {
            $chat_post_retention = 60;
        }

        $sql_chat_settings =
            "chat_enabled = '" . escape(post_value('chat_enabled') ? 1 : 0) . "',
             chat_site_enabled = '" . escape(post_value('chat_site_enabled') ? 1 : 0) . "',
             chat_operator_user_id = '" . escape((int) post_value('chat_operator_user_id')) . "',
             chat_welcome_message = '" . escape(mb_substr((string) post_value('chat_welcome_message'), 0, 500)) . "',
             chat_offline_email = '" . escape(post_value('chat_offline_email') ? 1 : 0) . "',
             chat_captcha = '" . escape(post_value('chat_captcha') ? 1 : 0) . "',
             chat_retention_days = " . $chat_post_retention . ",";

        if (waf_table_has_column('config', 'chat_widget_theme')) {
            $chat_post_theme = post_value('chat_widget_theme');

            if (!in_array($chat_post_theme, array('auto', 'light', 'dark'), true)) {
                $chat_post_theme = 'auto';
            }

            $chat_post_color = (string) post_value('chat_widget_color');

            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $chat_post_color)) {
                $chat_post_color = '#0d6efd';
            }

            $chat_post_icon = post_value('chat_widget_icon');

            if (!in_array($chat_post_icon, array('chat', 'support', 'help'), true)) {
                $chat_post_icon = 'chat';
            }

            $sql_chat_settings .=
                "chat_widget_theme = '" . escape($chat_post_theme) . "',
                 chat_widget_color = '" . escape($chat_post_color) . "',
                 chat_widget_icon = '" . escape($chat_post_icon) . "',";
        }

        // Attachment permissions arrive with 2026.4.2; separate guard.
        if (waf_table_has_column('config', 'chat_allow_files')) {
            $sql_chat_settings .=
                "chat_allow_files = '" . escape(post_value('chat_allow_files') ? 1 : 0) . "',
                 chat_allow_images = '" . escape(post_value('chat_allow_images') ? 1 : 0) . "',";
        }

        // Voice messages and audio files arrive with 2026.4.4; its own guard
        // so an installation between the two upgrades still saves the rest.
        if (waf_table_has_column('config', 'chat_allow_audio')) {
            $sql_chat_settings .=
                "chat_allow_audio = '" . escape(post_value('chat_allow_audio') ? 1 : 0) . "',";
        }

        // The visitor image limit arrives with 2026.4.2; clamped to 1-20.
        if (waf_table_has_column('config', 'chat_visitor_image_limit')) {
            $chat_post_image_limit = max(1, min(20, (int) post_value('chat_visitor_image_limit')));

            $sql_chat_settings .=
                "chat_visitor_image_limit = " . $chat_post_image_limit . ",";
        }

        // The widget label arrives with 2026.4.2; empty = language file
        // default.
        if (waf_table_has_column('config', 'chat_widget_title')) {
            $sql_chat_settings .=
                "chat_widget_title = '" . escape(mb_substr(trim((string) post_value('chat_widget_title')), 0, 100)) . "',";
        }

        // Where an attachment is filed (2026.4.4). Zero means "not chosen" and
        // the code then falls back to the top folder.
        if (waf_table_has_column('config', 'chat_upload_folder_id')) {
            $sql_chat_settings .=
                "chat_upload_folder_id = '" . escape((int) post_value('chat_upload_folder_id')) . "',";
        }
    }

    // ── SMS ──
    //
    // Checked before anything is written: a changed account is asked about at
    // the provider, and a refusal stops the whole category.
    $sms_settings_post = pg_sms_settings_check($_POST, $liveform);
    $sms_messages_post = pg_sms_message_settings_check($_POST, $liveform);

    // ── WhatsApp ──
    //
    // Same reason as the SMS account: a changed account is asked about at
    // Meta before anything is written.
    $whatsapp_settings_post = pg_whatsapp_settings_check($_POST, $liveform);
    $whatsapp_messages_post = pg_whatsapp_message_settings_check($_POST, $liveform);
    $whatsapp_channel_post = pg_whatsapp_channel_check($_POST, USER_ID, $liveform);
    $sms_channel_post = pg_sms_channel_check($_POST, USER_ID, $liveform);

    // ── İYS ──
    //
    // Same reason as the SMS account: a changed account is asked about at
    // İYS before anything is written.
    $iys_settings_post = pg_iys_settings_check($_POST, $liveform);

    if ($liveform->check_form_errors()) {
        return;
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            " . $sql_chat_settings . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // The SMS account goes to the connected accounts' applications, after the
    // config row; the test message, when its button was pressed, after that.
    pg_sms_settings_save($sms_settings_post, USER_ID, $liveform);
    pg_sms_message_settings_save($sms_messages_post, USER_ID);
    pg_sms_channel_save($sms_channel_post, USER_ID);
    pg_whatsapp_settings_save($whatsapp_settings_post, USER_ID, $liveform);
    pg_whatsapp_channel_save($whatsapp_channel_post, USER_ID);
    pg_whatsapp_message_settings_save($whatsapp_messages_post, USER_ID);
    pg_iys_settings_save($iys_settings_post, USER_ID, $liveform);
