<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */


include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');

$liveform = new liveform('smtp_settings');

// The save, the DKIM jobs and the test e-mail are includes/fn/mail_settings.php.
$smtp_ready = pg_mail_settings_ready();

// The site's accounts connected for sending e-mail (Connections), and the
// providers one can be connected with from here.
$mail_accounts = pg_connect_accounts(array('user_id' => 0, 'purpose' => 'mail'));
$mail_providers = pg_mail_settings_providers();

// Writes the posted boxes and says so; false when a box was refused or
// config.php could not be read or written.
function smtp_settings_save($liveform_settings) {
    $saved = pg_mail_settings_save($_POST, $liveform_settings);

    if ($saved !== false) {
        log_activity(lang("SMTP Settings was modified"), $_SESSION['sessionusername']);
        $liveform_settings->add_notice(lang('The smtp settings have been saved.'));
    }

    return $saved;
}

// if the form has not been submitted
if (!$_POST) {

    // Assign config values. The passwords are left out: a stored secret is
    // never drawn back into the page (or the form state in the session); an
    // empty box keeps the saved value, and the placeholder says one is saved.
    $config_keys = [
        'SYSTEM_SMTP_HOSTNAME','SYSTEM_SMTP_USERNAME','SYSTEM_SMTP_PORT',
        'EMAIL_CAMPAIGN_JOB','EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
        'CAMPAIGN_SMTP_HOSTNAME','CAMPAIGN_SMTP_USERNAME','CAMPAIGN_SMTP_PORT',
        'DKIM_DOMAIN','DKIM_SELECTOR'
    ];
    foreach ($config_keys as $key) {
        $field = strtolower($key);
        $liveform->assign_field_value($field, defined($key) ? constant($key) : '');
    }

    $system_password_placeholder = (defined('SYSTEM_SMTP_PASSWORD') && ((string) SYSTEM_SMTP_PASSWORD !== '')) ? lang('Leave blank to keep the saved secret') : '';
    $campaign_password_placeholder = (defined('CAMPAIGN_SMTP_PASSWORD') && ((string) CAMPAIGN_SMTP_PASSWORD !== '')) ? lang('Leave blank to keep the saved secret') : '';

    $system_connection_field = pg_mail_settings_connection_field('system_mail_connection', defined('SYSTEM_MAIL_CONNECTION') ? SYSTEM_MAIL_CONNECTION : 0, $mail_accounts);
    $campaign_connection_field = pg_mail_settings_connection_field('campaign_mail_connection', defined('CAMPAIGN_MAIL_CONNECTION') ? CAMPAIGN_MAIL_CONNECTION : 0, $mail_accounts);
    $connect_buttons = pg_mail_settings_connect_buttons($mail_providers, $mail_accounts);

    // The buttons above sit inside the settings form and submit these forms
    // by their form attribute; forms cannot be nested.
    $connect_forms = '';

    foreach ($mail_providers as $mail_provider_key => $mail_provider) {
        $connect_forms .= '
            <form method="post" action="connect.php" id="pgset_mail_connect_' . h($mail_provider_key) . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($mail_provider_key, 'mail', 'smtp_settings.php') . '
            </form>';
    }

    // Save and send a test: the post saved the boxes and redirected here, so
    // this request's constants hold the saved settings.
    pg_mail_settings_send_test($liveform);

    $connect_flash = pg_connect_flash_take();

    if ($connect_flash) {
        if ($connect_flash['kind'] === 'error') {
            $liveform->add_error(h($connect_flash['text']));
        } else {
            $liveform->add_notice(h($connect_flash['text']));
        }
    }

    // Buttons
    $system_buttons = '';
    if ($smtp_ready) {
        $system_buttons = '
            <button type="submit" id="send_system_email" name="send_system_email" value="Send System Test Email" class="btn my-1 btn-solid theme-warning">
                <span class="material-icons me-3" aria-hidden="true">forward_to_inbox</span>
                <span class="pg-btn-label">' . lang('Save and send a test') . '</span>
            </button>';
    }

    $dkim_generate_button = '
        <button type="submit" name="generate_dkim_key" value="true" class="btn my-1 btn-solid theme-secondary">
            <span class="material-icons me-3" aria-hidden="true">vpn_key</span>
            <span class="pg-btn-label">' . lang('Save and generate a DKIM key') . '</span>
        </button>';
    $dkim_buttons = $dkim_generate_button;
    if ($smtp_ready) {
        $dkim_buttons .= '
            <button type="submit" name="test_dkim" value="true" class="btn my-1 btn-solid theme-info">
                <span class="material-icons me-3" aria-hidden="true">verified</span>
                <span class="pg-btn-label">' . lang('Save and test') . '</span>
            </button>';
    }




    $dkim_settings = '
    <div class="col-12 md:col-8 lg:col-6  my-3">
        <label class="visually-hidden" for="dkim_selector">' . h(lang('Selector')) . '</label>
        <label class="visually-hidden" for="dkim_domain">' . h(lang('Domain')) . '</label>
        <div class="input-group">
            ' . $liveform->output_field([
                'type'=>'text',
                'name'=>'dkim_selector',
                'id'=>'dkim_selector',
                'class'=>'form-control text-end',
                'placeholder'=>lang('Selector'),
                'spellcheck'=>'false',
                'autocapitalize'=>'off'
            ]) . '
            <span class="input-group-text">._domainkey.</span>
            ' . $liveform->output_field([
                'type'=>'text',
                'name'=>'dkim_domain',
                'id'=>'dkim_domain',
                'class'=>'form-control',
                'placeholder'=>'example.com',
                'inputmode'=>'url',
                'spellcheck'=>'false',
                'autocapitalize'=>'off'
            ]) . '
        </div>
    </div>';




    // TXT record preview
    $txt_record = db_value("SELECT description FROM files WHERE name = 'dkim.key'");
    $dkim_txt_preview = '';
    if ($txt_record) {
        $dkim_txt_preview = '
            <div class="col-12">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            ' . $dkim_settings . '
                            <div class="col-12 my-3">
                                <label for="textrecord" class="form-label">' . lang('Add this TXT record to your DNS:') . '</label>
                                <textarea id="textrecord" class="form-control" rows="4" readonly>' . h($txt_record) . '</textarea>
                                <div class="mt-3">' . $dkim_buttons . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    } else {
        $dkim_txt_preview = '
            <div class="col-12">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            ' . $dkim_settings . '
                            <div class="col-12 my-3">
                                <p class="fg-secondary">' . lang('No DKIM key generated yet.') . '</p>
                                <div>' . $dkim_buttons . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }

    print pg_page_shell([
        'title'=> lang('SMTP Settings'),
        'extra classes'=>'setting pg-measure-edit',
        'icon'=>'setting',
        'heading'=>lang('SMTP Settings'),
        'heading_description' => lang('Update the Smtp settings for the Software Mail Function.')
    ]) . '
<main id="content" class="container-fluid">
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . $liveform->get_warnings() . $liveform->output_notices() . '
                
				
                <form name="form" action="smtp_settings.php" method="post" autocomplete="off"  submitshortcut="submit_save">
                    ' . get_token_field() . '
                    <!-- Return in a box presses the first submit button: this one saves, not Send Test or Generate DKIM Key -->
                    <button type="submit" name="submit_save" value="Save" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
                    <!-- Edit screen layout of product_builder.php: the form column, the status column (Sending domain), the sticky save bar. -->
                    <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                    <div class="row">
                        <!-- Sending service -->
                        <div class="col-12">
                            <div class="card mb-7" id="mail_transport_card">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Sending service') . '</h2>
                                <div class="card-body">' . pg_mail_transport_settings_fields() . '
                                    <div class="mt-5">
                                        <button type="submit" name="send_system_email" value="Send System Test Email" class="btn my-1 btn-solid theme-warning">
                                            <span class="material-icons me-3" aria-hidden="true">forward_to_inbox</span>
                                            <span class="pg-btn-label">' . lang('Save and send a test') . '</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- System SMTP -->
                        <div class="col-12 xl:col-6">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('System') . '</h2>
                                <div class="card-body">
                                    <div class="my-3">
                                        ' . $system_connection_field . '
                                        ' . $connect_buttons . '
                                    </div>
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="system_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_hostname','id'=>'system_smtp_hostname','class'=>'form-control','inputmode'=>'url','spellcheck'=>'false','autocapitalize'=>'off']) . '
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="system_smtp_port" class="form-label">' . lang('Port') . '</label>
                                            ' . $liveform->output_field(['type'=>'number','name'=>'system_smtp_port','id'=>'system_smtp_port','class'=>'form-control','placeholder'=>'587']) . '
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="system_smtp_username" class="form-label">' . lang('Username') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_username','id'=>'system_smtp_username','class'=>'form-control','autocomplete'=>'new-password','spellcheck'=>'false','autocapitalize'=>'off']) . '
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="system_smtp_password" class="form-label">' . lang('Password') . '</label>
                                            ' . $liveform->output_field(['type'=>'password','name'=>'system_smtp_password','id'=>'system_smtp_password','class'=>'form-control','autocomplete'=>'new-password','placeholder'=>$system_password_placeholder]) . '
                                        </div>
                                    </div>
                                    <div class="mt-5">' . $system_buttons . '</div>
                                </div>
                            </div>
                        </div>
                        <!-- Campaign SMTP -->
                        <div class="col-12 xl:col-6">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Campaign') . '
                                </h2>
                                <div class="card-body">
                                    <div class="form-field my-3">
                                        <div class="switch">' . $liveform->output_field([
                                            'type'=>'checkbox',
                                            'name'=>'email_campaign_job',
                                            'id'=>'email_campaign_job',
                                            'class'=>'collapse-switcher no-bs',
                                            'value'=>'true',
                                            'data-bs-target'=>'#email_campaign_job_row'
                                        ]) . '</div>
                                        <label class="" for="email_campaign_job">' . lang('Enable Email Campaign Job') . '</label>
                                    </div>
                                    <div class="collapse" id="email_campaign_job_row">
                                        <div class="my-3">
                                            ' . $campaign_connection_field . '
                                        </div>
                                        <div class="row">
                                            <div class="col-12 lg:col-6 my-3">
                                                <label for="campaign_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_hostname','id'=>'campaign_smtp_hostname','class'=>'form-control','inputmode'=>'url','spellcheck'=>'false','autocapitalize'=>'off']) . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-3 my-3">
                                                <label for="email_campaign_job_number_of_emails" class="form-label">' . lang('Max.') . '</label>
                                                ' . $liveform->output_field(['type'=>'number','name'=>'email_campaign_job_number_of_emails','id'=>'email_campaign_job_number_of_emails','class'=>'form-control','placeholder'=>'25']) . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-3 my-3">
                                                <label for="campaign_smtp_port" class="form-label">' . lang('Port') . '</label>
                                                ' . $liveform->output_field(['type'=>'number','name'=>'campaign_smtp_port','id'=>'campaign_smtp_port','class'=>'form-control','placeholder'=>'587']) . '
                                            </div>
                                            <div class="col-12 lg:col-6 my-3">
                                                <label for="campaign_smtp_username" class="form-label">' . lang('Username') . '</label>
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_username','id'=>'campaign_smtp_username','class'=>'form-control','autocomplete'=>'new-password','spellcheck'=>'false','autocapitalize'=>'off']) . '
                                            </div>
                                            <div class="col-12 lg:col-6 my-3">
                                                <label for="campaign_smtp_password" class="form-label">' . lang('Password') . '</label>
                                                ' . $liveform->output_field(['type'=>'password','name'=>'campaign_smtp_password','id'=>'campaign_smtp_password','class'=>'form-control','autocomplete'=>'new-password','placeholder'=>$campaign_password_placeholder]) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- DKIM TXT Record Preview -->
                        ' . $dkim_txt_preview . '
                    </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <!-- Sending domain: SPF, DKIM, DMARC and the bounce rates, read only.
                             Not pinned: with the TXT records to publish it is taller than the window. -->
                        ' . pg_mail_domain_health_card(pg_mail_domain_health(), false) . '
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success">
                                        <span class="material-icons me-3" aria-hidden="true">save</span>
                                        <span class="pg-btn-label">' . lang(['string'=>'Save']) . '</span>
                                    </button>
                                </div>
                            </div>
                        </nav>
                    </div>
                    </div>
                </form>
                ' . $connect_forms . '
            </div>
        </div>
    
</main>' . pg_mail_transport_settings_script() . pg_mail_domain_health_script() . output_footer();

    $liveform->remove_form();
    exit;

} else {
    validate_token_field();
    $liveform_settings = new liveform('smtp_settings');

    // Every field here holds one value. One posted as a list
    // (system_smtp_hostname[]=x) stopped the request in trim(); reading it as
    // not given would remove that setting from config.php. Nothing is written,
    // so every setting stays as it was.
    $smtp_lists = pg_request_unexpected_lists($_POST);

    if ($smtp_lists) {
        $liveform_settings->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $smtp_lists)))));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // DKIM key generate
    if (isset($_POST['generate_dkim_key']) && $_POST['generate_dkim_key'] === 'true') {
        // The boxes are saved first, so the mailer signs with the selector and
        // domain the TXT record below names.
        $saved = smtp_settings_save($liveform_settings);

        if ($saved !== false) {
            pg_mail_settings_dkim_generate($saved, $liveform_settings);
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // DKIM test
    if (isset($_POST['test_dkim']) && $_POST['test_dkim'] === 'true') {
        // Checks the selector and domain beside the button, saved first; the
        // constants of this request still hold the ones loaded before the save.
        $saved = smtp_settings_save($liveform_settings);

        if ($saved !== false) {
            pg_mail_settings_dkim_test($saved, $liveform_settings);
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // Test e-mail sending
    if (isset($_POST['send_system_email']) && $_POST['send_system_email'] === 'Send System Test Email') {
        // Saved first; the SMTP constants of this request still hold the old
        // settings, so the next page view sends the test with the saved ones.
        if (smtp_settings_save($liveform_settings) !== false) {
            $_SESSION['smtp_settings_send_test'] = true;
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // Save the settings
    if (isset($_POST['submit_save']) && ($_POST['submit_save'] ?? '') === 'Save') {
        smtp_settings_save($liveform_settings);

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // fallback
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
    exit;
}