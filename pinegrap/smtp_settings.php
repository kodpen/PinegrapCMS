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

$liveform = new liveform('smtp_settings');

// SMTP credentials check
$smtp_ready = (
    defined('SYSTEM_SMTP_HOSTNAME') && SYSTEM_SMTP_HOSTNAME !== '' &&
    defined('SYSTEM_SMTP_USERNAME') && SYSTEM_SMTP_USERNAME !== '' &&
    defined('SYSTEM_SMTP_PASSWORD') && SYSTEM_SMTP_PASSWORD !== ''
) || (pg_mail_connection('system') !== null);

// The site's accounts connected for sending e-mail (Connected accounts), and
// the providers one can be connected with from here.
$mail_accounts = pg_connect_accounts(array('user_id' => 0, 'purpose' => 'mail'));
$mail_providers = array();

if (pg_connect_schema_ready() && pg_connect_can_seal()) {
    foreach (pg_connect_providers() as $mail_provider_key => $mail_provider) {
        if (pg_connect_purpose_allows(pg_connect_purpose('mail'), $mail_provider_key, 'site') && pg_connect_app_ready(pg_connect_app($mail_provider_key))) {
            $mail_providers[$mail_provider_key] = $mail_provider;
        }
    }
}

// What a kind of mail is sent through: this server (SMTP or mail()) or one
// of the connected accounts.
function smtp_settings_connection_field($name, $current, $mail_accounts) {

    $options = '<option value="">' . h(lang('This server: the SMTP account below, or PHP mail() without one')) . '</option>';

    foreach ($mail_accounts as $mail_account) {
        $provider = pg_connect_provider($mail_account['provider']);

        $options .= '<option value="' . (int) $mail_account['id'] . '"' . (((int) $current === (int) $mail_account['id']) ? ' selected' : '') . '>'
            . h($mail_account['label'] . ' (' . (is_array($provider) ? $provider['label'] : $mail_account['provider']) . ')'
                . (($mail_account['status'] !== 'active') ? ' - ' . lang('Connect again') : ''))
            . '</option>';
    }

    return '
        <label for="' . h($name) . '" class="form-label">' . h(lang('Send through')) . '</label>
        <select name="' . h($name) . '" id="' . h($name) . '" class="form-control">' . $options . '</select>';
}

function smtp_settings_connect_buttons($mail_providers, $mail_accounts) {

    $buttons = '';

    foreach ($mail_providers as $provider_key => $provider) {
        $buttons .= '
            <button type="submit" form="smtp_connect_' . h($provider_key) . '" class="btn btn-sm btn-outline theme-primary rounded-pill me-1 mt-1">
                <i class="bi ' . h($provider['icon']) . ' me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $provider['label']))) . '</button>';
    }

    $text = $mail_accounts
        ? lang('A connected account sends without an SMTP password; the message is from that account and replies go to the address it was meant to come from.')
        : lang('Or send from a Google or Microsoft account, without an SMTP password.');

    $link = $mail_providers
        ? ''
        : ' <a href="connections.php">' . h(lang('Set up the application on Connected accounts first.')) . '</a>';

    return '<div class="form-text">' . h($text) . $link . '</div>' . (($buttons !== '') ? '<div>' . $buttons . '</div>' : '');
}

// if the form has not been submitted
if (!$_POST) {

    // Assign config values
    $config_keys = [
        'SYSTEM_SMTP_HOSTNAME','SYSTEM_SMTP_USERNAME','SYSTEM_SMTP_PASSWORD','SYSTEM_SMTP_PORT',
        'EMAIL_CAMPAIGN_JOB','EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
        'CAMPAIGN_SMTP_HOSTNAME','CAMPAIGN_SMTP_USERNAME','CAMPAIGN_SMTP_PASSWORD','CAMPAIGN_SMTP_PORT',
        'DKIM_DOMAIN','DKIM_SELECTOR'
    ];
    foreach ($config_keys as $key) {
        $field = strtolower($key);
        $liveform->assign_field_value($field, defined($key) ? constant($key) : '');
    }

    $system_connection_field = smtp_settings_connection_field('system_mail_connection', defined('SYSTEM_MAIL_CONNECTION') ? SYSTEM_MAIL_CONNECTION : 0, $mail_accounts);
    $campaign_connection_field = smtp_settings_connection_field('campaign_mail_connection', defined('CAMPAIGN_MAIL_CONNECTION') ? CAMPAIGN_MAIL_CONNECTION : 0, $mail_accounts);
    $connect_buttons = smtp_settings_connect_buttons($mail_providers, $mail_accounts);

    // The buttons above sit inside the settings form and submit these forms
    // by their form attribute; forms cannot be nested.
    $connect_forms = '';

    foreach ($mail_providers as $mail_provider_key => $mail_provider) {
        $connect_forms .= '
            <form method="post" action="connect.php" id="smtp_connect_' . h($mail_provider_key) . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($mail_provider_key, 'mail', 'smtp_settings.php') . '
            </form>';
    }

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
                <span class="pg-btn-label">' . lang('Send Test') . '</span>
            </button>';
    }

    $dkim_generate_button = '
        <button type="submit" name="generate_dkim_key" value="true" class="btn my-1 btn-solid theme-secondary">
            <span class="material-icons me-3" aria-hidden="true">vpn_key</span>
            <span class="pg-btn-label">' . lang('Generate DKIM Key') . '</span>
        </button>';
    $dkim_buttons = $dkim_generate_button;
    if ($smtp_ready) {
        $dkim_buttons .= '
            <button type="submit" name="test_dkim" value="true" class="btn my-1 btn-solid theme-info">
                <span class="material-icons me-3" aria-hidden="true">verified</span>
                <span class="pg-btn-label">' . lang('Test DKIM') . '</span>
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
                'placeholder'=>lang('Selector')
            ]) . '
            <span class="input-group-text">._domainkey.</span>
            ' . $liveform->output_field([
                'type'=>'text',
                'name'=>'dkim_domain',
                'id'=>'dkim_domain',
                'class'=>'form-control',
                'placeholder'=>'example.com'
            ]) . '
        </div>
    </div>';




    // TXT record preview
    $txt_record = db_value("SELECT description FROM files WHERE name = 'dkim.key'");
    $dkim_txt_preview = '';
    if ($txt_record) {
        $dkim_txt_preview = '
            <div class="col-12">
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </div>
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
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </div>
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
        'extra classes'=>'setting',
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
                    <div class="row">
                        <!-- System SMTP -->
                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('System') . '</div>
                                <div class="card-body">
                                    <div class="my-3">
                                        ' . $system_connection_field . '
                                        ' . $connect_buttons . '
                                    </div>
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="system_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_hostname','id'=>'system_smtp_hostname','class'=>'form-control']) . '
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="system_smtp_port" class="form-label">' . lang('Port') . '</label>
                                            ' . $liveform->output_field(['type'=>'number','name'=>'system_smtp_port','id'=>'system_smtp_port','class'=>'form-control','placeholder'=>'587']) . '
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="system_smtp_username" class="form-label">' . lang('Username') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_username','id'=>'system_smtp_username','class'=>'form-control','autocomplete'=>'new-password']) . '
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="system_smtp_password" class="form-label">' . lang('Password') . '</label>
                                            ' . $liveform->output_field(['type'=>'password','name'=>'system_smtp_password','id'=>'system_smtp_password','class'=>'form-control','autocomplete'=>'new-password']) . '
                                        </div>
                                    </div>
                                    <div class="mt-5">' . $system_buttons . '</div>
                                </div>
                            </div>
                        </div>
                        <!-- Campaign SMTP -->
                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Campaign') . '
                                </div>
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
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_hostname','id'=>'campaign_smtp_hostname','class'=>'form-control']) . '
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
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_username','id'=>'campaign_smtp_username','class'=>'form-control','autocomplete'=>'new-password']) . '
                                            </div>
                                            <div class="col-12 lg:col-6 my-3">
                                                <label for="campaign_smtp_password" class="form-label">' . lang('Password') . '</label>
                                                ' . $liveform->output_field(['type'=>'password','name'=>'campaign_smtp_password','id'=>'campaign_smtp_password','class'=>'form-control','autocomplete'=>'new-password']) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- DKIM TXT Record Preview -->
                        ' . $dkim_txt_preview . '
                    </div>

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success">
                                    <span class="material-icons me-3" aria-hidden="true">save</span>
                                    <span class="pg-btn-label">' . lang(['string'=>'Save']) . '</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </form>
                ' . $connect_forms . '
            </div>
        </div>
    
</main>' . output_footer();

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
        $selector = trim(isset($_POST['dkim_selector']) ? $_POST['dkim_selector'] : '');
        $domain   = trim(isset($_POST['dkim_domain']) ? $_POST['dkim_domain'] : '');


        if ($selector === '' || $domain === '') {
            $liveform_settings->add_warning(lang('DKIM domain and selector must be filled before generating key.'));
        } else {
            // The new pair is made before the old one is touched: when OpenSSL
            // cannot make a key, the pair in use keeps signing.
            $dkim_pair = pg_dkim_key_pair($selector, $domain);

            if (!$dkim_pair) {
                $liveform_settings->add_warning(lang('The DKIM key could not be generated on this server. Any existing DKIM key was left unchanged.'));
            } else {
                // Delete the old records and files
                db("DELETE FROM files WHERE name IN ('dkim.key','dkim.pub')");
                @unlink(FILE_DIRECTORY_PATH . '/dkim.key');
                @unlink(FILE_DIRECTORY_PATH . '/dkim.pub');

                $private_pem = $dkim_pair['private'];
                $public_pem = $dkim_pair['public'];
                $txt_record = $dkim_pair['txt_record'];

                // Private key file
                file_put_contents(FILE_DIRECTORY_PATH . '/dkim.key', $private_pem);
                // Owner-only: the private key must not be readable by other accounts. Failure is ignored so Windows/IIS installs keep working.
                @chmod(FILE_DIRECTORY_PATH . '/dkim.key', 0600);
                $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.key');
                db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
                    VALUES ('dkim.key','" . escape(getPublicRootFolderId()) . "','" . escape($txt_record) . "','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

                // Public key file
                file_put_contents(FILE_DIRECTORY_PATH . '/dkim.pub', $public_pem);
                $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.pub');
                db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
                    VALUES ('dkim.pub','" . escape(getPublicRootFolderId()) . "','DKIM Public Key (PEM)','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

                $liveform_settings->add_notice(
                    lang('DKIM key pair generated successfully.') .
                    '<br><label for="dkim_notice_record" class="form-label mt-3">' . lang('Add this TXT record to your DNS:') . '</label>' .
                    '<textarea id="dkim_notice_record" class="form-control" rows="4" readonly>' . h($txt_record) . '</textarea>'
                );
                log_activity('DKIM key pair generated, files saved, TXT suggestion stored.', $_SESSION['sessionusername']);
            }
        }
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // DKIM test
    if (isset($_POST['test_dkim']) && $_POST['test_dkim'] === 'true') {
        $selector = defined('DKIM_SELECTOR') ? DKIM_SELECTOR : '';
        $domain   = defined('DKIM_DOMAIN') ? DKIM_DOMAIN : '';
        $txt_record = db_value("SELECT description FROM files WHERE name = 'dkim.key'");

        if ($selector && $domain && $txt_record) {
            $dns_records = dns_get_record($selector . '._domainkey.' . $domain, DNS_TXT);
            $dns_txt = '';
            if ($dns_records) {
                foreach ($dns_records as $rec) {
                    $dns_txt .= $rec['txt'] . ' ';
                }
                $dns_txt = trim($dns_txt);
            }

            if ($dns_txt && strpos($txt_record, $dns_txt) !== false) {
                $liveform_settings->add_notice(lang('DKIM test successful. DNS record matches generated key.'));
                log_activity('DKIM test successful.', $_SESSION['sessionusername']);
            } else {
                $liveform_settings->add_warning(lang('DKIM test failed. DNS record not matching or not found.'));
            }
        } else {
            $liveform_settings->add_warning(lang('DKIM domain/selector or key not set.'));
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // Test e-mail sending
    if (isset($_POST['send_system_email']) && $_POST['send_system_email'] === 'Send System Test Email') {
        $result = email([
            'to' => EMAIL_ADDRESS,
            'to_name' => ORGANIZATION_NAME,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'reply_to' => EMAIL_ADDRESS,
            'subject' => lang('System SMTP Settings Test'),
            'body' => lang('Your System smtp settings work correctly.'),
            'format' => 'plain_text',
            'type' => 'system',
            'notify_sender' => false
        ]);

        if ($result) {
            $liveform_settings->add_notice(lang(['string'=>'Test email accepted by SMTP server. Please check mailbox: {var:1}', 'vars'=>[h(EMAIL_ADDRESS)]]));
            log_activity(lang(['string'=>'Test email accepted by SMTP server. Mailbox: {var:1}', 'vars'=>[EMAIL_ADDRESS]]), $_SESSION['sessionusername']);
        } else {
            $liveform_settings->add_warning(h(pg_mail_failure_sentence(lang('Test email could not be sent.'), true)));
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // Save the settings
    if (isset($_POST['submit_save']) && ($_POST['submit_save'] ?? '') === 'Save') {
        $config_file_content = '';
        $fd = fopen(CONFIG_FILE_PATH, "r");
        if ($fd) {
            while (!feof($fd)) {
                $config_file_content .= fgets($fd, 4096);
            }
            fclose($fd);
        }

        $config_keys = [
            'SYSTEM_SMTP_HOSTNAME','SYSTEM_SMTP_USERNAME','SYSTEM_SMTP_PASSWORD','SYSTEM_SMTP_PORT',
            'EMAIL_CAMPAIGN_JOB','EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
            'CAMPAIGN_SMTP_HOSTNAME','CAMPAIGN_SMTP_USERNAME','CAMPAIGN_SMTP_PASSWORD','CAMPAIGN_SMTP_PORT',
            'DKIM_DOMAIN','DKIM_SELECTOR',
            'SYSTEM_MAIL_CONNECTION','CAMPAIGN_MAIL_CONNECTION'
        ];

        // The defines as this request loaded them and as they are written,
        // for the audit trail.
        $smtp_audit_before = array();
        $smtp_audit_after = array();

        foreach ($config_keys as $key) {
            $field = strtolower($key);

            // Saved as typed: update_config_define() escapes quotes and
            // backslashes, so a password with a quote in it still works. A
            // text box never sends a control character; one in a forged
            // request would put a line break inside the define, where the
            // next save could no longer find it.
            $value = (isset($_POST[$field]) && is_string($_POST[$field])) ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $_POST[$field])) : '';

            // A connected account is named by its number, and only one that
            // was connected for sending e-mail can be chosen.
            if (($key === 'SYSTEM_MAIL_CONNECTION') || ($key === 'CAMPAIGN_MAIL_CONNECTION')) {
                $chosen = pg_connect_account((int) $value);
                $value = (is_array($chosen) && ($chosen['user_id'] === 0) && in_array('mail', $chosen['purposes'], true)) ? (string) (int) $chosen['id'] : '';
            }

            $smtp_audit_was = defined($key) ? (string) constant($key) : '';

            if ($key === 'EMAIL_CAMPAIGN_JOB') {
                $smtp_audit_was = (defined($key) && ((constant($key) === true) || (constant($key) === 'true'))) ? 'true' : 'false';
            }

            $smtp_audit_before[$field] = $smtp_audit_was;

            if ($key === 'EMAIL_CAMPAIGN_JOB') {
                $smtp_audit_after[$field] = ($value === 'true') ? 'true' : 'false';
            } elseif (($value === '') && (($key === 'SYSTEM_SMTP_PASSWORD') || ($key === 'CAMPAIGN_SMTP_PASSWORD'))) {
                $smtp_audit_after[$field] = $smtp_audit_was;
            } else {
                $smtp_audit_after[$field] = $value;
            }

            // The job switch is stored as a real boolean. The quoted forms
            // 'true' / 'false' written by older versions are removed first,
            // because the string 'false' is truthy and could never switch
            // the job off.
            if ($key === 'EMAIL_CAMPAIGN_JOB') {
                $value = ($value === 'true') ? 'true' : 'false';
                $config_file_content = preg_replace("/[ \t]*define\s*\(\s*'$key'\s*,\s*'[^']*'\s*\);\r?\n?/i", '', $config_file_content);

                if (preg_match("/define\s*\(\s*'$key'\s*,\s*(?:true|false)\s*\);/i", $config_file_content)) {
                    $config_file_content = preg_replace("/define\s*\(\s*'$key'\s*,\s*(?:true|false)\s*\);/i", "define('$key', $value);", $config_file_content);
                } else {
                    $config_file_content = pg_config_insert_line($config_file_content, "define('$key', $value);");
                }

                continue;
            }

            // A blank password box means "keep the saved value": the screen never
            // renders a stored secret back, so an empty box is not a request to
            // clear it.
            if ($value === '' && ($key === 'SYSTEM_SMTP_PASSWORD' || $key === 'CAMPAIGN_SMTP_PASSWORD')) {
                continue;
            }

            // The shared helpers confine every match to the key's own line. A
            // hand-edited config.php mixes LF and CRLF endings, and a pattern
            // anchored on "\r\n" ran past an LF line and deleted the defines
            // that followed it.
            if ($value === '') {
                $config_file_content = remove_config_define($config_file_content, $key, 'string');
            } else {
                $config_file_content = update_config_define($config_file_content, $key, $value, 'string');
            }
        }

        // Written only from a successful read: an empty result would replace
        // config.php with an empty file and take the site down. The write
        // goes through a temp file and a rename, so a failure leaves the old
        // file as it was.
        if (trim($config_file_content) === '') {
            $liveform_settings->add_error(lang('Config file could not be opened.'));
        } elseif (!pg_write_config_file($config_file_content)) {
            $liveform_settings->add_error(lang('The config file could not be written.'));
        } else {
            log_activity(lang("SMTP Settings was modified"), $_SESSION['sessionusername']);
            pg_audit('update', 'settings', 0, $smtp_audit_before, $smtp_audit_after, 'smtp');
            $liveform_settings->add_notice(lang('The smtp settings have been saved.'));
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // fallback
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
    exit;
}