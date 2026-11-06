<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Mail transports: sending e-mail through a provider's HTTP API instead of
 * the SMTP server (contract in includes/mail/driver.php).
 *
 * email() builds every message with PHPMailer as before. When no server or
 * connected account of the message's own and no connected account chosen on
 * SMTP Settings applies, and a transport is switched on, the built message is
 * read back into a plain array (pg_mail_transport_message()) and handed to
 * the driver instead of the SMTP server. Nothing switched on: SMTP as before.
 *
 * The account is kept in connect_apps under 'mail_<key>', as the SMS
 * providers' accounts are: the driver's one secret field (API key, server
 * token) sealed with the site key and never drawn back into the form, the
 * other fields among the options, the transport in use as enabled. One
 * transport is on at a time; switching one on switches the others off, and
 * their accounts are kept. No schema change: a driver added later needs no
 * column.
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
 * The transports a site can send through.
 *
 * @return array key => array(label, file under includes/mail/, portal)
 */
function pg_mail_transports()
{
    return array(
        'postmark' => array('label' => 'Postmark', 'file' => 'postmark.php', 'portal' => 'https://account.postmarkapp.com/servers'),
        'mailgun'  => array('label' => 'Mailgun', 'file' => 'mailgun.php', 'portal' => 'https://app.mailgun.com/'),
        'brevo'    => array('label' => 'Brevo', 'file' => 'brevo.php', 'portal' => 'https://app.brevo.com/settings/keys/api'),
        'resend'   => array('label' => 'Resend', 'file' => 'resend.php', 'portal' => 'https://resend.com/api-keys'),
        'ses'      => array('label' => 'Amazon SES', 'file' => 'ses.php', 'portal' => 'https://console.aws.amazon.com/ses/home'),
    );
}

/**
 * Loads a transport's driver.
 *
 * @param string $key
 * @return bool whether its required functions are there
 */
function pg_mail_transport_driver($key)
{
    $transports = pg_mail_transports();

    if (!is_string($key) || !isset($transports[$key])) {
        return false;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/mail/' . $transports[$key]['file'];

    if (!function_exists('pg_mail_' . $key . '_send') && is_file($file)) {
        require_once($file);
    }

    foreach (array('fields', 'capabilities', 'request', 'answer', 'send') as $operation) {
        if (!function_exists('pg_mail_' . $key . '_' . $operation)) {
            return false;
        }
    }

    return true;
}

/**
 * A driver's fields.
 *
 * @param string $key
 * @return array name => field (includes/mail/driver.php), empty for none
 */
function pg_mail_transport_fields($key)
{
    if (!pg_mail_transport_driver($key)) {
        return array();
    }

    $fields = call_user_func('pg_mail_' . $key . '_fields');

    return is_array($fields) ? $fields : array();
}

/**
 * The connect_apps key a transport's account is kept under.
 *
 * @param string $key
 * @return string
 */
function pg_mail_transport_app_key($key)
{
    return 'mail_' . $key;
}

/**
 * A transport's account on this site.
 *
 * @param string $key
 * @return array values (field name => value, the secret left out), secret
 *               ('' when none or when the site's key cannot read it),
 *               secret_stored, enabled
 */
function pg_mail_transport_settings($key)
{
    $app = (function_exists('pg_connect_app') && pg_connect_schema_ready()) ? pg_connect_app(pg_mail_transport_app_key($key)) : null;
    $values = array();

    foreach (pg_mail_transport_fields($key) as $name => $field) {
        if ($field['type'] === 'secret') {
            continue;
        }

        $values[$name] = (is_array($app) && isset($app['options'][$name]) && is_scalar($app['options'][$name]))
            ? (string) $app['options'][$name]
            : (isset($field['default']) ? (string) $field['default'] : '');
    }

    return array(
        'values'        => $values,
        'secret'        => is_array($app) ? (string) $app['client_secret'] : '',
        'secret_stored' => is_array($app) && $app['secret_stored'],
        'enabled'       => is_array($app) && $app['enabled'],
    );
}

/**
 * Whether an account has everything a message needs.
 *
 * @param array $fields   pg_mail_transport_fields()
 * @param array $settings pg_mail_transport_settings()
 * @return bool
 */
function pg_mail_transport_complete($fields, $settings)
{
    if (!$fields || ($settings['secret'] === '')) {
        return false;
    }

    foreach ($fields as $name => $field) {
        if (($field['type'] !== 'secret') && !empty($field['required']) && (!isset($settings['values'][$name]) || ($settings['values'][$name] === ''))) {
            return false;
        }
    }

    return true;
}

/**
 * The transport messages go through: the one switched on with a complete
 * account, else 'smtp' (the SMTP server, a connected account or mail(), as
 * before transports existed). Read once a request; a queue run sends many.
 *
 * @param bool $reset read again (after the settings were saved)
 * @return string a pg_mail_transports() key or 'smtp'
 */
function pg_mail_transport($reset = false)
{
    static $transport = null;

    if (($transport !== null) && !$reset) {
        return $transport;
    }

    $transport = 'smtp';

    foreach (array_keys(pg_mail_transports()) as $key) {
        $settings = pg_mail_transport_settings($key);

        if ($settings['enabled'] && pg_mail_transport_complete(pg_mail_transport_fields($key), $settings)) {
            $transport = $key;
            break;
        }
    }

    return $transport;
}

/**
 * An address as a header names it: "Name" <address>, or the address alone.
 *
 * @param string $address
 * @param string $name
 * @return string
 */
function pg_mail_transport_address($address, $name = '')
{
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $name));

    if ($name === '') {
        return (string) $address;
    }

    return '"' . addcslashes($name, '"\\') . '" <' . $address . '>';
}

/**
 * A message PHPMailer was given, as the drivers take it
 * (includes/mail/driver.php). Attachments named by a path are read here.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $mail configured as email() does
 * @param string                        $type 'system' or 'campaign'
 * @param bool                          $raw  also the finished message as
 *                                            an SMTP server receives it
 *                                            ('mime', without Bcc), for a
 *                                            driver that sends it whole
 * @return array
 * @throws \PHPMailer\PHPMailer\Exception when $raw and PHPMailer cannot
 *                                        build it
 */
function pg_mail_transport_message($mail, $type, $raw = false)
{
    $html = ($mail->ContentType === 'text/html');
    $headers = array();

    foreach ($mail->getCustomHeaders() as $header) {
        $headers[(string) $header[0]] = (string) $header[1];
    }

    $attachments = array();

    foreach ($mail->getAttachments() as $attachment) {
        $content = $attachment[5] ? (string) $attachment[0] : (string) @file_get_contents($attachment[0]);

        $attachments[] = array(
            'name'    => (string) $attachment[2],
            'type'    => (string) $attachment[4],
            'content' => $content,
            'cid'     => ((string) $attachment[6] === 'inline') ? (string) $attachment[7] : '',
        );
    }

    return array(
        'from'        => (string) $mail->From,
        'from_name'   => (string) $mail->FromName,
        'to'          => array_values($mail->getToAddresses()),
        'cc'          => array_values($mail->getCcAddresses()),
        'bcc'         => array_values($mail->getBccAddresses()),
        'reply_to'    => array_values($mail->getReplyToAddresses()),
        'subject'     => (string) $mail->Subject,
        'text'        => $html ? (string) $mail->AltBody : (string) $mail->Body,
        'html'        => $html ? (string) $mail->Body : '',
        'headers'     => $headers,
        'attachments' => $attachments,
        'type'        => ($type === 'campaign') ? 'campaign' : 'system',
        'mime'        => ($raw && $mail->preSend()) ? $mail->getSentMIMEMessage() : '',
    );
}

/**
 * The answer of a send, in one shape.
 *
 * @param bool   $ok
 * @param string $id        the provider's id of the message
 * @param string $error     a sentence for the operator, '' on success
 * @param bool   $permanent sending the same message again fails the same way
 * @return array ok, id, error, permanent
 */
function pg_mail_transport_result($ok, $id = '', $error = '', $permanent = false)
{
    return array('ok' => (bool) $ok, 'id' => (string) $id, 'error' => (string) $error, 'permanent' => !$ok && $permanent);
}

/**
 * Whether a refusal with this HTTP status is the message's own fault, so the
 * queue gives it up: a 4xx, except a key the operator can still fix (401,
 * 403), a timeout (408) and a rate limit (429).
 *
 * @param int $status
 * @return bool
 */
function pg_mail_transport_status_permanent($status)
{
    $status = (int) $status;

    return ($status >= 400) && ($status < 500) && !in_array($status, array(401, 403, 408, 429), true);
}

/**
 * Hands a built request to its provider and reads the answer.
 *
 * @param string $key
 * @param array  $request pg_mail_<key>_request()
 * @return array pg_mail_transport_result()
 */
function pg_mail_transport_call($key, $request)
{
    $transports = pg_mail_transports();
    $label = $transports[$key]['label'];
    $capabilities = call_user_func('pg_mail_' . $key . '_capabilities');
    $max_bytes = isset($capabilities['max_bytes']) ? (int) $capabilities['max_bytes'] : 0;

    // json_encode() refuses text that is not UTF-8.
    if (!is_string($request['body'])) {
        return pg_mail_transport_result(false, '', $label . ': ' . lang('The message could not be encoded for the service.'), true);
    }

    if (($max_bytes > 0) && (strlen($request['body']) > $max_bytes)) {
        return pg_mail_transport_result(false, '', lang(array(
            'string' => 'The message is {var:1} and the account takes at most {var:2}. Send large files as a link.',
            'vars'   => array(convert_bytes_to_string(strlen($request['body'])), convert_bytes_to_string($max_bytes)))), true);
    }

    $options = array(
        'body'    => $request['body'],
        'headers' => $request['headers'],
        'timeout' => 60,
    );

    if (isset($request['userpwd'])) {
        $options['userpwd'] = $request['userpwd'];
    }

    $response = pg_curl_run('POST', $request['url'], $options);

    if ((int) $response['errno'] !== 0) {
        return pg_mail_transport_result(false, '', $label . ': ' . $response['error'] . pg_curl_tls_hint($response['errno']));
    }

    $answer = call_user_func('pg_mail_' . $key . '_answer', (int) $response['status'], (string) $response['body']);

    if ($answer['ok']) {
        return pg_mail_transport_result(true, $answer['id']);
    }

    $error = ($answer['error'] !== '') ? $answer['error'] : 'HTTP ' . (int) $response['status'];

    return pg_mail_transport_result(false, '', $label . ': ' . mb_substr($error, 0, 300, 'UTF-8'), pg_mail_transport_status_permanent($response['status']));
}

/**
 * Whether a transport takes the finished message whole (capability 'raw').
 *
 * @param string $key
 * @return bool
 */
function pg_mail_transport_raw($key)
{
    if (!pg_mail_transport_driver($key)) {
        return false;
    }

    $capabilities = call_user_func('pg_mail_' . $key . '_capabilities');

    return !empty($capabilities['raw']);
}

/**
 * Sends a built message through a transport.
 *
 * @param string $key
 * @param array  $message pg_mail_transport_message()
 * @return array pg_mail_transport_result()
 */
function pg_mail_transport_deliver($key, $message)
{
    if (!pg_mail_transport_driver($key)) {
        return pg_mail_transport_result(false, '', lang('This kind of connection is not available.'));
    }

    try {
        $result = call_user_func('pg_mail_' . $key . '_send', $message, pg_mail_transport_settings($key));
    } catch (\Exception $e) {
        return pg_mail_transport_result(false, '', $e->getMessage());
    }

    return is_array($result) ? $result + pg_mail_transport_result(false) : pg_mail_transport_result(false);
}

/**
 * A field of a driver as posted, control characters removed.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_mail_transport_post($post, $name)
{
    return (isset($post[$name]) && is_string($post[$name])) ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $post[$name])) : '';
}

/**
 * What a save of the card does (pure).
 *
 * @param array $post     the posted form
 * @param array $fields   key => pg_mail_transport_fields()
 * @param array $before   key => pg_mail_transport_settings()
 * @param bool  $can_seal whether the site can seal a secret
 * @return array chosen (key or 'smtp'), write (null, or values, secret
 *               (null keeps the stored one)), disable (keys switched off),
 *               marks (posted field name => sentence), error
 */
function pg_mail_transport_settings_decide($post, $fields, $before, $can_seal)
{
    $chosen = pg_mail_transport_post($post, 'mail_transport');
    $chosen = isset($fields[$chosen]) ? $chosen : 'smtp';

    $decision = array('chosen' => $chosen, 'write' => null, 'disable' => array(), 'marks' => array(), 'error' => '');

    if ($chosen !== 'smtp') {
        $values = array();
        $secret = '';

        foreach ($fields[$chosen] as $name => $field) {
            $posted = pg_mail_transport_post($post, 'mail_' . $chosen . '_' . $name);

            if ($field['type'] === 'secret') {
                $secret = $posted;
                $missing = ($posted === '') && ($before[$chosen]['secret'] === '');
            } else {
                if (($field['type'] === 'select') && !isset($field['options'][$posted])) {
                    $posted = isset($field['default']) ? (string) $field['default'] : '';
                }

                $values[$name] = $posted;
                $missing = !empty($field['required']) && ($posted === '');
            }

            if ($missing) {
                $transports = pg_mail_transports();
                $decision['marks']['mail_' . $chosen . '_' . $name] = lang(array(
                    'string' => '{var:1} must be filled in to send e-mail through {var:2}.',
                    'vars'   => array(h($field['label']), h(isset($transports[$chosen]) ? $transports[$chosen]['label'] : $chosen))));
            }
        }

        if ($decision['marks']) {
            return $decision;
        }

        if (($values !== $before[$chosen]['values']) || ($secret !== '') || !$before[$chosen]['enabled']) {
            if (!$can_seal) {
                $decision['error'] = lang('The service cannot be saved: the site has no encryption key to keep its key sealed.');

                return $decision;
            }

            $decision['write'] = array('values' => $values, 'secret' => ($secret !== '') ? $secret : null);
        }
    }

    foreach ($before as $key => $settings) {
        if (($key !== $chosen) && $settings['enabled']) {
            $decision['disable'][] = $key;
        }
    }

    return $decision;
}

/**
 * Saves the card's choice. Called by every save of SMTP Settings.
 *
 * @param array    $post
 * @param int      $user_id
 * @param liveform $liveform
 * @return bool false when nothing was saved because of a mark or an error
 */
function pg_mail_transport_settings_save($post, $user_id, $liveform)
{
    if (!pg_connect_schema_ready()) {
        return true;
    }

    $fields = array();
    $before = array();

    foreach (array_keys(pg_mail_transports()) as $key) {
        $fields[$key] = pg_mail_transport_fields($key);
        $before[$key] = pg_mail_transport_settings($key);
    }

    $decision = pg_mail_transport_settings_decide($post, $fields, $before, pg_connect_can_seal());

    foreach ($decision['marks'] as $name => $sentence) {
        $liveform->mark_error($name, $sentence);
    }

    if ($decision['error'] !== '') {
        $liveform->add_error(h($decision['error']));
    }

    if ($decision['marks'] || ($decision['error'] !== '')) {
        return false;
    }

    foreach ($decision['disable'] as $key) {
        db("UPDATE connect_apps SET enabled = '0', updated_user_id = '" . (int) $user_id . "', updated_timestamp = UNIX_TIMESTAMP()
            WHERE provider = '" . e(pg_mail_transport_app_key($key)) . "'");
    }

    if ($decision['write'] !== null) {
        pg_connect_app_save(pg_mail_transport_app_key($decision['chosen']), '', $decision['write']['secret'], $decision['write']['values'], true, $user_id);
    }

    pg_mail_webhook_settings_save($post, $decision['chosen']);

    if (($decision['write'] !== null) || $decision['disable']) {
        $was = 'smtp';

        foreach ($before as $key => $settings) {
            $was = $settings['enabled'] ? $key : $was;
        }

        // The secret is left out of the trail; the options name no secret.
        pg_audit('update', 'settings', 0,
            array('mail_transport' => $was),
            array('mail_transport' => $decision['chosen']) + (($decision['write'] !== null) ? $decision['write']['values'] : array()),
            'smtp');
    }

    pg_mail_transport(true);

    return true;
}

/**
 * The card's fields on SMTP Settings.
 *
 * @return string
 */
function pg_mail_transport_settings_fields()
{
    $transports = pg_mail_transports();
    $shown = pg_mail_transport();
    $options = '<option value="smtp">' . h(lang('SMTP: the server or connected account below')) . '</option>';
    $panes = '';
    $warning = '';

    foreach ($transports as $key => $transport) {
        $settings = pg_mail_transport_settings($key);

        // A transport switched on that cannot send stays shown, with why.
        if ($settings['enabled'] && ($shown === 'smtp')) {
            $shown = $key;
            $warning = ($settings['secret_stored'] && ($settings['secret'] === ''))
                ? lang('The stored key cannot be read with this site\'s encryption key, so e-mail goes through SMTP. Enter it again.')
                : lang('The account is not complete, so e-mail goes through SMTP.');
        }

        $options .= '<option value="' . h($key) . '">' . h($transport['label']) . '</option>';
        $pane = '';

        foreach (pg_mail_transport_fields($key) as $name => $field) {
            $id = 'mail_' . $key . '_' . $name;
            $help = isset($field['help']) ? '<div class="form-text">' . h($field['help']) . '</div>' : '';

            if ($field['type'] === 'select') {
                $choices = '';

                foreach ($field['options'] as $value => $label) {
                    $choices .= '<option value="' . h($value) . '"' . (((string) $value === $settings['values'][$name]) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                $input = '<select name="' . h($id) . '" id="' . h($id) . '" class="form-control">' . $choices . '</select>';
            } elseif ($field['type'] === 'secret') {
                $placeholder = ($settings['secret'] !== '') ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"' : '';
                $input = '<input type="password" name="' . h($id) . '" id="' . h($id) . '" class="form-control" value="" autocomplete="new-password"' . $placeholder . '>';
            } else {
                $input = '<input type="text" name="' . h($id) . '" id="' . h($id) . '" class="form-control" value="' . h($settings['values'][$name]) . '" spellcheck="false" autocapitalize="off">';
            }

            $pane .= '
                <div class="col-12 md:col-6 my-3">
                    <label for="' . h($id) . '" class="form-label">' . h($field['label']) . '</label>
                    ' . $input . $help . '
                </div>';
        }

        $pane .= pg_mail_webhook_settings_fields($key);

        $panes .= '
            <div class="row" data-mail-transport="' . h($key) . '"' . (($key === $shown) ? '' : ' hidden') . '>' . $pane . '
                <div class="col-12 form-text"><a href="' . h($transport['portal']) . '" target="_blank" rel="noopener noreferrer">' . h(lang(array('string' => 'Your {var:1} account', 'vars' => $transport['label']))) . '</a></div>
            </div>';
    }

    $options = str_replace('<option value="' . h($shown) . '">', '<option value="' . h($shown) . '" selected>', $options);

    return '
        <div class="my-3">
            <label for="mail_transport" class="form-label">' . h(lang('Send through')) . '</label>
            <select name="mail_transport" id="mail_transport" class="form-control">' . $options . '</select>
            <div class="form-text">' . h(lang('A sending service takes the e-mail of the site over its API instead of the SMTP server. A connected account chosen below still sends its kind of mail. The domain of the From address must be verified with the service.')) . '</div>
        </div>'
        . (($warning !== '') ? '<div class="alert theme-warning">' . h($warning) . '</div>' : '')
        . $panes;
}

/**
 * Shows the chosen transport's fields only.
 *
 * @return string
 */
function pg_mail_transport_settings_script()
{
    return '
    <script>
        (function () {
            var select = document.getElementById("mail_transport");

            if (!select) {
                return;
            }

            function sync() {
                Array.prototype.forEach.call(document.querySelectorAll("[data-mail-transport]"), function (pane) {
                    pane.hidden = pane.getAttribute("data-mail-transport") !== select.value;
                });
            }

            select.addEventListener("change", sync);
            sync();
        })();
    </script>';
}


/**
 * The SMTP password a save of SMTP Settings stores when its box was left empty.
 *
 * The box is never drawn back into the form, so an empty one keeps the saved
 * password -- but only for the server it was typed for. SMTP AUTH sends the
 * password itself, so keeping it for a new host, or a new port (465 is the
 * only one with TLS before the greeting), would hand it to a server the person
 * who typed it never chose; "Save and send a test" sends it at once. The
 * username may change.
 *
 * @param array $posted host, port, password as posted
 * @param array $stored host, port, password as saved in config.php
 * @return string the password to store; '' when it has to be typed again
 */
function pg_smtp_password_kept(array $posted, array $stored)
{
    $password = isset($posted['password']) ? (string) $posted['password'] : '';

    if ($password !== '') {
        return $password;
    }

    $host = function ($values) {
        return strtolower(trim(isset($values['host']) ? (string) $values['host'] : ''));
    };

    // An empty port box sends through 587 (pg_mail_send_built()).
    $port = function ($values) {
        $value = trim(isset($values['port']) ? (string) $values['port'] : '');

        return ($value === '') ? 587 : (int) $value;
    };

    $same_server = ($host($posted) !== '') && ($host($posted) === $host($stored)) && ($port($posted) === $port($stored));

    return $same_server ? (isset($stored['password']) ? (string) $stored['password'] : '') : '';
}
