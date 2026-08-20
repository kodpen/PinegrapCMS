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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */


include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');

$liveform = new liveform('smtp_settings');

// Helper functions
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('escape')) {
    function escape($s) { return addslashes($s); }
}
if (!function_exists('db_value')) {
    function db_value($query) {
        $result = db($query);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_row($result);
            return $row ? $row[0] : null;
        }
        return null;
    }
}

// SMTP credentials check
$smtp_ready = (
    defined('SYSTEM_SMTP_HOSTNAME') && SYSTEM_SMTP_HOSTNAME !== '' &&
    defined('SYSTEM_SMTP_USERNAME') && SYSTEM_SMTP_USERNAME !== '' &&
    defined('SYSTEM_SMTP_PASSWORD') && SYSTEM_SMTP_PASSWORD !== ''
);

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

    // Buttons
    $system_buttons = '';
    if ($smtp_ready) {
        $system_buttons = '
            <button type="submit" id="send_system_email" name="send_system_email" value="Send System Test Email" class="btn my-1 btn-warning">
                <span class="material-icons me-2">forward_to_inbox</span>
                <span class="btn-text">' . lang('Send Test') . '</span>
            </button>';
    }

    $dkim_generate_button = '
        <button type="submit" name="generate_dkim_key" value="true" class="btn my-1 btn-secondary">
            <span class="material-icons me-2">vpn_key</span>
            <span class="btn-text">' . lang('Generate DKIM Key') . '</span>
        </button>';
    $dkim_buttons = $dkim_generate_button;
    if ($smtp_ready) {
        $dkim_buttons .= '
            <button type="submit" name="test_dkim" value="true" class="btn my-1 btn-info">
                <span class="material-icons me-2">verified</span>
                <span class="btn-text">' . lang('Test DKIM') . '</span>
            </button>';
    }




    $dkim_settings = '
    <div class="col-12 col-md-8 col-lg-6  my-2">
        <div class="input-group">
            ' . $liveform->output_field([
                'type'=>'text',
                'name'=>'dkim_selector',
                'id'=>'dkim_selector',
                'class'=>'form-control text-end',
                'placeholder'=>'Selector'
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
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            ' . $dkim_settings . '
                            <div class="col-12 my-2">
                                <label for="textrecord" class="form-label">' . lang('Add this TXT record to your DNS:') . '</label>
                                <textarea id="textrecord" class="form-control" rows="4" readonly>' . h($txt_record) . '</textarea>
                                <div class="mt-2">' . $dkim_buttons . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    } else {
        $dkim_txt_preview = '
            <div class="col-12">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('DKIM TXT Record') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            ' . $dkim_settings . '
                            <div class="col-12 my-2">
                                <p class="text-muted">' . lang('No DKIM key generated yet.') . '</p>
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
        'cancel'=>['enable'=>true,'title'=>lang('Return to Settings'),'onclick'=>"window.location.href='settings.php'"]
    ]) . '
            ' . get_codemirror_includes() . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . $liveform->get_warnings() . $liveform->output_notices() . '
                
				<div class="row mb-2  flex-wrap">
					<div class="col-12 col-sm-12 text-center text-md-start">
						<h2 class="d-inline-block " data-bs-content="' . lang('Update the Smtp settings for the Software Mail Function.') . '" title="' . lang('SMTP Settings') . '">' . lang('SMTP Settings') . '</h2>
					</div>
				</div>
                <form name="form" action="smtp_settings.php" method="post" autocomplete="off"  submitshortcut="submit_save">
                    ' . get_token_field() . '
                    <div class="row">
                        <!-- System SMTP -->
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('System') . '</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-8 my-2">
                                            <label for="system_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_hostname','id'=>'system_smtp_hostname','class'=>'form-control']) . '
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="system_smtp_port" class="form-label">' . lang('Port') . '</label>
                                            ' . $liveform->output_field(['type'=>'number','name'=>'system_smtp_port','id'=>'system_smtp_port','class'=>'form-control','placeholder'=>'587']) . '
                                        </div>
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="system_smtp_username" class="form-label">' . lang('Username') . '</label>
                                            ' . $liveform->output_field(['type'=>'text','name'=>'system_smtp_username','id'=>'system_smtp_username','class'=>'form-control','autocomplete'=>'new-password']) . '
                                        </div>
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="system_smtp_password" class="form-label">' . lang('Password') . '</label>
                                            ' . $liveform->output_field(['type'=>'password','name'=>'system_smtp_password','id'=>'system_smtp_password','class'=>'form-control','autocomplete'=>'new-password']) . '
                                        </div>
                                    </div>
                                    <div class="mt-3">' . $system_buttons . '</div>
                                </div>
                            </div>
                        </div>
                        <!-- Campaign SMTP -->
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Campaign') . '
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch my-2">
                                        ' . $liveform->output_field([
                                            'type'=>'checkbox',
                                            'name'=>'email_campaign_job',
                                            'id'=>'email_campaign_job',
                                            'class'=>'form-check-input collapse-switcher',
                                            'value'=>'true',
                                            'data-bs-target'=>'#email_campaign_job_row'
                                        ]) . '
                                        <label class="form-check-label" for="email_campaign_job">' . lang('Enable Email Campaign Job') . '</label>
                                    </div>
                                    <div class="collapse" id="email_campaign_job_row">
                                        <div class="row">
                                            <div class="col-12 col-lg-6 my-2">
                                                <label for="campaign_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_hostname','id'=>'campaign_smtp_hostname','class'=>'form-control']) . '
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-3 my-2">
                                                <label for="email_campaign_job_number_of_emails" class="form-label">' . lang('Max.') . '</label>
                                                ' . $liveform->output_field(['type'=>'number','name'=>'email_campaign_job_number_of_emails','id'=>'email_campaign_job_number_of_emails','class'=>'form-control','placeholder'=>'25']) . '
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-3 my-2">
                                                <label for="campaign_smtp_port" class="form-label">' . lang('Port') . '</label>
                                                ' . $liveform->output_field(['type'=>'number','name'=>'campaign_smtp_port','id'=>'campaign_smtp_port','class'=>'form-control','placeholder'=>'587']) . '
                                            </div>
                                            <div class="col-12 col-lg-6 my-2">
                                                <label for="campaign_smtp_username" class="form-label">' . lang('Username') . '</label>
                                                ' . $liveform->output_field(['type'=>'text','name'=>'campaign_smtp_username','id'=>'campaign_smtp_username','class'=>'form-control','autocomplete'=>'new-password']) . '
                                            </div>
                                            <div class="col-12 col-lg-6 my-2">
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

                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success">
                                    <span class="material-icons me-2">save</span>
                                    <span class="btn-text">' . lang(['string'=>'Save']) . '</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' . output_footer();

    $liveform->remove_form();
    exit;

} else {
    validate_token_field();
    $liveform_settings = new liveform('smtp_settings');

    // DKIM key generate
    if (isset($_POST['generate_dkim_key']) && $_POST['generate_dkim_key'] === 'true') {
        $selector = trim(isset($_POST['dkim_selector']) ? $_POST['dkim_selector'] : '');
        $domain   = trim(isset($_POST['dkim_domain']) ? $_POST['dkim_domain'] : '');


        if ($selector === '' || $domain === '') {
            $liveform_settings->add_warning(lang('DKIM domain and selector must be filled before generating key.'));
        } else {
            // Eski kayıtları ve dosyaları sil
            db("DELETE FROM files WHERE name IN ('dkim.key','dkim.pub')");
            @unlink(FILE_DIRECTORY_PATH . '/dkim.key');
            @unlink(FILE_DIRECTORY_PATH . '/dkim.pub');

            $res = openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
            if ($res) {
                openssl_pkey_export($res, $private_pem);
                $details = openssl_pkey_get_details($res);
                $public_pem = isset($details['key']) ? $details['key'] : '';
                $pub = str_replace(
                    ["-----BEGIN PUBLIC KEY-----","-----END PUBLIC KEY-----","\r","\n"],
                    '',
                    $public_pem
                );
                $pub = trim($pub);
                $txt_record = $selector . '._domainkey.' . $domain . ' IN TXT "v=DKIM1; k=rsa; p=' . $pub . '"';

                // Private key dosyası
                file_put_contents(FILE_DIRECTORY_PATH . '/dkim.key', $private_pem);
                $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.key');
                db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
                    VALUES ('dkim.key','" . escape(getPublicRootFolderId()) . "','" . escape($txt_record) . "','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

                // Public key dosyası
                file_put_contents(FILE_DIRECTORY_PATH . '/dkim.pub', $public_pem);
                $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.pub');
                db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
                    VALUES ('dkim.pub','" . escape(getPublicRootFolderId()) . "','DKIM Public Key (PEM)','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

                $liveform_settings->add_notice(
                    lang('DKIM key pair generated successfully.') .
                    '<br><label class="form-label mt-2">' . lang('Add this TXT record to your DNS:') . '</label>' .
                    '<textarea class="form-control" rows="4" readonly>' . h($txt_record) . '</textarea>'
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

    // Test e-postası gönderimi
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
            $liveform_settings->add_notice(lang(['string'=>'Test email accepted by SMTP server. Please check mailbox: {var:1}', 'vars'=>[EMAIL_ADDRESS]]));
            log_activity(lang(['string'=>'Test email accepted by SMTP server. Mailbox: {var:1}', 'vars'=>[EMAIL_ADDRESS]]), $_SESSION['sessionusername']);
        } else {
            $liveform_settings->add_warning(lang('Test email could not be sent. Check logs for details.'));
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // Ayarları kaydet
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
            'DKIM_DOMAIN','DKIM_SELECTOR'
        ];

        foreach ($config_keys as $key) {
            $field = strtolower($key);
            $value = isset($_POST[$field]) ? trim(str_replace("'", '', $_POST[$field])) : '';

            if ($key === 'EMAIL_CAMPAIGN_JOB') {
                $value = ($value === 'true') ? 'true' : 'false';
            }

            if (defined($key)) {
                if ($value !== '' && constant($key) !== $value && preg_match("/define\('$key', '(.*?)'\);/si", $config_file_content)) {
                    $config_file_content = preg_replace("/define\('$key', '(.*?)'\);/si", "define('$key', '$value');", $config_file_content);
                } elseif ($value === '') {
                    $config_file_content = preg_replace("/define\('$key', '(.*?)'\);\r\n/si", '', $config_file_content);
                }
            } else {
                if ($value !== '') {
                    $config_file_content = str_replace('?>', "define('$key', '$value');\r\n?>", $config_file_content);
                }
            }
        }

        $handle = fopen(CONFIG_FILE_PATH, 'w');
        if ($handle) {
            fwrite($handle, $config_file_content);
            fclose($handle);
        }

        log_activity(lang("SMTP Settings was modified"), $_SESSION['sessionusername']);
        $liveform_settings->add_notice(lang('The smtp settings have been saved.'));

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
        exit;
    }

    // fallback
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php');
    exit;
}