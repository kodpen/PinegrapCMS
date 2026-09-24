<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Communication screen writes: e-mail campaigns and live chat.
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
    // ── MailChimp ──
    //
    // Everything here runs BEFORE the write and can stop it. A key, a list or
    // a store that Mailchimp refuses is the operator's mistake and not the
    // software's, so it is marked on the field rather than thrown: the dialog
    // then says which one and leaves on screen everything that was typed.
    //
    // Nothing in this category is written when that happens. Writing the rest
    // would leave the site's own settings saved and the integration's refused,
    // with one Save button reporting both.
    $mailchimp_post            = post_value('mailchimp') ? 1 : 0;
    $mailchimp_key_post        = trim((string) post_value('mailchimp_key'));
    $mailchimp_list_post       = trim((string) post_value('mailchimp_list_id'));
    $mailchimp_store_post      = trim((string) post_value('mailchimp_store_id'));
    $mailchimp_days_post       = trim((string) post_value('mailchimp_sync_days'));
    $mailchimp_limit_post      = trim((string) post_value('mailchimp_sync_limit'));
    $mailchimp_automation_post = post_value('mailchimp_automation') ? 1 : 0;

    if ($mailchimp_post) {

        if ($mailchimp_key_post === '') {
            $liveform->mark_error('mailchimp_key', lang(array('string' => '{var:1} is required.', 'vars' => lang('API Key'))));
        }

        if ($mailchimp_list_post === '') {
            $liveform->mark_error('mailchimp_list_id', lang(array('string' => '{var:1} is required.', 'vars' => lang('List ID'))));
        }

        if ($mailchimp_store_post === '') {
            $liveform->mark_error('mailchimp_store_id', lang(array('string' => '{var:1} is required.', 'vars' => lang('Store ID'))));
        }
    }

    if ($mailchimp_post && !$liveform->check_form_errors()) {

        require_once(PG_FUNCTIONS_DIR . '/mailchimp.php');

        // Is the key a key at all.
        $mailchimp_answer = mailchimp_request(array(
            'path' => '/ping',
            'key'  => $mailchimp_key_post));

        if ($mailchimp_answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_key', lang('Sorry, the API key is not valid') . '. ' . h($mailchimp_answer['message']));
        }
    }

    if ($mailchimp_post && !$liveform->check_form_errors()) {

        $mailchimp_answer = mailchimp_request(array(
            'path' => '/lists/' . $mailchimp_list_post . '?fields=id',
            'key'  => $mailchimp_key_post));

        if ($mailchimp_answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_list_id', lang('Sorry, the List ID is not valid') . '. ' . h($mailchimp_answer['message']));
        }
    }

    if ($mailchimp_post && !$liveform->check_form_errors()) {

        // A store that is not there yet is not an error -- it is what the
        // screen creates. Any other refusal is.
        $mailchimp_answer = mailchimp_request(array(
            'path'  => '/ecommerce/stores/' . $mailchimp_store_post . '?fields=id,is_syncing',
            'key'   => $mailchimp_key_post,
            'quiet' => true));

        $mailchimp_missing = (($mailchimp_answer['status'] == 'error')
            && ($mailchimp_answer['mailchimp_response']['status'] == 404));

        if (($mailchimp_answer['status'] == 'error') && !$mailchimp_missing) {
            $liveform->mark_error('mailchimp_store_id', lang('Sorry, the Store ID is not valid') . '. ' . h($mailchimp_answer['message']));

        } elseif ($mailchimp_missing) {

            $mailchimp_store = array(
                'id'            => $mailchimp_store_post,
                'list_id'       => $mailchimp_list_post,
                'name'          => HOSTNAME_SETTING,
                'platform'      => 'Pinegrap',
                'domain'        => HOSTNAME_SETTING,
                'is_syncing'    => ($mailchimp_automation_post ? false : true),
                'email_address' => EMAIL_ADDRESS,
                'currency_code' => BASE_CURRENCY_CODE,
            );

            $mailchimp_answer = mailchimp_request(array(
                'method' => 'post',
                'path'   => '/ecommerce/stores',
                'data'   => $mailchimp_store,
                'key'    => $mailchimp_key_post));

            if ($mailchimp_answer['status'] == 'error') {
                $liveform->mark_error('mailchimp_store_id', lang('Sorry, the store could not be created') . '. ' . h($mailchimp_answer['message']));
            }

        } else {
            $mailchimp_store = $mailchimp_answer['mailchimp_response'];
        }

        // is_syncing is the opposite of automation: while the store is syncing
        // its history, Mailchimp holds the automations back.
        if (!$liveform->check_form_errors()
            && isset($mailchimp_store['is_syncing'])
            && ($mailchimp_store['is_syncing'] == (bool) $mailchimp_automation_post)
        ) {
            $mailchimp_store['is_syncing'] = !$mailchimp_automation_post;

            $mailchimp_answer = mailchimp_request(array(
                'method' => 'patch',
                'path'   => '/ecommerce/stores/' . $mailchimp_store_post,
                'data'   => $mailchimp_store,
                'key'    => $mailchimp_key_post));

            if ($mailchimp_answer['status'] == 'error') {
                $liveform->mark_error('mailchimp_automation', lang('Sorry, automation could not be updated') . '. ' . h($mailchimp_answer['message']));
            }
        }
    }

    if ($liveform->check_form_errors()) {
        return;
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            organization_name = '" . escape(post_value('organization_name')) . "',
            organization_address_1 = '" . escape(post_value('organization_address_1')) . "',
            organization_address_2 = '" . escape(post_value('organization_address_2')) . "',
            organization_city = '" . escape(post_value('organization_city')) . "',
            organization_state = '" . escape(post_value('organization_state')) . "',
            organization_zip_code = '" . escape(post_value('organization_zip_code')) . "',
            organization_country = '" . escape(post_value('organization_country')) . "',
            opt_in_label = '" . escape(post_value('opt_in_label')) . "',
            plain_text_email_campaign_footer = '" . escape(trim(post_value('plain_text_email_campaign_footer'))) . "',
            mailchimp = '" . escape($mailchimp_post) . "',
            mailchimp_key = '" . escape($mailchimp_key_post) . "',
            mailchimp_list_id = '" . escape($mailchimp_list_post) . "',
            mailchimp_store_id = '" . escape($mailchimp_store_post) . "',
            mailchimp_sync_days = '" . escape($mailchimp_days_post) . "',
            mailchimp_sync_limit = '" . escape($mailchimp_limit_post) . "',
            mailchimp_automation = '" . escape($mailchimp_automation_post) . "',
            " . $sql_chat_settings . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");
