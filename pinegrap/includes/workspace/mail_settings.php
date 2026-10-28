<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the mailboxes card of the settings screen
 * (workspace_settings.php): a mailbox's name, address, channel and the
 * server whose sender checks it believes; the IMAP server it is read from
 * (the password sealed with pg_connect_seal(), never drawn back), "Test
 * connection", and an .eml file brought in by hand. The ingest is mail.php,
 * the IMAP client includes/fn/imap_client.php.
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
 * Are the mailbox tables there (the 2026.11.0 upgrade has run)?
 *
 * @return bool
 */
function ws_mail_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_mail_boxes') && pg_schema_has('ws_mail_conversations') && pg_schema_has('ws_mail_items');
    }

    return $ready;
}

/**
 * Mail services a box can be read from: the IMAP server, and the server
 * whose Authentication-Results verdict is believed when it always writes
 * under one name (left empty where it writes under each host's own name;
 * "Test connection" reads it from the newest mail then).
 *
 * @return array[] key => label, host, port, auth_server, hint
 */
function ws_mail_providers()
{
    return array(
        'gmail'  => array('label' => lang('Gmail or Google Workspace'), 'host' => 'imap.gmail.com', 'port' => 993, 'auth_server' => 'mx.google.com',
            'hint' => lang('Turn on 2-Step Verification for the account and use an app password; Google refuses the account\'s own password.')),
        'yandex' => array('label' => 'Yandex', 'host' => 'imap.yandex.com', 'port' => 993, 'auth_server' => '',
            'hint' => lang('Allow access by mail programs in the Yandex mail settings and use an app password.')),
        'icloud' => array('label' => 'iCloud', 'host' => 'imap.mail.me.com', 'port' => 993, 'auth_server' => '',
            'hint' => lang('Use an app-specific password made at account.apple.com; the user is the address before @icloud.com.')),
        'zoho'   => array('label' => 'Zoho Mail', 'host' => 'imap.zoho.com', 'port' => 993, 'auth_server' => 'mx.zohomail.com',
            'hint' => lang('Turn on IMAP access in the Zoho Mail settings; with two-factor sign-in use an app password.')),
        'yahoo'  => array('label' => 'Yahoo', 'host' => 'imap.mail.yahoo.com', 'port' => 993, 'auth_server' => '',
            'hint' => lang('Use an app password made in the Yahoo account security settings.')),
        'other'  => array('label' => lang('Another server'), 'host' => '', 'port' => 993, 'auth_server' => '',
            'hint' => lang('The server must accept IMAP over TLS (usually port 993). Microsoft 365 and Outlook.com accept no password for IMAP; they are read through a connected account later.')),
    );
}

/**
 * A mailbox form as posted, checked and cleaned. Pure: whether the channel
 * may be chosen is asked by the caller. A chosen provider fills an empty
 * server, and an empty checking server, with its own.
 *
 * @param array $input name, address, channel_id, auth_server, enabled,
 *                     provider, host, port, user, password, folder,
 *                     after_fetch, move_folder
 * @return array ok, error, values (the same keys; password as typed, '' to keep)
 */
function ws_mail_box_input($input)
{
    if (!function_exists('pg_imap_host_valid')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');
    }

    $line = function ($key, $default = '') use ($input) {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($input[$key] ?? '')));
        return ($value === '') ? $default : $value;
    };
    $providers = ws_mail_providers();
    $provider = isset($providers[(string) ($input['provider'] ?? '')]) ? (string) $input['provider'] : '';
    $preset = ($provider !== '') ? $providers[$provider] : array('host' => '', 'port' => 993, 'auth_server' => '');
    $host = strtolower($line('host', $preset['host']));
    $auth_server = strtolower($line('auth_server', $preset['auth_server']));
    $port = (int) $line('port', (string) $preset['port']);
    $after_fetch = (($input['after_fetch'] ?? '') === 'move') ? 'move' : 'seen';
    $values = array(
        'name'        => $line('name'),
        'address'     => strtolower($line('address')),
        'channel_id'  => (int) ($input['channel_id'] ?? 0),
        'auth_server' => $auth_server,
        'enabled'     => !empty($input['enabled']) ? 1 : 0,
        'provider'    => $provider,
        'host'        => $host,
        'port'        => $port,
        'user'        => $line('user'),
        'password'    => (string) ($input['password'] ?? ''),
        'folder'      => $line('folder', 'INBOX'),
        'after_fetch' => $after_fetch,
        'move_folder' => ($after_fetch === 'move') ? $line('move_folder') : '',
    );
    $fail = function ($error) use ($values) {
        return array('ok' => false, 'error' => $error, 'values' => $values);
    };

    if (($values['name'] === '') || (mb_strlen($values['name']) > 100)) {
        return $fail(lang('Give the mailbox a name of at most 100 characters.'));
    }

    if ((strlen($values['address']) > 255) || !filter_var($values['address'], FILTER_VALIDATE_EMAIL)) {
        return $fail(lang('Enter the mailbox\'s e-mail address.'));
    }

    if ($values['channel_id'] <= 0) {
        return $fail(lang('Choose the channel its mail goes to.'));
    }

    if (($auth_server !== '') && ((strlen($auth_server) > 255) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $auth_server))) {
        return $fail(lang('The checking server is a host name such as mx.google.com.'));
    }

    // No server: the box takes only .eml files brought in by hand.
    if ($host === '') {
        return array('ok' => true, 'error' => '', 'values' => $values);
    }

    if (!pg_imap_host_valid($host) || ($port < 1) || ($port > 65535)) {
        return $fail(lang('The IMAP server is a host name such as imap.gmail.com, with a port from 1 to 65535.'));
    }

    if (($values['user'] === '') || (strlen($values['user']) > 255)) {
        return $fail(lang('Enter the user name the mailbox signs in with.'));
    }

    if ((strlen($values['password']) > 1024) || preg_match('/[\x00\r\n]/', $values['password'])) {
        return $fail(lang('The password cannot be kept as typed.'));
    }

    if ((strlen($values['folder']) > 255) || (($after_fetch === 'move') && (($values['move_folder'] === '') || (strlen($values['move_folder']) > 255)))) {
        return $fail(lang('Name the folder to read and, to move mail after reading, the folder it goes to.'));
    }

    if (($after_fetch === 'move') && (strcasecmp($values['move_folder'], $values['folder']) === 0)) {
        return $fail(lang('Mail cannot be moved into the folder it is read from.'));
    }

    return array('ok' => true, 'error' => '', 'values' => $values);
}

/**
 * A box's settings JSON.
 *
 * @param array|null $box
 * @return array
 */
function ws_mail_box_settings($box)
{
    $settings = $box ? json_decode((string) $box['settings'], true) : null;

    return is_array($settings) ? $settings : array();
}

/**
 * The channels a mailbox may write into for this person: the open ones they
 * read, never a guest room or a discussion.
 *
 * @param array $viewer
 * @return array[] id => channel row
 */
function ws_mail_box_channels($viewer)
{
    $out = array();

    foreach (ws_channels_for($viewer) as $channel) {
        if (in_array((string) $channel['kind'], array('public', 'private'), true)) {
            $out[(int) $channel['id']] = $channel;
        }
    }

    return $out;
}

/**
 * What the card says after an .eml was brought in.
 *
 * @param array $result ws_mail_ingest()
 * @return array notice (bool), text
 */
function ws_mail_ingest_message($result)
{
    switch ((string) $result['status']) {
        case 'stored':
            return array('notice' => true, 'text' => lang('The e-mail was brought into its channel.'));

        case 'duplicate':
            return array('notice' => true, 'text' => lang('This e-mail is already in the mailbox.'));

        case 'loop':
            return array('notice' => false, 'text' => lang('This e-mail was not brought in: it was sent by this site or automatically, or to a list.'));

        case 'held':
            return array('notice' => false, 'text' => lang(array('string' => 'This sender has sent more than {var:1} e-mails in the last hour; try again later.', 'vars' => WS_MAIL_SENDER_HOURLY)));
    }

    $reasons = array(
        'too_large'  => lang('The e-mail is larger than 25 MB.'),
        'no_sender'  => lang('The e-mail has no sender address.'),
        'no_channel' => lang('The mailbox\'s channel is missing or in the archive.'),
    );

    return array('notice' => false, 'text' => $reasons[(string) $result['reason']] ?? lang('The e-mail could not be saved.'));
}

/**
 * The authserv-id of the topmost Authentication-Results header of a header
 * block: the name the receiving server writes its verdicts under.
 *
 * @param string $headers raw header lines
 * @return string lower case host name, '' when none
 */
function ws_mail_authserv_id($headers)
{
    $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', (string) $headers);

    if (!preg_match('/^authentication-results:[ \t]*([^;\r\n]*)/im', $unfolded, $match)) {
        return '';
    }

    // "mx.google.com" or "mx.example.com 1" (a version), comments dropped.
    $id = strtolower(trim(preg_replace('/\([^)]*\)/', ' ', $match[1])));
    $id = (string) strtok($id, " \t");

    return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $id) ? $id : '';
}

/**
 * Connects to a box's server, signs in and looks at its folders without
 * changing anything: the folder read (EXAMINE), the folder mail is moved
 * to, and the checking server named by the newest mail.
 *
 * @param array $box
 * @return array ok, error (a pg_imap error, or no_server | no_password |
 *               move_folder), detail, exists, auth_server
 */
function ws_mail_box_test($box)
{
    if (!function_exists('pg_imap_connect')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');
    }

    $settings = ws_mail_box_settings($box);
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'exists' => 0, 'auth_server' => '');

    if ((string) ($settings['host'] ?? '') === '') {
        return array_merge($out, array('error' => 'no_server'));
    }

    $secret = pg_connect_unseal((string) ($settings['secret'] ?? ''));

    if (!isset($secret['password'])) {
        return array_merge($out, array('error' => 'no_password'));
    }

    $connected = pg_imap_connect((string) $settings['host'], (int) ($settings['port'] ?? 993), array('budget' => 20));

    if (!$connected['ok']) {
        return array_merge($out, array('error' => $connected['error'], 'detail' => $connected['detail']));
    }

    $session = $connected['session'];
    $failed = function () use (&$session, $out) {
        $said = array_merge($out, array('error' => $session['error'], 'detail' => $session['detail']));
        pg_imap_logout($session);
        return $said;
    };

    if (!pg_imap_login($session, (string) ($settings['user'] ?? ''), (string) $secret['password'])) {
        return $failed();
    }

    $folder = pg_imap_select($session, (string) $box['folder'], true);

    if (!$folder['ok']) {
        return $failed();
    }

    $out['exists'] = $folder['exists'];

    if ($folder['exists'] > 0) {
        $reply = pg_imap_command($session, array('FETCH', (string) $folder['exists'], '(BODY.PEEK[HEADER.FIELDS (AUTHENTICATION-RESULTS)])'));

        foreach ($reply['untagged'] as $line) {
            if (($line['type'] === 'FETCH') && is_array($line['data'])) {
                $out['auth_server'] = ws_mail_authserv_id((string) pg_imap_fetch_section($line['data'], 'BODY[HEADER'));
            }
        }
    }

    if (((string) $box['after_fetch'] === 'move') && !pg_imap_select($session, (string) ($settings['move_folder'] ?? ''), true)['ok']) {
        $said = array_merge($out, array('error' => 'move_folder', 'detail' => $session['detail']));
        pg_imap_logout($session);
        return $said;
    }

    pg_imap_logout($session);

    return array_merge($out, array('ok' => true));
}

/**
 * What a failed check says, from the error code and the server's words.
 *
 * @param string $error
 * @param string $detail
 * @param string $host
 * @return string
 */
function ws_mail_error_text($error, $detail, $host)
{
    $texts = array(
        'no_server'     => lang('Enter the mailbox\'s IMAP server first.'),
        'no_password'   => lang('Enter the mailbox\'s password again; the kept one cannot be read.'),
        'connect'       => lang('The server could not be reached on its port.'),
        'tls'           => lang('The server\'s certificate could not be verified.'),
        'timeout'       => lang('The server did not answer in time.'),
        'auth'          => lang('The server refused the user name or password.'),
        'auth_disabled' => lang('The server does not allow signing in with a password.'),
        'folder'        => lang('The folder to read was not found.'),
        'move_folder'   => lang('The folder to move mail to was not found.'),
        'too_large'     => lang('An e-mail is larger than 25 MB.'),
        'no_channel'    => lang('The mailbox\'s channel is missing or in the archive.'),
        'not_saved'     => lang('The e-mail could not be saved.'),
    );
    $text = $texts[(string) $error] ?? lang('The server ended the conversation unexpectedly.');

    if (($error === 'auth') && preg_match('/(^|\.)(gmail|googlemail)\.com$/', (string) $host)) {
        $text .= ' ' . lang('Gmail and Google Workspace take an app password here, not the account\'s own password.');
    }

    if ((string) $detail !== '') {
        $text .= ' ' . lang(array('string' => 'The server said: {var:1}', 'vars' => (string) $detail));
    }

    return $text;
}

/**
 * The last check kept on a box: "code|server words".
 *
 * @param string $error
 * @param string $detail
 * @return string at most 255 bytes
 */
function ws_mail_last_error($error, $detail)
{
    $kept = (string) $error . '|' . (string) $detail;

    return (strlen($kept) > 255) ? preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', substr($kept, 0, 255)) : $kept;
}

/**
 * Saves the card: a mailbox added or changed (mail_box_save), or an .eml
 * brought into one (mail_eml).
 *
 * @param array    $viewer
 * @param string   $action
 * @param liveform $liveform
 */
function ws_mail_settings_post($viewer, $action, $liveform)
{
    if (!ws_mail_ready()) {
        $liveform->add_error(lang('The database has not been upgraded yet.'));
        return;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $box_id = (int) ($_POST['box_id'] ?? 0);
    $box = ($box_id > 0) ? ws_mail_box($box_id) : null;

    if (($box_id > 0) && !$box) {
        $liveform->add_error(lang('That mailbox could not be found.'));
        return;
    }

    if ($action === 'mail_eml') {
        $file = $_FILES['eml'] ?? null;

        if (!$box || !is_array($file) || is_array($file['error']) || ((int) $file['error'] !== UPLOAD_ERR_OK) || !is_uploaded_file((string) $file['tmp_name'])) {
            $liveform->add_error(lang('Choose an .eml file to bring in.'));
            return;
        }

        $result = ws_mail_ingest((string) file_get_contents((string) $file['tmp_name']), $box);
        $said = ws_mail_ingest_message($result);

        if ($said['notice']) {
            $liveform->add_notice($said['text']);
        } else {
            $liveform->add_error($said['text']);
        }

        if ($result['status'] === 'stored') {
            log_activity(lang(array('string' => 'an e-mail was brought into the mailbox {var:1} by hand', 'vars' => (string) $box['name'])), $username);
        }

        return;
    }

    if ($action === 'mail_test') {
        if (!$box) {
            $liveform->add_error(lang('That mailbox could not be found.'));
            return;
        }

        $result = ws_mail_box_test($box);
        $settings = ws_mail_box_settings($box);
        $now = time();
        $set = "checked_at = '" . $now . "', last_error = '" . e($result['ok'] ? '' : ws_mail_last_error($result['error'], $result['detail'])) . "'"
            . ($result['ok'] ? ", ok_at = '" . $now . "'" : '');

        // The receiving server named by the newest mail fills an empty
        // checking server; one an editor typed is never replaced.
        $filled = $result['ok'] && ($result['auth_server'] !== '') && ((string) ($settings['auth_server'] ?? '') === '');

        if ($filled) {
            $settings['auth_server'] = $result['auth_server'];
            $set .= ", settings = '" . e(json_encode($settings)) . "'";
        }

        db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . $box_id . "'");

        if (!$result['ok']) {
            $liveform->add_error(ws_mail_error_text($result['error'], $result['detail'], (string) ($settings['host'] ?? '')));
            return;
        }

        $liveform->add_notice(lang(array('string' => 'Connected: {var:1} e-mails in {var:2}.', 'vars' => array(pg_format_number($result['exists'], 0), (string) $box['folder']))));

        if ($filled) {
            $liveform->add_notice(lang(array('string' => 'The checking server was set to {var:1}, the name the newest e-mail was checked under.', 'vars' => $result['auth_server'])));
        }

        return;
    }

    $checked = ws_mail_box_input($_POST);

    if (!$checked['ok']) {
        $liveform->add_error($checked['error']);
        return;
    }

    $values = $checked['values'];
    $channels = ws_mail_box_channels($viewer);

    // A box keeps a channel its editor cannot read, as long as nobody picks
    // another one.
    if (!isset($channels[$values['channel_id']]) && !($box && ((int) $box['channel_id'] === $values['channel_id']))) {
        $liveform->add_error(lang('Choose the channel its mail goes to.'));
        return;
    }

    $settings = ws_mail_box_settings($box);

    // A password typed now replaces the kept one; an empty box keeps it.
    if ($values['password'] !== '') {
        if (!pg_connect_can_seal()) {
            $liveform->add_error(lang('This site has no encryption key, so a password cannot be kept.'));
            return;
        }

        $settings['secret'] = pg_connect_seal(array('password' => $values['password']));
    }

    if (($values['host'] !== '') && (empty($settings['secret']) || (($settings['host'] ?? '') !== $values['host']) || (($settings['user'] ?? '') !== $values['user'])) && ($values['password'] === '')) {
        $liveform->add_error(lang('Enter the password for this server and user.'));
        return;
    }

    if ($values['host'] === '') {
        unset($settings['secret']);
    }

    foreach (array('auth_server', 'provider', 'host', 'port', 'user', 'move_folder') as $key) {
        $settings[$key] = $values[$key];
    }

    $now = time();

    $set = "name = '" . e($values['name']) . "', address = '" . e($values['address']) . "', channel_id = '" . (int) $values['channel_id'] . "',
        settings = '" . e(json_encode($settings)) . "', folder = '" . e($values['folder']) . "', after_fetch = '" . e($values['after_fetch']) . "',
        enabled = '" . (int) $values['enabled'] . "', updated_at = '" . $now . "'";

    if ($box) {
        db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . $box_id . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was changed', 'vars' => $values['name'])), $username);
    } else {
        db("INSERT INTO ws_mail_boxes SET " . $set . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was added', 'vars' => $values['name'])), $username);
    }

    $liveform->add_notice(lang('The mailbox was saved.'));
}

/**
 * One mailbox's form, empty for a new one.
 *
 * @param string     $self_url
 * @param array|null $box
 * @param array[]    $channels ws_mail_box_channels()
 * @return string
 */
function ws_mail_box_form($self_url, $box, $channels)
{
    $id = $box ? (int) $box['id'] : 0;
    $prefix = 'ws_mail_' . $id . '_';
    $settings = ws_mail_box_settings($box);
    $channel_id = $box ? (int) $box['channel_id'] : 0;
    $options = '<option value="">' . h(lang('Choose')) . '</option>';

    if ($box && ($channel_id > 0) && !isset($channels[$channel_id])) {
        $options .= '<option value="' . $channel_id . '" selected>' . h(lang('A channel you do not see')) . '</option>';
    }

    foreach ($channels as $channel) {
        $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === $channel_id) ? ' selected' : '') . '>'
            . h('#' . $channel['name']) . '</option>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-mail" class="py-5">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mail_box_save">
            <input type="hidden" name="box_id" value="' . $id . '">
            <div class="row g-5">
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="name" id="' . $prefix . 'name" maxlength="100" value="' . h($box ? (string) $box['name'] : '') . '" placeholder="' . h(lang('Support')) . '">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'address">' . h(lang('E-mail address')) . '</label>
                    <input class="form-control form-control-sm" type="email" name="address" id="' . $prefix . 'address" maxlength="255" value="' . h($box ? (string) $box['address'] : '') . '" placeholder="support@example.com">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'channel">' . h(lang('Channel')) . '</label>
                    <select class="form-control form-control-sm" name="channel_id" id="' . $prefix . 'channel">' . $options . '</select>
                    <div class="form-text">' . h(lang('Each new conversation becomes a card here; its answers go under the card.')) . '</div>
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'auth">' . h(lang('Checking server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" name="auth_server" id="' . $prefix . 'auth" maxlength="255" value="' . h((string) ($settings['auth_server'] ?? '')) . '" placeholder="mx.google.com" autocomplete="off">
                    <div class="form-text">' . h(lang('The server that receives this mailbox\'s mail and writes Authentication-Results. Only its verdict is believed; left empty, every sender stays unverified and no mail is tied to a customer.')) . '</div>
                </div>
                ' . ws_mail_box_server_fields($prefix, $box, $settings) . '
                <div class="md:col-12">
                    <div class="form-field">
                        <div class="switch"><input type="checkbox" name="enabled" value="1" id="' . $prefix . 'enabled"' . ((!$box || (int) $box['enabled'] === 1) ? ' checked' : '') . '></div>
                        <label for="' . $prefix . 'enabled">' . h(lang('On')) . '</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mt-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>
        </form>';
}

/**
 * The reading half of a mailbox's form: provider, IMAP server, user,
 * password (drawn empty, an empty box keeps the kept one), folder and what
 * happens to mail once read.
 *
 * @param string     $prefix
 * @param array|null $box
 * @param array      $settings ws_mail_box_settings()
 * @return string
 */
function ws_mail_box_server_fields($prefix, $box, $settings)
{
    $provider = (string) ($settings['provider'] ?? '');
    $options = '<option value="">' . h(lang('None: only .eml files')) . '</option>';
    $hints = '';

    foreach (ws_mail_providers() as $key => $entry) {
        $options .= '<option value="' . h($key) . '"' . (($key === $provider) ? ' selected' : '') . '>' . h($entry['label']) . '</option>';
        $hints .= '<li><strong>' . h($entry['label']) . ':</strong> ' . h($entry['hint']) . '</li>';
    }

    $kept = !empty($settings['secret']);
    $move = $box && ((string) $box['after_fetch'] === 'move');

    return '
                <div class="md:col-12"><h3 class="h6 mt-3 mb-0">' . h(lang('Reading the mailbox')) . '</h3>
                    <div class="form-text">' . h(lang('Leave the server empty to bring mail in only as .eml files. A chosen service fills an empty server and checking server.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'provider">' . h(lang('Mail service')) . '</label>
                    <select class="form-control form-control-sm" name="provider" id="' . $prefix . 'provider">' . $options . '</select>
                </div>
                <div class="md:col-5">
                    <label class="form-label" for="' . $prefix . 'host">' . h(lang('IMAP server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" name="host" id="' . $prefix . 'host" maxlength="253" value="' . h((string) ($settings['host'] ?? '')) . '" placeholder="imap.example.com" autocomplete="off">
                </div>
                <div class="md:col-3">
                    <label class="form-label" for="' . $prefix . 'port">' . h(lang('Port')) . '</label>
                    <input class="form-control form-control-sm" type="number" name="port" id="' . $prefix . 'port" min="1" max="65535" value="' . (int) ($settings['port'] ?? 993) . '">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'user">' . h(lang('User name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="user" id="' . $prefix . 'user" maxlength="255" value="' . h((string) ($settings['user'] ?? '')) . '" autocomplete="off">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'password">' . h(lang('Password')) . '</label>
                    <input class="form-control form-control-sm" type="password" name="password" id="' . $prefix . 'password" value="" autocomplete="new-password" placeholder="' . h($kept ? lang('Stored. Type a new one to replace it.') : '') . '">
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'folder">' . h(lang('Folder to read')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="folder" id="' . $prefix . 'folder" maxlength="255" value="' . h($box ? (string) $box['folder'] : 'INBOX') . '">
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'after">' . h(lang('After reading')) . '</label>
                    <select class="form-control form-control-sm" name="after_fetch" id="' . $prefix . 'after">
                        <option value="seen"' . ($move ? '' : ' selected') . '>' . h(lang('Mark as read')) . '</option>
                        <option value="move"' . ($move ? ' selected' : '') . '>' . h(lang('Move to a folder')) . '</option>
                    </select>
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'move">' . h(lang('Folder to move to')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="move_folder" id="' . $prefix . 'move" maxlength="255" value="' . h((string) ($settings['move_folder'] ?? '')) . '" placeholder="' . h(lang('Processed')) . '">
                </div>
                <div class="md:col-12">
                    <details class="small fg-secondary"><summary>' . h(lang('What each service needs')) . '</summary><ul class="mb-0 mt-2">' . $hints . '</ul></details>
                </div>';
}

/**
 * The line under a box about its last check.
 *
 * @param array $box
 * @return string HTML
 */
function ws_mail_box_check_line($box)
{
    $settings = ws_mail_box_settings($box);

    if ((string) ($settings['host'] ?? '') === '') {
        return h(lang('Not read from a server'));
    }

    if ((int) $box['checked_at'] === 0) {
        return h(lang('Not checked yet'));
    }

    $when = date('d.m.Y H:i', (int) $box['checked_at']);

    if ((string) $box['last_error'] === '') {
        return '<i class="bi bi-check-circle fg-success me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Checked {var:1}', 'vars' => $when)));
    }

    $parts = explode('|', (string) $box['last_error'], 2);

    return '<i class="bi bi-exclamation-triangle fg-danger me-1" aria-hidden="true"></i>'
        . h(lang(array('string' => 'Checked {var:1}', 'vars' => $when)) . ': ' . ws_mail_error_text($parts[0], $parts[1] ?? '', (string) $settings['host']));
}

/**
 * How the mailboxes read from a server stand, for the System status tile.
 *
 * Only a box that is on and has a server counts. A box whose last read
 * failed is failing; one left a mail over 25 MB in the box, or not read well
 * for over an hour (the general job is not running), wants a look; a box
 * added in the last hour and not checked yet is fine. 'none' when no box
 * counts, so the tile is not drawn.
 *
 * @param array[] $boxes ws_mail_boxes rows
 * @param int     $now
 * @return array state (none|ok|warn|fail), failing, stale, on, rows
 *               (id, name, address, state, checked_at, error, detail)
 */
function ws_mail_fetch_health(array $boxes, $now)
{
    $out = array('state' => 'none', 'failing' => 0, 'stale' => 0, 'on' => 0, 'rows' => array());
    $rank = array('none' => 0, 'ok' => 1, 'warn' => 2, 'fail' => 3);

    foreach ($boxes as $box) {
        $settings = ws_mail_box_settings($box);

        if (empty($box['enabled']) || ((string) ($box['transport'] ?? 'imap') !== 'imap') || ((string) ($settings['host'] ?? '') === '')) {
            continue;
        }

        $parts = explode('|', (string) $box['last_error'], 2);
        $error = $parts[0];
        $since = max((int) $box['ok_at'], (int) $box['created_at']);

        if (($error !== '') && ($error !== 'too_large')) {
            $state = 'fail';
            $out['failing']++;
        } elseif (($error === 'too_large') || (((int) $now - $since) > 3600)) {
            $state = 'warn';
            $out['stale'] += ($error === 'too_large') ? 0 : 1;
        } else {
            $state = 'ok';
        }

        $out['on']++;
        $out['rows'][] = array(
            'id'         => (int) $box['id'],
            'name'       => (string) $box['name'],
            'address'    => (string) $box['address'],
            'state'      => $state,
            'checked_at' => (int) $box['checked_at'],
            'error'      => $error,
            'detail'     => $parts[1] ?? '',
            'host'       => (string) $settings['host'],
        );

        if ($rank[$state] > $rank[$out['state']]) {
            $out['state'] = $state;
        }
    }

    return $out;
}

/**
 * The mailboxes card: each box with its channel and last mail, its form,
 * and the .eml upload.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_mail_settings_card($self_url, $viewer)
{
    if (!ws_mail_ready()) {
        return '';
    }

    $channels = ws_mail_box_channels($viewer);
    $rows = '';

    foreach ((array) db_items("SELECT b.*, (SELECT MAX(i.created_at) FROM ws_mail_items i WHERE i.box_id = b.id) AS last_mail_at
        FROM ws_mail_boxes b ORDER BY b.name") as $box) {
        $id = (int) $box['id'];
        $channel = $channels[(int) $box['channel_id']] ?? null;

        $rows .= '
            <div class="ws-dept-row' . ((int) $box['enabled'] === 1 ? '' : ' opacity-50') . '">
                <div class="ws-grow">
                    <div class="fw-semibold">' . h((string) $box['name']) . ' <span class="small fg-secondary">' . h((string) $box['address']) . '</span></div>
                    <div class="small fg-secondary">'
                        . ($channel ? '<a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h((string) $channel['name']) . '</a>' : h(lang('A channel you do not see')))
                        . ' · ' . h(((int) $box['last_mail_at'] > 0) ? lang(array('string' => 'Last e-mail {var:1}', 'vars' => date('d.m.Y H:i', (int) $box['last_mail_at']))) : lang('No e-mail yet')) . '
                    </div>
                    <div class="small fg-secondary">' . ws_mail_box_check_line($box) . '</div>
                </div>
                ' . (((string) (ws_mail_box_settings($box)['host'] ?? '') !== '') ? '
                <form method="post" action="' . h($self_url) . '#ws-mail" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mail_test">
                    <input type="hidden" name="box_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('Test connection')) . '</button>
                </form>' : '') . '
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="collapse" data-bs-target="#ws-mail-' . $id . '" aria-expanded="false"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            </div>
            <div class="collapse" id="ws-mail-' . $id . '">
                ' . ws_mail_box_form($self_url, $box, $channels) . '
                <form method="post" action="' . h($self_url) . '#ws-mail" enctype="multipart/form-data" class="pb-5 d-flex flex-wrap align-items-end gap-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mail_eml">
                    <input type="hidden" name="box_id" value="' . $id . '">
                    <div>
                        <label class="form-label" for="ws_mail_eml_' . $id . '">' . h(lang('Bring in an .eml file')) . '</label>
                        <input class="form-control form-control-sm" type="file" name="eml" id="ws_mail_eml_' . $id . '" accept=".eml,message/rfc822">
                    </div>
                    <button type="submit" class="btn btn-sm btn-text theme-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>' . h(lang('Bring in')) . '</button>
                </form>
            </div>';
    }

    if ($rows === '') {
        $rows = '<div class="ws-empty py-5"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('No mailboxes yet. A mailbox brings the e-mail sent to an address into a channel, where the team talks it over.')) . '</div>';
    }

    return '
        <div class="card mt-7" id="ws-mail">
            <div class="card-header d-flex align-items-center gap-3">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . h(lang('Mailboxes')) . '</h2>
                <button type="button" class="btn btn-sm btn-text theme-primary" data-bs-toggle="collapse" data-bs-target="#ws-mail-new" aria-expanded="false"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                <div class="collapse" id="ws-mail-new">' . ws_mail_box_form($self_url, null, $channels) . '</div>
                ' . $rows . '
                <div class="form-text mt-5">' . h(lang('A mailbox with a server is read every minute while the general scheduled job runs; an .eml file saved from a mail program can also be brought in by hand.')) . '</div>
            </div>
        </div>';
}
