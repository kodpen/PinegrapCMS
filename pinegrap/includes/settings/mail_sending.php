<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail sending: the cards of the former SMTP Settings
 * screen. What they save and run is includes/fn/mail_settings.php; the
 * settings are config.php defines, read here from the constants.
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
$mail_accounts = pg_connect_accounts(array('user_id' => 0, 'purpose' => 'mail'));
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

// Each button saves the boxes first, then does its job
// (includes/settings/mail_sending.save.php).
$mail_test_button = '
                                            <button type="submit" name="send_system_email" value="Send System Test Email" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-send me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and send a test') . '</span>
                                            </button>';

// ── Sending service ──
$pg_settings_cards[] = '
                        <div id="pgset-mail-transport" class="pg-set-card">
                            <div class="card" id="mail_transport_card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Sending service') . '
                                </div>
                                <div class="card-body">' . pg_mail_transport_settings_fields() . '
                                    <div class="mt-5">' . $mail_test_button . '</div>
                                </div>
                            </div>
                        </div>';

// ── System ──
$pg_settings_cards[] = '
                        <div id="pgset-mail-system" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('System') . '
                                </div>
                                <div class="card-body">
                                    <div class="my-3">
                                        ' . pg_mail_settings_connection_field('system_mail_connection', defined('SYSTEM_MAIL_CONNECTION') ? SYSTEM_MAIL_CONNECTION : 0, $mail_accounts) . '
                                        ' . pg_mail_settings_connect_buttons($mail_providers, $mail_accounts) . '
                                    </div>
                                    <div class="row gy-5">
                                        <div class="col-12 md:col-8">
                                            <label for="system_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                            <input type="text" name="system_smtp_hostname" id="system_smtp_hostname" class="form-control" value="' . $mail_value('SYSTEM_SMTP_HOSTNAME') . '" inputmode="url" spellcheck="false" autocapitalize="off">
                                        </div>
                                        <div class="col-12 md:col-4">
                                            <label for="system_smtp_port" class="form-label">' . lang('Port') . '</label>
                                            <input type="number" name="system_smtp_port" id="system_smtp_port" class="form-control" value="' . $mail_value('SYSTEM_SMTP_PORT') . '" placeholder="587">
                                        </div>
                                        <div class="col-12 lg:col-6">
                                            <label for="system_smtp_username" class="form-label">' . lang('Username') . '</label>
                                            <input type="text" name="system_smtp_username" id="system_smtp_username" class="form-control" value="' . $mail_value('SYSTEM_SMTP_USERNAME') . '" autocomplete="new-password" spellcheck="false" autocapitalize="off">
                                        </div>
                                        <div class="col-12 lg:col-6">
                                            <label for="system_smtp_password" class="form-label">' . lang('Password') . '</label>
                                            <input type="password" name="system_smtp_password" id="system_smtp_password" class="form-control" value="" autocomplete="new-password"' . $mail_system_kept . '>
                                        </div>
                                    </div>
                                    ' . ($mail_ready ? '<div class="mt-5">' . $mail_test_button . '</div>' : '') . '
                                </div>
                            </div>
                        </div>';

// ── Campaign ──
$pg_settings_cards[] = '
                        <div id="pgset-mail-campaign" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Campaign') . '
                                </div>
                                <div class="card-body">
                                    <div class="form-field">
                                        <div class="switch"><input type="checkbox" name="email_campaign_job" id="email_campaign_job" value="true"' . ($mail_campaign_on ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#email_campaign_job_row"></div>
                                        <label for="email_campaign_job">' . lang('Enable Email Campaign Job') . '</label>
                                    </div>
                                    <div class="collapse" id="email_campaign_job_row">
                                        <div class="my-3">
                                            ' . pg_mail_settings_connection_field('campaign_mail_connection', defined('CAMPAIGN_MAIL_CONNECTION') ? CAMPAIGN_MAIL_CONNECTION : 0, $mail_accounts) . '
                                        </div>
                                        <div class="row gy-5">
                                            <div class="col-12 lg:col-6">
                                                <label for="campaign_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                                <input type="text" name="campaign_smtp_hostname" id="campaign_smtp_hostname" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_HOSTNAME') . '" inputmode="url" spellcheck="false" autocapitalize="off">
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-3">
                                                <label for="email_campaign_job_number_of_emails" class="form-label">' . lang('Max.') . '</label>
                                                <input type="number" name="email_campaign_job_number_of_emails" id="email_campaign_job_number_of_emails" class="form-control" value="' . $mail_value('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS') . '" placeholder="25">
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-3">
                                                <label for="campaign_smtp_port" class="form-label">' . lang('Port') . '</label>
                                                <input type="number" name="campaign_smtp_port" id="campaign_smtp_port" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_PORT') . '" placeholder="587">
                                            </div>
                                            <div class="col-12 lg:col-6">
                                                <label for="campaign_smtp_username" class="form-label">' . lang('Username') . '</label>
                                                <input type="text" name="campaign_smtp_username" id="campaign_smtp_username" class="form-control" value="' . $mail_value('CAMPAIGN_SMTP_USERNAME') . '" autocomplete="new-password" spellcheck="false" autocapitalize="off">
                                            </div>
                                            <div class="col-12 lg:col-6">
                                                <label for="campaign_smtp_password" class="form-label">' . lang('Password') . '</label>
                                                <input type="password" name="campaign_smtp_password" id="campaign_smtp_password" class="form-control" value="" autocomplete="new-password"' . $mail_campaign_kept . '>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── DKIM TXT Record ──
$mail_txt_record = db_value("SELECT description FROM files WHERE name = 'dkim.key'");

$mail_dkim_buttons = '
                                            <button type="submit" name="generate_dkim_key" value="true" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-key me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and generate a DKIM key') . '</span>
                                            </button>'
    . ($mail_ready ? '
                                            <button type="submit" name="test_dkim" value="true" class="pg-button pg-button-quiet pg-button-sm">
                                                <i class="bi bi-patch-check me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save and test') . '</span>
                                            </button>' : '');

$mail_dkim_record = $mail_txt_record
    ? '<label for="textrecord" class="form-label">' . lang('Add this TXT record to your DNS:') . '</label>
                                            <textarea id="textrecord" class="form-control" rows="4" readonly>' . h($mail_txt_record) . '</textarea>'
    : '<p class="fg-secondary">' . lang('No DKIM key generated yet.') . '</p>';

$pg_settings_cards[] = '
                        <div id="pgset-mail-dkim" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('DKIM TXT Record') . '
                                </div>
                                <div class="card-body">
                                    <div class="row gy-5">
                                        <div class="col-12 md:col-8 lg:col-6">
                                            <label class="visually-hidden" for="dkim_selector">' . h(lang('Selector')) . '</label>
                                            <label class="visually-hidden" for="dkim_domain">' . h(lang('Domain')) . '</label>
                                            <div class="input-group">
                                                <input type="text" name="dkim_selector" id="dkim_selector" class="form-control text-end" value="' . $mail_value('DKIM_SELECTOR') . '" placeholder="' . h(lang('Selector')) . '" spellcheck="false" autocapitalize="off">
                                                <span class="input-group-text">._domainkey.</span>
                                                <input type="text" name="dkim_domain" id="dkim_domain" class="form-control" value="' . $mail_value('DKIM_DOMAIN') . '" placeholder="example.com" inputmode="url" spellcheck="false" autocapitalize="off">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            ' . $mail_dkim_record . '
                                            <div class="mt-3 d-flex flex-wrap gap-2">' . $mail_dkim_buttons . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Sending domain ──
// Read only: SPF, DKIM and DMARC from DNS, the bounce and complaint rates.
// The card keeps id="mail_domain_card", the operations alert's old target.
$pg_settings_cards[] = '
                        <div id="pgset-mail-domain" class="pg-set-card">
                            ' . pg_mail_domain_health_card(pg_mail_domain_health(), false) . '
                        </div>';

// The Connect buttons sit inside the settings form and submit these by their
// form attribute; forms cannot be nested. The service sends the browser back
// to this category's page.
foreach ($mail_providers as $mail_provider_key => $mail_provider) {
    $mail_connect_key = h($mail_provider_key);

    $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="pgset_mail_connect_' . $mail_connect_key . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($mail_provider_key, 'mail', pg_settings_url('mail_sending') . '#pgset-mail-system') . '
            </form>';
}

$pg_settings_scripts[] = pg_mail_transport_settings_script() . pg_mail_domain_health_script();
