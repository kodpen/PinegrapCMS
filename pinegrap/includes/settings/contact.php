<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Communication cards: e-mail campaigns and live chat.
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

// ── Campaigns ──
$pg_settings_cards[] = '
                        <div id="pgset-campaigns" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Campaigns') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="pg-f-md">
                                            <label for="organization_name" class="form-label">' . lang('Organization Name') . '</label>
                                            <input type="text" name="organization_name" id="organization_name" class="form-control" value="' . h($organization_name) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_address_1" class="form-label">' . lang('Organization Address') . ' 1</label>
                                            <input type="text" name="organization_address_1" id="organization_address_1" class="form-control" value="' . h($organization_address_1) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_address_2" class="form-label">' . lang('Organization Address') . ' 2</label>
                                            <input type="text" name="organization_address_2" id="organization_address_2" class="form-control" value="' . h($organization_address_2) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_city" class="form-label">' . lang('Organization City') . '</label>
                                            <input type="text" name="organization_city" id="organization_city" class="form-control" value="' . h($organization_city) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_state" class="form-label">' . lang('Organization State') . '</label>
                                            <input type="text" name="organization_state" id="organization_state" class="form-control" value="' . h($organization_state) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_zip_code" class="form-label">' . lang('Organization Zip Code') . '</label>
                                            <input type="text" name="organization_zip_code" id="organization_zip_code" class="form-control" value="' . h($organization_zip_code) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_country" class="form-label">' . lang('Organization Country') . '</label>
                                            <input type="text" name="organization_country" id="organization_country" class="form-control" value="' . h($organization_country) . '"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="opt_in_label" class="form-label">' . lang('Opt-In Label') . '</label>
                                            <input type="text" name="opt_in_label" id="opt_in_label" class="form-control" value="' . h($opt_in_label) . '" maxlength="255"/>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="plain_text_email_campaign_footer" class="form-label">' . lang('Plain Text Footer') . '</label>
                                            <textarea name="plain_text_email_campaign_footer" id="plain_text_email_campaign_footer" class="form-control">' . h($plain_text_email_campaign_footer) . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Chat ──
$pg_settings_cards[] = '
                        <div id="pgset-chat" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Chat') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $chat_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="chat_enabled" name="chat_enabled" data-bs-target="#live_chat_row"/>
                                                <label class="form-check-label" for="chat_enabled">' . lang('Enable Live Chat') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="live_chat_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="pg-f-md">
                                                            <label for="chat_operator_user_id" class="form-label">' . lang('Site Chat Operator') . '</label>
                                                            <select name="chat_operator_user_id" id="chat_operator_user_id" class="form-select">' . $output_chat_operator_options . '</select>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="chat_retention_days" class="form-label">' . lang('Chat Retention Period') . '</label>
                                                            <select name="chat_retention_days" id="chat_retention_days" class="form-select">' . $output_chat_retention_options . '</select>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $chat_site_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="chat_site_enabled" name="chat_site_enabled" data-bs-target="#live_chat_site_row"/>
                                                                <label class="form-check-label" for="chat_site_enabled">' . lang('Enable Site Chat Bubble') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 " id="live_chat_site_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-3">
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
                                                                        <select name="chat_widget_theme" id="chat_widget_theme" class="form-select">' . $output_chat_theme_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-sm">
                                                                        <label for="chat_widget_color" class="form-label">' . lang('Widget Color') . '</label>
                                                                        <input type="color" name="chat_widget_color" id="chat_widget_color" class="form-control form-control-color w-100" value="' . h($chat_widget_color) . '" />
                                                                    </div>
                                                                    <div class="pg-f-sm">
                                                                        <label for="chat_widget_icon" class="form-label">' . lang('Widget Icon') . '</label>
                                                                        <select name="chat_widget_icon" id="chat_widget_icon" class="form-select">' . $output_chat_icon_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <div class="form-check form-switch">
                                                                            <input value="1"' . $chat_allow_images_checked . ' class="form-check-input" type="checkbox" id="chat_allow_images" name="chat_allow_images"/>
                                                                            <label class="form-check-label" for="chat_allow_images">' . lang('Allow image attachments') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <div class="form-check form-switch">
                                                                            <input value="1"' . $chat_allow_files_checked . ' class="form-check-input" type="checkbox" id="chat_allow_files" name="chat_allow_files"/>
                                                                            <label class="form-check-label" for="chat_allow_files">' . lang('Allow file attachments') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label for="chat_visitor_image_limit" class="form-label">' . lang('Visitor Image Limit') . '</label>
                                                                        <input type="number" name="chat_visitor_image_limit" id="chat_visitor_image_limit" class="form-control" value="' . (int) $chat_visitor_image_limit . '" min="1" max="20" />
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <label for="chat_upload_folder_id" class="form-label">' . lang('Folder for chat attachments') . '</label>
                                                                        <select name="chat_upload_folder_id" id="chat_upload_folder_id" class="form-select">' . select_folder($chat_upload_folder_id, 0) . '</select>
                                                                        <div class="form-text">' . lang('Everything people send in the chat is filed here, so it can be found in the File Manager afterwards.') . '</div>
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <div class="form-check form-switch">
                                                                            <input value="1"' . $chat_captcha_checked . ' class="form-check-input" type="checkbox" id="chat_captcha" name="chat_captcha"/>
                                                                            <label class="form-check-label" for="chat_captcha">' . lang('Require puzzle captcha for visitors') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 ">
                                                                        <div class="form-check form-switch">
                                                                            <input value="1"' . $chat_offline_email_checked . ' class="form-check-input" type="checkbox" id="chat_offline_email" name="chat_offline_email"/>
                                                                            <label class="form-check-label" for="chat_offline_email">' . lang('Email the operator when a message arrives while offline') . '</label>
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

// ── MailChimp ──
$pg_settings_cards[] = '
                        <div id="pgset-mailchimp" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('MailChimp') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="mailchimp" id="mailchimp" value="1"' . $mailchimp_checked . ' class="form-check-input collapse-switcher" data-bs-target="#mailchimp_row"/>
                                                <label class="form-check-label" for="mailchimp">' . lang('MailChimp Sync') . '<br/><span class="form-text">' . lang('Auto-export customers, orders, & products to MailChimp regularly. Requires cron job (job.php).') . '</span></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="mailchimp_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="pg-f-lg">
                                                            <label for="mailchimp_key" class="form-label">' . lang('API Key') . '</label>
                                                            <input type="text" name="mailchimp_key" id="mailchimp_key" class="form-control" value="' . h($mailchimp_key) . '" maxlength="100"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="mailchimp_list_id" class="form-label">' . lang('List ID') . '</label>
                                                            <input type="text" name="mailchimp_list_id" id="mailchimp_list_id" class="form-control" value="' . h($mailchimp_list_id) . '" maxlength="100"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="mailchimp_store_id" class="form-label">' . lang('Store ID') . '</label>
                                                            <input type="text" name="mailchimp_store_id" id="mailchimp_store_id" class="form-control" value="' . h($mailchimp_store_id) . '" maxlength="100"/>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="mailchimp_sync_days" class="form-label">' . lang('Historical Sync') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="mailchimp_sync_days" id="mailchimp_sync_days" class="form-control text-end" value="' . h($mailchimp_sync_days) . '" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal"/>
                                                                <span class="input-group-text">' . lang('days in the past') . '</span>
                                                            </div>
                                                            <div class="form-text">' . lang('Set how far in the past to sync orders. Leave blank to sync all historical orders.') . '</div>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="mailchimp_sync_limit" class="form-label">' . lang('Limit Sync') . '</label>
                                                            <input type="text" name="mailchimp_sync_limit" id="mailchimp_sync_limit" class="form-control text-end" value="' . h($mailchimp_sync_limit) . '" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal"/>
                                                            <div class="form-text">' . lang('max number of orders to sync each time cron job runs') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input type="checkbox" name="mailchimp_automation" id="mailchimp_automation" value="1"' . $mailchimp_automation_checked . ' class="form-check-input"/>
                                                                <label class="form-check-label" for="mailchimp_automation">' . lang('Automation') . '<br/><span class="form-text text-danger">' . lang('only enable after all historical orders have been synced, to start sending MailChimp automation campaigns') . '</span></label>
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
