<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail › E-mail sending tab as folded rows: How e-mail
 * leaves (its ways sheet holds the system e-mail's account, sending service
 * and mail server), Sending domain, the DKIM record, Campaign mail,
 * MailChimp and Send a test. The rows' lines are includes/fn/
 * mail_sending_rows.php; what the cards save and run is includes/fn/
 * mail_settings.php, config.php defines read here from the constants; the
 * campaign texts and MailChimp are config row columns, prepared by
 * includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_sending_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// The search index draws every category to read its labels: the test e-mail
// and the Connect message wait for the pane or the page that shows them.
if (empty($_GET['search'])) {

    // "Save and send a test": the save wrote config.php, and this request's
    // constants hold what it saved.
    pg_mail_settings_send_test($liveform);

    $mail_flash = pg_connect_flash_take();

    if ($mail_flash) {
        if ($mail_flash['kind'] === 'error') {
            $liveform->add_error(h($mail_flash['text']));
        } else {
            $liveform->add_notice(h($mail_flash['text']));
        }
    }
}

$mail_ready = pg_mail_settings_ready();
$mail_accounts = pg_mail_sending_accounts();
$mail_providers = pg_mail_settings_providers();

// A define's value as the box shows it. The passwords are never drawn back:
// an empty box keeps the saved one, and the placeholder says one is saved.
$mail_value = function ($key) {
    return defined($key) ? h((string) constant($key)) : '';
};

$mail_kept = h(lang('Leave blank to keep the saved secret'));
$mail_system_kept = (defined('SYSTEM_SMTP_PASSWORD') && ((string) SYSTEM_SMTP_PASSWORD !== '')) ? ' placeholder="' . $mail_kept . '"' : '';
$mail_campaign_kept = (defined('CAMPAIGN_SMTP_PASSWORD') && ((string) CAMPAIGN_SMTP_PASSWORD !== '')) ? ' placeholder="' . $mail_kept . '"' : '';
$mail_campaign_on = (defined('EMAIL_CAMPAIGN_JOB') && ((EMAIL_CAMPAIGN_JOB === true) || (EMAIL_CAMPAIGN_JOB === 'true')));

$mail_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';
$mail_parts = array();

// ── How e-mail leaves ──
// A row a kind of mail, saying how it leaves today (pg_mail_way_read()).
// System e-mail's Change opens the ways sheet; the campaigns' account and
// server are still the Campaign mail row's.
$mail_system_way = pg_mail_way_read('system');
$mail_campaign_way = pg_mail_way_read('campaign');

$mail_way_row = function ($tone, $icon, $name, $line, $button) {
    return '
                                        <div class="pg-value-row">
                                            <span class="pg-icon-tile pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>
                                            <span class="pg-value-row-text"><span class="pg-value-row-name">' . h($name) . '</span><span class="pg-value-row-line">' . h($line) . '</span></span>
                                            ' . $button . '
                                        </div>';
};

$mail_change = function ($name, $target) {
    return '<button type="button" class="pg-button pg-button-quiet pg-button-sm" ' . $target . ' aria-label="' . h(lang('Change') . ': ' . $name) . '">' . h(lang('Change')) . '</button>';
};

$mail_parts['leaves'] = array('html' => '
                        <div id="pgset-mail-system" class="pg-set-card">
                            <div class="card" id="mail_transport_card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('How e-mail leaves') . '
                                </div>
                                <div class="card-body">
                                    <div class="pg-card-inner">'
    . $mail_way_row('blue', 'bi-envelope-check', lang('System e-mail'), $mail_system_way['sentence'], $mail_change(lang('System e-mail'), 'data-bs-toggle="drawer" data-bs-target="#pgset-mail-way" aria-controls="pgset-mail-way"'))
    . $mail_way_row('violet', 'bi-megaphone', lang('Campaigns'), $mail_campaign_way['sentence'], $mail_change(lang('Campaigns'), 'data-pg-set-goto="pgset-mail-campaign"')) . '
                                    </div>
                                    <div class="pg-field-help">' . h(lang('System e-mail is order, form, password and notice mail; campaigns are bulk sends.')) . '</div>
                                </div>
                            </div>
                        </div>') + pg_mail_sending_fold_leaves($mail_system_way, $mail_campaign_way, $mail_ready);

// The ways sheet of system e-mail: four choice cards, then only the chosen
// way's fields. It sits outside the settings form, so every field is joined
// to it by its form attribute; the pane's Save writes it.
$mail_way = pg_mail_way_chosen(pg_mail_connection('system'), pg_mail_way_enabled_service());
$mail_system_account = defined('SYSTEM_MAIL_CONNECTION') ? (int) SYSTEM_MAIL_CONNECTION : 0;
$mail_way_panes = '';

foreach (array('google', 'microsoft') as $mail_provider_key) {
    $mail_select = pg_mail_way_account_select($mail_provider_key, $mail_accounts, $mail_system_account, $mail_way === $mail_provider_key, $mail_form);

    $mail_way_panes .= '
                    <div class="mt-5" data-mail-way-pane="' . $mail_provider_key . '"' . (($mail_way === $mail_provider_key) ? '' : ' hidden') . '>
                        <h3 class="pg-group-label">' . h(($mail_provider_key === 'google') ? lang('A Google account') : lang('A Microsoft account')) . '</h3>
                        ' . $mail_select . pg_mail_settings_connect_buttons($mail_providers, $mail_provider_key, $mail_select !== '') . '
                    </div>';
}

$mail_way_panes .= '
                    <div class="mt-5" data-mail-way-pane="service"' . (($mail_way === 'service') ? '' : ' hidden') . '>
                        <h3 class="pg-group-label">' . h(lang('A sending service')) . '</h3>
                        ' . pg_mail_transport_settings_fields() . '
                    </div>
                    <div class="mt-5" data-mail-way-pane="server"' . (($mail_way === 'server') ? '' : ' hidden') . '>
                        <h3 class="pg-group-label">' . h(lang('Your own mail server')) . '</h3>
                        <div class="row g-5">
                            <div class="col-8">
                                <label for="system_smtp_hostname" class="pg-field-label">' . lang('Hostname') . '</label>
                                <input type="text" name="system_smtp_hostname" id="system_smtp_hostname" class="pg-input" value="' . $mail_value('SYSTEM_SMTP_HOSTNAME') . '" inputmode="url" spellcheck="false" autocapitalize="off" aria-describedby="system_smtp_hostname_help">
                                <div class="pg-field-help" id="system_smtp_hostname_help">' . h(lang('Without a server name, e-mail leaves through PHP mail() on this server.')) . '</div>
                            </div>
                            <div class="col-4">
                                <label for="system_smtp_port" class="pg-field-label">' . lang('Port') . '</label>
                                <input type="number" name="system_smtp_port" id="system_smtp_port" class="pg-input" value="' . $mail_value('SYSTEM_SMTP_PORT') . '" placeholder="587">
                            </div>
                            <div class="col-12">
                                <label for="system_smtp_username" class="pg-field-label">' . lang('Username') . '</label>
                                <input type="text" name="system_smtp_username" id="system_smtp_username" class="pg-input" value="' . $mail_value('SYSTEM_SMTP_USERNAME') . '" autocomplete="new-password" spellcheck="false" autocapitalize="off">
                            </div>
                            <div class="col-12">
                                <label for="system_smtp_password" class="pg-field-label">' . lang('Password') . '</label>
                                <input type="password" name="system_smtp_password" id="system_smtp_password" class="pg-input" value="" autocomplete="new-password"' . $mail_system_kept . '>
                            </div>
                        </div>
                    </div>';

$mail_way_body = preg_replace('/<(input|select|textarea)\b(?![^>]*\sform=)/', '<$1 form="' . $mail_form . '"', pg_mail_way_cards($mail_way, $mail_form) . $mail_way_panes);

$pg_settings_modals[] = pg_settings_drawer(
    'pgset-mail-way',
    h(lang('How should system e-mail leave?')),
    $mail_way_body,
    !empty($pg_settings_in_dialog),
    '<span class="flex-grow-1"></span>'
        . '<button type="button" class="pg-button pg-button-quiet"' . (!empty($pg_settings_in_dialog) ? ' data-pgdrawer-close' : ' data-bs-dismiss="drawer"') . '>' . h(lang('Cancel')) . '</button>'
        . '<button type="submit" form="' . $mail_form . '" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>',
    h(lang('Order, form, password and notice mail.')),
    '',
    pg_settings_tile('envelope-check', 'blue')
);

// ── Campaign mail ──
$mail_parts['campaign'] = array('html' => '
                        <div id="pgset-mail-campaign" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Campaign mail') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-field">' . pg_settings_tile('megaphone', 'team') . '
                                                <div class="switch"><input type="checkbox" name="email_campaign_job" id="email_campaign_job" value="true"' . ($mail_campaign_on ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#email_campaign_job_row"></div>
                                                <label for="email_campaign_job">' . lang('Enable Email Campaign Job') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="collapse" id="email_campaign_job_row">
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                ' . pg_mail_settings_connection_field('campaign_mail_connection', defined('CAMPAIGN_MAIL_CONNECTION') ? CAMPAIGN_MAIL_CONNECTION : 0, $mail_accounts) . '
                                            </div>
                                            <div class="col-12">
                                                <label for="campaign_smtp_hostname" class="form-label pg-field-label">' . lang('Hostname') . '</label>
                                                <input type="text" name="campaign_smtp_hostname" id="campaign_smtp_hostname" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_HOSTNAME') . '" inputmode="url" spellcheck="false" autocapitalize="off">
                                            </div>
                                            <div class="col-12">
                                                <label for="email_campaign_job_number_of_emails" class="form-label pg-field-label">' . lang('Max.') . '</label>
                                                <input type="number" name="email_campaign_job_number_of_emails" id="email_campaign_job_number_of_emails" class="form-control" value="' . $mail_value('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS') . '" placeholder="25">
                                            </div>
                                            <div class="col-12">
                                                <label for="campaign_smtp_port" class="form-label pg-field-label">' . lang('Port') . '</label>
                                                <input type="number" name="campaign_smtp_port" id="campaign_smtp_port" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_PORT') . '" placeholder="587">
                                            </div>
                                            <div class="col-12">
                                                <label for="campaign_smtp_username" class="form-label pg-field-label">' . lang('Username') . '</label>
                                                <input type="text" name="campaign_smtp_username" id="campaign_smtp_username" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_USERNAME') . '" autocomplete="new-password" spellcheck="false" autocapitalize="off">
                                            </div>
                                            <div class="col-12">
                                                <label for="campaign_smtp_password" class="form-label pg-field-label">' . lang('Password') . '</label>
                                                <input type="password" name="campaign_smtp_password" id="campaign_smtp_password" class="form-control" value="" autocomplete="new-password"' . $mail_campaign_kept . '>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label for="opt_in_label" class="form-label pg-field-label">' . lang('Opt-In Label') . '</label>
                                            <input type="text" name="opt_in_label" id="opt_in_label" class="form-control" value="' . h($opt_in_label) . '" maxlength="255">
                                        </div>
                                        <div class="col-12">
                                            <label for="plain_text_email_campaign_footer" class="pg-field-label">' . lang('Plain Text Footer') . '</label>
                                            <textarea name="plain_text_email_campaign_footer" id="plain_text_email_campaign_footer" class="pg-input">' . h($plain_text_email_campaign_footer) . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_mail_sending_fold_campaign($mail_campaign_on, defined('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS') ? EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS : 0);

// ── DKIM TXT Record ──
// Named after the selector and domain mail is signed with now, not the ones
// the key was generated with.
$mail_txt_record = pg_mail_settings_dkim_shown_record(
    (string) db_value("SELECT description FROM files WHERE name = 'dkim.key'"),
    defined('DKIM_SELECTOR') ? (string) DKIM_SELECTOR : '',
    defined('DKIM_DOMAIN') ? (string) DKIM_DOMAIN : '',
    EMAIL_ADDRESS
);

$mail_dkim_buttons = '
                                            <button type="submit" name="generate_dkim_key" value="true" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-key" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and generate a DKIM key') . '</span>
                                            </button>'
    . ($mail_ready ? '
                                            <button type="submit" name="test_dkim" value="true" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-patch-check" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and test') . '</span>
                                            </button>' : '');

$mail_dkim_record = $mail_txt_record
    ? '<label for="textrecord" class="pg-field-label">' . lang('Add this TXT record to your DNS:') . '</label>
                                            <textarea id="textrecord" class="pg-input font-monospace" rows="4" readonly>' . h($mail_txt_record) . '</textarea>'
    : '<p class="pg-field-help mt-0">' . lang('No DKIM key generated yet.') . '</p>';

$mail_parts['dkim'] = array('html' => '
                        <div id="pgset-mail-dkim" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('DKIM TXT Record') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 pg-field-span-6">
                                            <label class="visually-hidden" for="dkim_selector">' . h(lang('Selector')) . '</label>
                                            <label class="visually-hidden" for="dkim_domain">' . h(lang('Domain')) . '</label>
                                            <div class="pg-input font-monospace">
                                                <input type="text" name="dkim_selector" id="dkim_selector" class="text-end" value="' . $mail_value('DKIM_SELECTOR') . '" placeholder="' . h(lang('Selector')) . '" spellcheck="false" autocapitalize="off">
                                                <span class="pg-text-meta" aria-hidden="true">._domainkey.</span>
                                                <input type="text" name="dkim_domain" id="dkim_domain" value="' . $mail_value('DKIM_DOMAIN') . '" placeholder="example.com" inputmode="url" spellcheck="false" autocapitalize="off">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            ' . $mail_dkim_record . '
                                            <div class="mt-3 d-flex flex-wrap gap-2">' . $mail_dkim_buttons . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_mail_sending_fold_dkim(defined('DKIM_SELECTOR') ? (string) DKIM_SELECTOR : '', defined('DKIM_DOMAIN') ? (string) DKIM_DOMAIN : '', $mail_txt_record !== '');

// ── Sending domain ──
// Read only: SPF, DKIM and DMARC from DNS, the bounce and complaint rates.
// The card keeps id="mail_domain_card", the operations alert's old target.
// The E-mail category's Sending tab reads the same lookup for its pill.
$mail_domain_health = pg_mail_domain_health();

$mail_parts['domain'] = array('html' => '
                        <div id="pgset-mail-domain" class="pg-set-card">
                            ' . pg_mail_domain_health_card($mail_domain_health, false) . '
                        </div>') + pg_mail_sending_fold_domain($mail_domain_health);

// ── MailChimp ──
// Written to the config row by this category's save, checked at Mailchimp
// first (mail_sending.save.php).
$mail_parts['mailchimp'] = array('html' => '
                        <div id="pgset-mailchimp" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    <span lang="en">' . lang('MailChimp') . '</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-field">' . pg_settings_tile('envelope-paper', 'team') . '
                                                <div class="switch"><input type="checkbox" name="mailchimp" id="mailchimp" value="1"' . $mailchimp_checked . ' class="collapse-switcher" data-bs-target="#mailchimp_row"></div>
                                                <label for="mailchimp">' . lang('MailChimp Sync') . '</label><div class="form-text">' . lang('Auto-export customers, orders, & products to MailChimp regularly. Requires cron job (job.php).') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="collapse" id="mailchimp_row">
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <label for="mailchimp_key" class="form-label pg-field-label">' . lang('API Key') . '</label>
                                                <input type="text" name="mailchimp_key" id="mailchimp_key" class="form-control" value="" maxlength="100" autocomplete="off" spellcheck="false" placeholder="' . ($mailchimp_key_stored ? h(lang('Leave blank to keep the stored key')) : '') . '">' . ($mailchimp_key_stored ? '
                                                <div class="form-text">' . lang('A secret is saved (never shown here). Type a new value only to replace it.') . '</div>
                                                <div class="d-flex align-items-center gap-3 mt-2">
                                                    <input value="1" class="pg-checkbox" type="checkbox" id="mailchimp_key_clear" name="mailchimp_key_clear">
                                                    <label for="mailchimp_key_clear">' . lang('Remove the stored key') . '</label>
                                                </div>' : '') . '
                                            </div>
                                            <div class="col-12">
                                                <label for="mailchimp_list_id" class="form-label pg-field-label">' . lang('List ID') . '</label>
                                                <input type="text" name="mailchimp_list_id" id="mailchimp_list_id" class="form-control" value="' . h($mailchimp_list_id) . '" maxlength="100">
                                            </div>
                                            <div class="col-12">
                                                <label for="mailchimp_store_id" class="form-label pg-field-label">' . lang('Store ID') . '</label>
                                                <input type="text" name="mailchimp_store_id" id="mailchimp_store_id" class="form-control" value="' . h($mailchimp_store_id) . '" maxlength="100">
                                            </div>
                                            <div class="col-12">
                                                <label for="mailchimp_sync_days" class="form-label pg-field-label">' . lang('Historical Sync') . '</label>
                                                <label class="form-control form-adorn form-adorn-end">
                                                    <span class="form-adorn-text" aria-hidden="true">' . lang('days in the past') . '</span>
                                                    <input type="text" name="mailchimp_sync_days" id="mailchimp_sync_days" class="form-ghost text-end" value="' . h($mailchimp_sync_days ? $mailchimp_sync_days : '') . '" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal">
                                                </label>
                                                <div class="form-text">' . lang('Set how far in the past to sync orders. Leave blank to sync all historical orders.') . '</div>
                                            </div>
                                            <div class="col-12">
                                                <label for="mailchimp_sync_limit" class="form-label pg-field-label">' . lang('Limit Sync') . '</label>
                                                <input type="text" name="mailchimp_sync_limit" id="mailchimp_sync_limit" class="form-control text-end" value="' . h($mailchimp_sync_limit ? $mailchimp_sync_limit : '') . '" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal">
                                                <div class="form-text">' . lang('max number of orders to sync each time cron job runs') . '</div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-field">
                                                    <div class="switch"><input type="checkbox" name="mailchimp_automation" id="mailchimp_automation" value="1"' . $mailchimp_automation_checked . '></div>
                                                    <label for="mailchimp_automation">' . lang('Automation') . '</label><div class="form-text fg-danger">' . lang('only enable after all historical orders have been synced, to start sending MailChimp automation campaigns') . '</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>') + pg_mail_sending_fold_mailchimp(!empty($mailchimp_checked), isset($mailchimp_list_id) ? (string) $mailchimp_list_id : '');

// ── Send a test ──
// The address it goes to, the button (it saves first) and the last test's
// result, kept in config_text by pg_mail_settings_send_test().
$mail_test = pg_mail_sending_fold_test(pg_mail_test_result_decode(pg_config_text('mail_test_last')), time());

$mail_parts['test'] = array('html' => '
                        <div id="pgset-mail-test" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Send a test') . '
                                </div>
                                <div class="card-body">
                                    <p class="pg-field-help mt-0">' . h(lang(array('string' => 'The test goes to {var:1}.', 'vars' => array(EMAIL_ADDRESS)))) . '</p>'
    . (($mail_test['result'] !== '') ? '
                                    <p class="pg-field-help d-flex flex-wrap align-items-center gap-2" role="status"><span class="pg-pill ' . h($mail_test['pill'][1]) . '">' . h($mail_test['pill'][0]) . '</span>' . h($mail_test['result']) . '</p>' : '') . '
                                    <div class="d-flex flex-wrap gap-2 mt-4">
                                        <button type="submit" name="send_system_email" value="Send System Test Email" class="pg-button pg-button-quiet pg-button-sm">
                                            <i class="bi bi-send" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and send a test') . '</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>') + $mail_test;

// ── The rows ──
$mail_rows = array();

foreach (pg_mail_sending_fold_order() as $mail_key) {
    $mail_rows[] = array('key' => 'mail-' . $mail_key) + pg_mail_sending_fold_face($mail_key) + $mail_parts[$mail_key];
}

$pg_settings_cards[] = pg_settings_fold($mail_rows);

// The Connect buttons sit inside the settings form and submit these by their
// form attribute; forms cannot be nested. The service sends the browser back
// to this category's page.
foreach ($mail_providers as $mail_provider_key => $mail_provider) {
    $pg_settings_modals[] = pg_mail_connect_form('pgset_mail_connect_' . $mail_provider_key, $mail_provider_key, 'mail', pg_settings_url('mail_sending') . '#pgset-mail-system');
}

// The ways sheet shows the chosen way's fields and enables only its
// account select, so one system_mail_connection is posted.
$pg_settings_scripts[] = pg_mail_transport_settings_script() . pg_mail_domain_health_script() . '
    <script>
        (function () {
            if (window.pgMailWayBound) {
                return;
            }

            window.pgMailWayBound = true;

            document.addEventListener("change", function (event) {
                var radio = event.target;

                if (!radio.matches || !radio.matches("[data-mail-way]")) {
                    return;
                }

                var sheet = radio.closest(".pg-sm-drawer, dialog") || document;

                Array.prototype.forEach.call(sheet.querySelectorAll("[data-mail-way-pane]"), function (pane) {
                    pane.hidden = pane.getAttribute("data-mail-way-pane") !== radio.value;
                });

                Array.prototype.forEach.call(sheet.querySelectorAll("[data-mail-way-account]"), function (select) {
                    select.disabled = select.getAttribute("data-mail-way-account") !== radio.value;
                });
            });
        })();
    </script>';
