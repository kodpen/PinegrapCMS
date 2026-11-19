<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What Site Settings › E-mail sending (includes/settings/mail_sending.php)
 * saves and runs: what the site's
 * mail is sent through, the campaign job, DKIM and the test e-mail. The settings are define() lines of
 * config.php; a sending service's account is a connect_apps row
 * (includes/fn/mail_transport.php) and a connected account is chosen by its
 * number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The config.php defines E-mail sending writes, in the order they are saved.
 *
 * @return string[]
 */
function pg_mail_settings_keys()
{
    return array(
        'SYSTEM_SMTP_HOSTNAME', 'SYSTEM_SMTP_USERNAME', 'SYSTEM_SMTP_PASSWORD', 'SYSTEM_SMTP_PORT',
        'EMAIL_CAMPAIGN_JOB', 'EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
        'CAMPAIGN_SMTP_HOSTNAME', 'CAMPAIGN_SMTP_USERNAME', 'CAMPAIGN_SMTP_PASSWORD', 'CAMPAIGN_SMTP_PORT',
        'DKIM_DOMAIN', 'DKIM_SELECTOR',
        'SYSTEM_MAIL_CONNECTION', 'CAMPAIGN_MAIL_CONNECTION',
    );
}

/**
 * Whether the saved settings name a way to send: an SMTP account, a
 * connected account or a sending service.
 *
 * @return bool
 */
function pg_mail_settings_ready()
{
    return (
        defined('SYSTEM_SMTP_HOSTNAME') && SYSTEM_SMTP_HOSTNAME !== '' &&
        defined('SYSTEM_SMTP_USERNAME') && SYSTEM_SMTP_USERNAME !== '' &&
        defined('SYSTEM_SMTP_PASSWORD') && SYSTEM_SMTP_PASSWORD !== ''
    ) || (pg_mail_connection('system') !== null) || (pg_mail_transport() !== 'smtp');
}

/**
 * The providers a site account for sending e-mail can be connected with:
 * those whose application is set up on Connections.
 *
 * @return array provider key => provider
 */
function pg_mail_settings_providers()
{
    $providers = array();

    if (!pg_connect_schema_ready() || !pg_connect_can_seal()) {
        return $providers;
    }

    foreach (pg_connect_providers() as $key => $provider) {
        if (pg_connect_purpose_allows(pg_connect_purpose('mail'), $key, 'site') && pg_connect_app_ready(pg_connect_app($key))) {
            $providers[$key] = $provider;
        }
    }

    return $providers;
}

/**
 * What a kind of mail is sent through: this server (SMTP or mail()) or one
 * of the site's connected accounts.
 *
 * @param string $name the field's name (system_mail_connection, campaign_mail_connection)
 * @param int|string $current the stored account number
 * @param array $accounts pg_connect_accounts() of purpose mail
 * @return string
 */
function pg_mail_settings_connection_field($name, $current, array $accounts)
{
    $options = '<option value="">' . h(lang('This server: the SMTP account below, or PHP mail() without one')) . '</option>';

    foreach ($accounts as $account) {
        $provider = pg_connect_provider($account['provider']);

        $options .= '<option value="' . (int) $account['id'] . '"' . (((int) $current === (int) $account['id']) ? ' selected' : '') . '>'
            . h($account['label'] . ' (' . (is_array($provider) ? $provider['label'] : $account['provider']) . ')'
                . (($account['status'] !== 'active') ? ' - ' . lang('Connect again') : ''))
            . '</option>';
    }

    return '
                                            <label for="setting_' . h($name) . '" class="form-label">' . h(lang('Send through')) . '</label>
                                            <select name="' . h($name) . '" id="setting_' . h($name) . '" class="form-control">' . $options . '</select>';
}

/**
 * The Connect buttons under the system mail's account, and the line above
 * them. Each button submits a hidden form outside the settings form
 * (includes/settings/mail_sending.php).
 *
 * @param array $providers pg_mail_settings_providers()
 * @param array $accounts pg_connect_accounts() of purpose mail
 * @return string
 */
function pg_mail_settings_connect_buttons(array $providers, array $accounts)
{
    $buttons = '';

    foreach ($providers as $key => $provider) {
        $buttons .= '
                                                <button type="submit" form="pgset_mail_connect_' . h($key) . '" class="pg-button pg-button-quiet pg-button-sm me-1 mt-1">
                                                    <i class="bi ' . h($provider['icon']) . ' me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $provider['label']))) . '</span></button>';
    }

    $text = $accounts
        ? lang('A connected account sends without an SMTP password; the message is from that account and replies go to the address it was meant to come from.')
        : lang('Or send from a Google or Microsoft account, without an SMTP password.');

    $link = $providers
        ? ''
        : ' <a href="connections.php">' . h(lang('Set up the application on Connections first.')) . '</a>';

    return '<div class="form-text">' . h($text) . $link . '</div>' . (($buttons !== '') ? '<div>' . $buttons . '</div>' : '');
}

/**
 * Writes the posted boxes to config.php. A refusal marks its field or adds
 * an error to the form and writes nothing.
 *
 * Returns the values as saved, by lower-case name (a blank password box
 * keeps the stored one), or false. The constants of this request keep the
 * values they were loaded with; the saved ones apply from the next request.
 *
 * @param array $post the request's fields
 * @param object $liveform the form the marks and the messages go to
 * @return array|false
 */
function pg_mail_settings_save(array $post, $liveform)
{
    // An empty password box keeps the saved password only for the server it
    // was typed for (pg_smtp_password_kept()). For a new server it is dropped,
    // and a save that would still sign in there with it is refused before
    // anything is written.
    $kept_passwords = array();

    foreach (array('system', 'campaign') as $kind) {
        $prefix = strtoupper($kind) . '_SMTP_';
        $posted = array();

        foreach (array('hostname' => 'host', 'port' => 'port', 'password' => 'password', 'username' => 'user') as $field => $name) {
            $posted[$name] = (isset($post[$kind . '_smtp_' . $field]) && is_string($post[$kind . '_smtp_' . $field])) ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $post[$kind . '_smtp_' . $field])) : '';
        }

        $stored = array(
            'host'     => defined($prefix . 'HOSTNAME') ? (string) constant($prefix . 'HOSTNAME') : '',
            'port'     => defined($prefix . 'PORT') ? (string) constant($prefix . 'PORT') : '',
            'password' => defined($prefix . 'PASSWORD') ? (string) constant($prefix . 'PASSWORD') : '',
        );

        $kept_passwords[$prefix . 'PASSWORD'] = pg_smtp_password_kept($posted, $stored);

        if (($posted['password'] === '') && ($stored['password'] !== '') && ($kept_passwords[$prefix . 'PASSWORD'] === '')
            && ($posted['host'] !== '') && ($posted['user'] !== '')) {
            $liveform->mark_error($kind . '_smtp_password', lang('The SMTP server or its port changed. Enter the password again.'));

            return false;
        }
    }

    // The sending service first: a field it marks leaves every setting as
    // it was.
    if (!pg_mail_transport_settings_save($post, USER_ID, $liveform)) {
        return false;
    }

    $config_file_content = '';
    $fd = fopen(CONFIG_FILE_PATH, 'r');

    if ($fd) {
        while (!feof($fd)) {
            $config_file_content .= fgets($fd, 4096);
        }

        fclose($fd);
    }

    // The defines as this request loaded them and as they are written, for
    // the audit trail.
    $audit_before = array();
    $audit_after = array();

    foreach (pg_mail_settings_keys() as $key) {
        $field = strtolower($key);

        // Saved as typed: update_config_define() escapes quotes and
        // backslashes, so a password with a quote in it still works. A text
        // box never sends a control character; one in a forged request would
        // put a line break inside the define, where the next save could no
        // longer find it.
        $value = (isset($post[$field]) && is_string($post[$field])) ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $post[$field])) : '';

        // A connected account is named by its number, and only one that was
        // connected for sending e-mail can be chosen.
        if (($key === 'SYSTEM_MAIL_CONNECTION') || ($key === 'CAMPAIGN_MAIL_CONNECTION')) {
            $chosen = pg_connect_account((int) $value);
            $value = (is_array($chosen) && ($chosen['user_id'] === 0) && in_array('mail', $chosen['purposes'], true)) ? (string) (int) $chosen['id'] : '';
        }

        $was = defined($key) ? (string) constant($key) : '';

        if ($key === 'EMAIL_CAMPAIGN_JOB') {
            $was = (defined($key) && ((constant($key) === true) || (constant($key) === 'true'))) ? 'true' : 'false';
        }

        $audit_before[$field] = $was;

        if ($key === 'EMAIL_CAMPAIGN_JOB') {
            $audit_after[$field] = ($value === 'true') ? 'true' : 'false';
        } elseif (($value === '') && (($key === 'SYSTEM_SMTP_PASSWORD') || ($key === 'CAMPAIGN_SMTP_PASSWORD'))) {
            $audit_after[$field] = $kept_passwords[$key];
        } else {
            $audit_after[$field] = $value;
        }

        // The job switch is stored as a real boolean. The quoted forms 'true'
        // and 'false' written by older versions are removed first, because the
        // string 'false' is truthy and could never switch the job off.
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

        // A blank password box means "keep the saved value": the card never
        // draws a stored secret back, so an empty box is not a request to
        // clear it -- unless the server changed, and then it is removed.
        if (($value === '') && (($key === 'SYSTEM_SMTP_PASSWORD') || ($key === 'CAMPAIGN_SMTP_PASSWORD'))) {
            if ($kept_passwords[$key] !== '') {
                continue;
            }
        }

        // The shared helpers confine every match to the key's own line. A
        // hand-edited config.php mixes LF and CRLF endings, and a pattern
        // anchored on "\r\n" ran past an LF line and deleted the defines that
        // followed it.
        if ($value === '') {
            $config_file_content = remove_config_define($config_file_content, $key, 'string');
        } else {
            $config_file_content = update_config_define($config_file_content, $key, $value, 'string');
        }
    }

    // Written only from a successful read: an empty result would replace
    // config.php with an empty file and take the site down. The write goes
    // through a temp file and a rename, so a failure leaves the old file as
    // it was.
    if (trim($config_file_content) === '') {
        $liveform->add_error(lang('Config file could not be opened.'));

        return false;
    }

    if (!pg_write_config_file($config_file_content)) {
        $liveform->add_error(lang('The config file could not be written.'));

        return false;
    }

    pg_audit('update', 'settings', 0, $audit_before, $audit_after, 'smtp');

    return $audit_after;
}

/**
 * "Save and generate a DKIM key": a new key pair for the saved selector and
 * domain. The new pair is made before the old one is touched, so when
 * OpenSSL cannot make a key the pair in use keeps signing.
 *
 * @param array $saved pg_mail_settings_save()'s answer
 * @param object $liveform
 * @return void
 */
function pg_mail_settings_dkim_generate(array $saved, $liveform)
{
    $selector = $saved['dkim_selector'];
    $domain = $saved['dkim_domain'];

    if (($selector === '') || ($domain === '')) {
        $liveform->add_warning(lang('DKIM domain and selector must be filled before generating key.'));

        return;
    }

    $pair = pg_dkim_key_pair($selector, $domain);

    if (!$pair) {
        $liveform->add_warning(lang('The DKIM key could not be generated on this server. Any existing DKIM key was left unchanged.'));

        return;
    }

    // Each file is written as a whole over the old one, dkim.pub first and
    // dkim.key second, so a failed key write leaves the signing key as it
    // was; dkim.pub is then put back (or removed when there was none) to keep
    // the two files a pair. The records change only once both are written.
    $saved_pair = false;
    $had_pub = is_file(FILE_DIRECTORY_PATH . '/dkim.pub');
    $old_pub = $had_pub ? @file_get_contents(FILE_DIRECTORY_PATH . '/dkim.pub') : false;

    if (pg_file_write_whole(FILE_DIRECTORY_PATH . '/dkim.pub', $pair['public'])) {
        if (pg_file_write_whole(FILE_DIRECTORY_PATH . '/dkim.key', $pair['private'])) {
            // Owner-only: the private key must not be readable by other
            // accounts. A failure is ignored so Windows/IIS installs work.
            @chmod(FILE_DIRECTORY_PATH . '/dkim.key', 0600);
            $saved_pair = true;
        } elseif ($old_pub !== false) {
            pg_file_write_whole(FILE_DIRECTORY_PATH . '/dkim.pub', $old_pub);
        } elseif (!$had_pub) {
            @unlink(FILE_DIRECTORY_PATH . '/dkim.pub');
        }
    }

    if (!$saved_pair) {
        $liveform->add_warning(lang('The DKIM key could not be saved. Any existing DKIM key was left unchanged.'));

        return;
    }

    db("DELETE FROM files WHERE name IN ('dkim.key','dkim.pub')");

    $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.key');
    db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
        VALUES ('dkim.key','" . escape(getPublicRootFolderId()) . "','" . escape($pair['txt_record']) . "','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

    $size = @filesize(FILE_DIRECTORY_PATH . '/dkim.pub');
    db("INSERT INTO files (name,folder,description,type,size,design,user,timestamp)
        VALUES ('dkim.pub','" . escape(getPublicRootFolderId()) . "','DKIM Public Key (PEM)','application/x-pem-file','" . escape($size) . "',1,'" . USER_ID . "',UNIX_TIMESTAMP())");

    // The record to publish is on the DKIM card after the save, so the notice
    // stays text.
    $liveform->add_notice(lang('DKIM key pair generated successfully.') . ' ' . lang('Add the TXT record shown on the DKIM TXT Record card to your DNS.'));
    log_activity(lang('DKIM key pair generated, files saved, TXT suggestion stored.'), $_SESSION['sessionusername']);
}

/**
 * "Save and test" on the DKIM card: whether the TXT record published for the
 * saved selector and domain is the generated key's.
 *
 * @param array $saved pg_mail_settings_save()'s answer
 * @param object $liveform
 * @return void
 */
function pg_mail_settings_dkim_test(array $saved, $liveform)
{
    $selector = $saved['dkim_selector'];
    $domain = $saved['dkim_domain'];
    $txt_record = db_value("SELECT description FROM files WHERE name = 'dkim.key'");

    if (!$selector || !$domain || !$txt_record) {
        $liveform->add_warning(lang('DKIM domain/selector or key not set.'));

        return;
    }

    $records = dns_get_record($selector . '._domainkey.' . $domain, DNS_TXT);
    $dns_txt = '';

    if ($records) {
        foreach ($records as $record) {
            $dns_txt .= $record['txt'] . ' ';
        }

        $dns_txt = trim($dns_txt);
    }

    if ($dns_txt && (strpos($txt_record, $dns_txt) !== false)) {
        $liveform->add_notice(lang('DKIM test successful. DNS record matches generated key.'));
        log_activity(lang('DKIM test successful.'), $_SESSION['sessionusername']);
    } else {
        $liveform->add_warning(lang('DKIM test failed. DNS record not matching or not found.'));
    }
}

/**
 * The test e-mail "Save and send a test" asked for. The save wrote
 * config.php and the screen is drawn again in a new request, whose constants
 * hold the saved settings, so the test is sent there. The flag goes first,
 * so a reload does not send again.
 *
 * @param object $liveform
 * @return void
 */
function pg_mail_settings_send_test($liveform)
{
    if (empty($_SESSION['smtp_settings_send_test'])) {
        return;
    }

    unset($_SESSION['smtp_settings_send_test']);

    if (!pg_mail_settings_ready()) {
        $liveform->add_warning(h(lang('No test email was sent: the saved settings name no sending service, SMTP account or connected account.')));

        return;
    }

    $result = email(array(
        'to'                 => EMAIL_ADDRESS,
        'to_name'            => ORGANIZATION_NAME,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'reply_to'           => EMAIL_ADDRESS,
        'subject'            => lang('System SMTP Settings Test'),
        'body'               => lang('Your System smtp settings work correctly.'),
        'format'             => 'plain_text',
        'type'               => 'system',
        'notify_sender'      => false,
    ));

    $transport = pg_mail_transport();
    $transports = pg_mail_transports();

    if ($result && ($transport !== 'smtp') && (pg_mail_connection('system') === null)) {
        $liveform->add_notice(lang(array('string' => 'Test email accepted by {var:1}. Please check mailbox: {var:2}', 'vars' => array(h($transports[$transport]['label']), h(EMAIL_ADDRESS)))));
        log_activity(lang(array('string' => 'Test email accepted by {var:1}. Mailbox: {var:2}', 'vars' => array($transports[$transport]['label'], EMAIL_ADDRESS))), $_SESSION['sessionusername']);
    } elseif ($result) {
        $liveform->add_notice(lang(array('string' => 'Test email accepted by SMTP server. Please check mailbox: {var:1}', 'vars' => array(h(EMAIL_ADDRESS)))));
        log_activity(lang(array('string' => 'Test email accepted by SMTP server. Mailbox: {var:1}', 'vars' => array(EMAIL_ADDRESS))), $_SESSION['sessionusername']);
    } else {
        $liveform->add_warning(h(pg_mail_failure_sentence(lang('Test email could not be sent.'), true)));
    }
}
