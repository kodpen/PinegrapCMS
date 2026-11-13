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
 * Mail services a box can be read from: the IMAP server, the server
 * whose Authentication-Results verdict is believed when it always writes
 * under one name (left empty where it writes under each host's own name;
 * "Test connection" reads it from the newest mail then), and the SMTP
 * server answers leave through.
 *
 * @return array[] key => label, host, port, auth_server, smtp_host, smtp_port, hint
 */
function ws_mail_providers()
{
    return array(
        'gmail'  => array('label' => lang('Gmail or Google Workspace'), 'host' => 'imap.gmail.com', 'port' => 993, 'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465, 'auth_server' => 'mx.google.com',
            'hint' => lang('Turn on 2-Step Verification for the account and use an app password; Google refuses the account\'s own password.')),
        'yandex' => array('label' => 'Yandex', 'host' => 'imap.yandex.com', 'port' => 993, 'smtp_host' => 'smtp.yandex.com', 'smtp_port' => 465, 'auth_server' => '',
            'hint' => lang('Allow access by mail programs in the Yandex mail settings and use an app password.')),
        'icloud' => array('label' => 'iCloud', 'host' => 'imap.mail.me.com', 'port' => 993, 'smtp_host' => 'smtp.mail.me.com', 'smtp_port' => 587, 'auth_server' => '',
            'hint' => lang('Use an app-specific password made at account.apple.com; the user is the address before @icloud.com.')),
        'zoho'   => array('label' => 'Zoho Mail', 'host' => 'imap.zoho.com', 'port' => 993, 'smtp_host' => 'smtp.zoho.com', 'smtp_port' => 465, 'auth_server' => 'mx.zohomail.com',
            'hint' => lang('Turn on IMAP access in the Zoho Mail settings; with two-factor sign-in use an app password.')),
        'yahoo'  => array('label' => 'Yahoo', 'host' => 'imap.mail.yahoo.com', 'port' => 993, 'smtp_host' => 'smtp.mail.yahoo.com', 'smtp_port' => 465, 'auth_server' => '',
            'hint' => lang('Use an app password made in the Yahoo account security settings.')),
        // No password: the box signs in with a connected account's token
        // and answers through it (purpose mail_read).
        'microsoft' => array('label' => lang('Microsoft 365 or Outlook.com'), 'host' => 'outlook.office365.com', 'port' => 993, 'smtp_host' => '', 'smtp_port' => 587, 'auth_server' => '', 'connection' => true,
            'hint' => lang('Connect the Microsoft account for Reading mailboxes, choose it as the connected account and send answers through it; no password is typed. The user is the mailbox\'s address.')),
        'other'  => array('label' => lang('Another server'), 'host' => '', 'port' => 993, 'smtp_host' => '', 'smtp_port' => 587, 'auth_server' => '',
            'hint' => lang('The server must accept IMAP over TLS (usually port 993). Microsoft 365 and Outlook.com accept no password for IMAP: choose Microsoft 365 for them.')),
    );
}

/**
 * The port a box's form shows: the stored one, else the chosen service's
 * (993 for IMAP, 587 for SMTP without one). A box saved without a sending
 * server stores port 0; drawn as 0 under the field's min="1", the browser
 * refused every later save of the drawer, the field hidden or not.
 *
 * @param mixed  $stored   ws_mail_box_settings()' port or smtp_port
 * @param string $provider ws_mail_providers() key, '' for none
 * @param string $key      port | smtp_port
 * @return int
 */
function ws_mail_box_port_shown($stored, $provider, $key)
{
    $stored = (int) $stored;

    if (($stored >= 1) && ($stored <= 65535)) {
        return $stored;
    }

    $providers = ws_mail_providers();
    $key = ($key === 'smtp_port') ? 'smtp_port' : 'port';

    return isset($providers[(string) $provider][$key]) ? (int) $providers[(string) $provider][$key] : (($key === 'smtp_port') ? 587 : 993);
}

/**
 * A mailbox form as posted, checked and cleaned. Pure: whether the channel
 * may be chosen, and who reads a box kept in no channel
 * (ws_mail_room_input()), is asked by the caller. A chosen provider fills an
 * empty server, and an empty checking server, with its own.
 *
 * @param array $input name, address, kept_in (mailbox: the box keeps its
 *                     mail in a room of its own and names no channel),
 *                     channel_id, auth_server, enabled,
 *                     provider, host, port, user, password, folder,
 *                     after_fetch, move_folder, smtp_host, smtp_port,
 *                     reply_via, sent_copy, sent_folder, connection_id,
 *                     reads_bounces, signature (ws_mail_signature_input())
 * @return array ok, error, field (the refused one, '' when ok), values (the
 *               same keys; password as typed, '' to keep)
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
    $preset = ($provider !== '') ? $providers[$provider] : array('host' => '', 'port' => 993, 'auth_server' => '', 'smtp_host' => '', 'smtp_port' => 587);
    $host = strtolower($line('host', $preset['host']));
    $auth_server = strtolower($line('auth_server', $preset['auth_server']));
    $port = (int) $line('port', (string) $preset['port']);
    $after_fetch = (($input['after_fetch'] ?? '') === 'move') ? 'move' : 'seen';
    $connection_id = max(0, (int) ($input['connection_id'] ?? 0));
    $reply_via = in_array(($input['reply_via'] ?? ''), array('site', 'connection'), true) ? (string) $input['reply_via'] : 'box';
    $smtp_host = ($reply_via === 'box') ? strtolower($line('smtp_host', $preset['smtp_host'])) : '';
    $signature = ws_mail_signature_input($input['signature'] ?? '');
    $kept_in = (($input['kept_in'] ?? '') === 'mailbox') ? 'mailbox' : 'channel';
    $values = array(
        'name'        => $line('name'),
        'address'     => strtolower($line('address')),
        'kept_in'     => $kept_in,
        'channel_id'  => ($kept_in === 'mailbox') ? 0 : (int) ($input['channel_id'] ?? 0),
        'auth_server' => $auth_server,
        'enabled'     => !empty($input['enabled']) ? 1 : 0,
        'provider'    => $provider,
        'host'        => $host,
        'port'        => $port,
        // A connected account's mailbox signs in as its own address.
        'user'        => $line('user', ($connection_id > 0) ? strtolower($line('address')) : ''),
        'password'    => (string) ($input['password'] ?? ''),
        'folder'      => $line('folder', 'INBOX'),
        'after_fetch' => $after_fetch,
        'move_folder' => ($after_fetch === 'move') ? $line('move_folder') : '',
        'reply_via'   => $reply_via,
        'smtp_host'   => $smtp_host,
        'smtp_port'   => ($smtp_host !== '') ? (int) $line('smtp_port', (string) $preset['smtp_port']) : 0,
        'sent_copy'   => !empty($input['sent_copy']) ? 1 : 0,
        'sent_folder' => $line('sent_folder'),
        'connection_id' => $connection_id,
        'reads_bounces' => !empty($input['reads_bounces']) ? 1 : 0,
        'signature'   => $signature['signature'],
    );
    $fail = function ($error, $field) use ($values) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => $values);
    };

    if (($values['name'] === '') || (mb_strlen($values['name']) > 100)) {
        return $fail(lang('Give the mailbox a name of at most 100 characters.'), 'name');
    }

    if ((strlen($values['address']) > 255) || !filter_var($values['address'], FILTER_VALIDATE_EMAIL)) {
        return $fail(lang('Enter the mailbox\'s e-mail address.'), 'address');
    }

    if (($kept_in === 'channel') && ($values['channel_id'] <= 0)) {
        return $fail(lang('Choose the channel its mail goes to.'), 'channel_id');
    }

    if (!$signature['ok']) {
        return $fail($signature['error'], 'signature');
    }

    if (($auth_server !== '') && ((strlen($auth_server) > 255) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $auth_server))) {
        return $fail(lang('The checking server is a host name such as mx.google.com.'), 'auth_server');
    }

    // A connected account signs in instead of a password, and is what
    // answers leave through.
    if (($reply_via === 'connection') && ($connection_id <= 0)) {
        return $fail(lang('Choose the connected account answers are sent through.'), 'connection_id');
    }

    if (($connection_id > 0) && ($host === '')) {
        return $fail(lang('A connected account reads the mailbox over IMAP: enter its server, or choose the service.'), 'host');
    }

    if (($smtp_host !== '') && (!pg_imap_host_valid($smtp_host) || ($values['smtp_port'] < 1) || ($values['smtp_port'] > 65535))) {
        return $fail(lang('The sending server is a host name such as smtp.gmail.com, with a port from 1 to 65535.'), 'smtp_host');
    }

    // Neither server: the box takes only .eml files brought in by hand.
    if (($host === '') && ($smtp_host === '')) {
        return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values);
    }

    if (($host !== '') && (!pg_imap_host_valid($host) || ($port < 1) || ($port > 65535))) {
        return $fail(lang('The IMAP server is a host name such as imap.gmail.com, with a port from 1 to 65535.'), 'host');
    }

    if (($values['user'] === '') || (strlen($values['user']) > 255)) {
        return $fail(lang('Enter the user name the mailbox signs in with.'), 'user');
    }

    if ((strlen($values['password']) > 1024) || preg_match('/[\x00\r\n]/', $values['password'])) {
        return $fail(lang('The password cannot be kept as typed.'), 'password');
    }

    if ((strlen($values['folder']) > 255) || (($after_fetch === 'move') && (($values['move_folder'] === '') || (strlen($values['move_folder']) > 255)))) {
        return $fail(lang('Name the folder to read and, to move mail after reading, the folder it goes to.'), 'folder');
    }

    if (strlen($values['sent_folder']) > 255) {
        return $fail(lang('The sent folder\'s name is at most 255 characters.'), 'sent_folder');
    }

    if (($after_fetch === 'move') && (strcasecmp($values['move_folder'], $values['folder']) === 0)) {
        return $fail(lang('Mail cannot be moved into the folder it is read from.'), 'move_folder');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values);
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
 * What a box signs in to its IMAP server with: its kept password, or a token
 * of its connected account (purpose mail_read). Asks the account's provider
 * for a token when it holds none fresh; talks to no IMAP server.
 *
 * @param array $box ws_mail_boxes row
 * @return array ok, error (no_password | no_account), detail, password, token
 */
function ws_mail_box_credentials($box)
{
    $settings = ws_mail_box_settings($box);
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'password' => '', 'token' => '');
    $connection_id = (int) ($settings['connection_id'] ?? 0);

    if ($connection_id > 0) {
        $account = function_exists('pg_connect_account') ? pg_connect_account($connection_id) : null;
        $purpose = function_exists('pg_connect_purpose') ? pg_connect_purpose('mail_read') : null;

        if (!is_array($account) || !in_array('mail_read', (array) $account['purposes'], true) || !isset($purpose['imap_scopes'][$account['provider']])) {
            return array_merge($out, array('error' => 'no_account'));
        }

        $token = pg_connect_access_token($connection_id, $purpose['imap_scopes'][$account['provider']]);

        return $token['ok']
            ? array_merge($out, array('ok' => true, 'token' => (string) $token['token']))
            : array_merge($out, array('error' => 'no_account', 'detail' => (string) $token['error']));
    }

    $secret = pg_connect_unseal((string) ($settings['secret'] ?? ''));

    return isset($secret['password'])
        ? array_merge($out, array('ok' => true, 'password' => (string) $secret['password']))
        : array_merge($out, array('error' => 'no_password'));
}

/**
 * Signs a box in on an open IMAP session with what ws_mail_box_credentials()
 * found: XOAUTH2 with a token, else the user and password.
 *
 * @param array $session
 * @param array $box
 * @param array $credentials
 * @return bool
 */
function ws_mail_box_login(&$session, $box, $credentials)
{
    $user = (string) (ws_mail_box_settings($box)['user'] ?? '');

    return ((string) $credentials['token'] !== '')
        ? pg_imap_login_xoauth2($session, $user, (string) $credentials['token'])
        : pg_imap_login($session, $user, (string) $credentials['password']);
}

/**
 * The connected accounts a box may sign in with: those serving Reading
 * mailboxes.
 *
 * @return array[] pg_connect_account() rows
 */
function ws_mail_box_accounts()
{
    return function_exists('pg_connect_accounts') ? pg_connect_accounts(array('purpose' => 'mail_read')) : array();
}

/**
 * The button that connects a Microsoft account for Reading mailboxes, for a
 * person who may connect the site's accounts (connect.php: a manager and
 * above); others are told who can.
 *
 * @param array       $viewer
 * @param string|null $form the Site Settings form's id: a button of the hidden
 *                          Connect form pgset_mail_connect instead of a form
 * @return string HTML
 */
function ws_mail_connect_form($viewer, $form = null)
{
    if (!function_exists('pg_connect_start_fields') || !function_exists('pg_connect_purpose') || !pg_connect_purpose('mail_read')) {
        return '';
    }

    if ((int) $viewer['role'] > 2) {
        return '<div class="form-text mt-3">' . h(lang('A Microsoft 365 mailbox is read through a connected account; a manager or an administrator connects it here or in Settings › Connections.')) . '</div>';
    }

    // In Site Settings the card sits inside the settings form: its button
    // submits the hidden Connect form the category hands over
    // (includes/settings/workspace_channels.php).
    if ($form !== null) {
        return '
                <div class="mt-3 d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" form="pgset_mail_connect" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-microsoft me-1" aria-hidden="true"></i>' . h(lang('Connect a Microsoft account for mailboxes')) . '</button>
                    <span class="form-text m-0">' . h(lang('Sign in with the mailbox\'s own account; the site\'s Microsoft application must be set up in Settings › Connections first.')) . '</span>
                </div>';
    }

    return '
                <form method="post" action="connect.php" class="mt-3 d-flex flex-wrap align-items-center gap-3">
                    ' . pg_connect_start_fields('microsoft', 'mail_read', pg_settings_workspace_link('ws-mail')) . '
                    <button type="submit" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-microsoft me-1" aria-hidden="true"></i>' . h(lang('Connect a Microsoft account for mailboxes')) . '</button>
                    <span class="form-text m-0">' . h(lang('Sign in with the mailbox\'s own account; the site\'s Microsoft application must be set up in Settings › Connections first.')) . '</span>
                </form>';
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

        case 'bounce':
            return array('notice' => true, 'text' => lang('This e-mail says an answer was not delivered; a line was added under its conversation.'));

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

    $credentials = ws_mail_box_credentials($box);

    if (!$credentials['ok']) {
        return array_merge($out, array('error' => $credentials['error'], 'detail' => $credentials['detail']));
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

    if (!ws_mail_box_login($session, $box, $credentials)) {
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
        'no_account'    => lang('The connected account is gone, no longer serves Reading mailboxes, or must be connected again.'),
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
 * The card's forms on either screen: a mailbox added or changed
 * (mail_box_save), its connection tried (mail_test), or an .eml brought into
 * one (mail_eml).
 *
 * @param array         $viewer
 * @param string        $action
 * @param liveform      $liveform
 * @param array|null    $input  box_id and the form's fields under their old
 *                              names (null: what was posted)
 * @param array|null    $file   the .eml as $_FILES gives it (null: $_FILES['eml'])
 * @param callable|null $refuse takes a refusal (null: the liveform's error)
 */
function ws_mail_settings_post($viewer, $action, $liveform, $input = null, $file = null, $refuse = null)
{
    $post = is_array($input) ? $input : $_POST;
    $refuse = is_callable($refuse) ? $refuse : function ($sentence) use ($liveform) {
        $liveform->add_error($sentence);
    };

    if (!ws_mail_ready()) {
        $refuse(lang('The database has not been upgraded yet.'));
        return;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $box_id = is_scalar($post['box_id'] ?? 0) ? (int) ($post['box_id'] ?? 0) : 0;
    $box = ($box_id > 0) ? ws_mail_box($box_id) : null;

    if (($box_id > 0) && !$box) {
        $refuse(lang('That mailbox could not be found.'));
        return;
    }

    if ($action === 'mail_eml') {
        $file = is_array($file) ? $file : ($_FILES['eml'] ?? null);

        if (!$box || !is_array($file) || is_array($file['error']) || ((int) $file['error'] !== UPLOAD_ERR_OK) || !is_uploaded_file((string) $file['tmp_name'])) {
            $refuse(lang('Choose an .eml file to bring in.'));
            return;
        }

        $result = ws_mail_ingest((string) file_get_contents((string) $file['tmp_name']), $box);
        $said = ws_mail_ingest_message($result);

        if ($said['notice']) {
            $liveform->add_notice($said['text']);
        } else {
            $refuse($said['text']);
        }

        if ($result['status'] === 'stored') {
            log_activity(lang(array('string' => 'an e-mail was brought into the mailbox {var:1} by hand', 'vars' => (string) $box['name'])), $username);
        }

        return;
    }

    if ($action === 'mail_test') {
        if (!$box) {
            $refuse(lang('That mailbox could not be found.'));
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
            $refuse(h(ws_mail_error_text($result['error'], $result['detail'], (string) ($settings['host'] ?? ''))));
            return;
        }

        $liveform->add_notice(lang(array('string' => 'Connected: {var:1} e-mails in {var:2}.', 'vars' => array(pg_format_number($result['exists'], 0), h((string) $box['folder'])))));

        if ($filled) {
            $liveform->add_notice(lang(array('string' => 'The checking server was set to {var:1}, the name the newest e-mail was checked under.', 'vars' => $result['auth_server'])));
        }

        return;
    }

    $checked = ws_mail_box_save_check($viewer, $box, $post);

    if (!$checked['ok']) {
        $refuse($checked['error']);
        return;
    }

    ws_mail_box_save_write($viewer, $box, $checked, $username);
    $liveform->add_notice(lang('The mailbox was saved.'));
}

/**
 * Every check of a mailbox's form, before anything is written: the fields,
 * the channel or, for a box kept in no channel, its readers
 * (mail_room.php), the routes, the automatic reply, the first answer's target,
 * Pinegrap AI's sorting, the password and the connected account. Writes
 * nothing.
 *
 * @param array      $viewer
 * @param array|null $box  null for a new one
 * @param array      $post the form's fields under their Workspace Settings names
 * @return array ok, error, field (the form field to mark), values, auto,
 *               settings (what ws_mail_box_save_write() stores)
 */
function ws_mail_box_save_check($viewer, $box, $post)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => array(), 'auto' => array(), 'settings' => array());
    };

    $checked = ws_mail_box_input($post);

    if (!$checked['ok']) {
        return $fail($checked['error'], $checked['field']);
    }

    $values = $checked['values'];
    $channels = ws_mail_box_channels($viewer);
    $room = ws_mail_room_input($post, ws_team_ids(), array_keys(ws_mail_room_departments($box)));

    if (($room['kept_in'] === 'mailbox') && !ws_mail_room_ready()) {
        return $fail(lang('The database has not been upgraded yet.'), 'kept_in');
    }

    if (!$room['ok']) {
        return $fail($room['error'], $room['field']);
    }

    // A box keeps a channel its editor cannot read, as long as nobody picks
    // another one. Its own room is never a channel to pick.
    if (($room['kept_in'] === 'channel') && !isset($channels[$values['channel_id']])
        && !($box && (ws_mail_box_kept_in($box) === 'channel') && ((int) $box['channel_id'] === $values['channel_id']))) {
        return $fail(lang('Choose the channel its mail goes to.'), 'channel_id');
    }

    $kept_channels = array_map(function ($route) {
        return (int) $route['channel_id'];
    }, ws_mail_routes($box));
    $routes = ws_mail_routes_input((array) ($post['route_match'] ?? array()), (array) ($post['route_channel'] ?? array()), array_keys($channels), $kept_channels);

    if (!$routes['ok']) {
        return $fail($routes['error'], 'route_match');
    }

    $auto = ws_mail_auto_reply_input($post);

    if (!$auto['ok']) {
        return $fail($auto['error'], 'auto_reply_text');
    }

    $first = ws_mail_first_reply_input($post);

    if (!$first['ok']) {
        return $fail($first['error'], 'first_reply_hours');
    }

    $settings = ws_mail_box_settings($box);
    $settings['routes'] = $routes['routes'];
    $settings['first_reply_hours'] = $first['hours'];
    $settings = ws_mail_triage_input($post, $settings, ws_mail_triage_ai_ready());

    // Where the mail is kept and who reads a box kept in no channel. A box
    // that never had a room keeps none of it; one that has keeps its room
    // (room_id) and its readers when it moves to a channel.
    unset($settings['kept_in']);

    if ($room['kept_in'] === 'mailbox') {
        $settings['kept_in'] = 'mailbox';

        // A suggestion is a message under the card: nothing shows it in a
        // room nobody opens as a channel.
        $settings['ai_triage'] = false;
    }

    if (($room['kept_in'] === 'mailbox') || ((int) ($settings['room_id'] ?? 0) > 0)) {
        $settings['room_people'] = $room['people'];
        $settings['room_departments'] = $room['departments'];
    }

    // A password typed now replaces the kept one; an empty box keeps it.
    if ($values['password'] !== '') {
        if (!pg_connect_can_seal()) {
            return $fail(lang('This site has no encryption key, so a password cannot be kept.'), 'password');
        }

        $settings['secret'] = pg_connect_seal(array('password' => $values['password']));
    }

    // A connected account (purpose mail_read) the box signs in with: only
    // one that serves reading mailboxes.
    if ($values['connection_id'] > 0) {
        $account = function_exists('pg_connect_account') ? pg_connect_account($values['connection_id']) : null;

        if (!is_array($account) || !in_array('mail_read', (array) $account['purposes'], true)) {
            return $fail(lang('Choose a connected account that serves Reading mailboxes.'), 'connection_id');
        }
    }

    // With a connected account nothing signs in with the password.
    $signs_in = ($values['connection_id'] <= 0) && (($values['host'] !== '') || ($values['smtp_host'] !== ''));

    // The kept password goes only to the servers it was typed for: a new
    // server, sending or reading, or a new user asks for it again.
    if ($signs_in && (empty($settings['secret']) || (($settings['host'] ?? '') !== $values['host']) || (($settings['user'] ?? '') !== $values['user'])
        || (($values['smtp_host'] !== '') && (($settings['smtp_host'] ?? '') !== $values['smtp_host']))) && ($values['password'] === '')) {
        return $fail(lang('Enter the password for this server and user.'), 'password');
    }

    if (!$signs_in) {
        unset($settings['secret']);
    }

    foreach (array('auth_server', 'provider', 'host', 'port', 'user', 'move_folder', 'smtp_host', 'smtp_port', 'sent_copy', 'sent_folder', 'connection_id', 'reads_bounces', 'signature') as $key) {
        $settings[$key] = $values[$key];
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values, 'auto' => $auto, 'settings' => $settings);
}

/**
 * Stores a mailbox ws_mail_box_save_check() passed, and writes its Site Log
 * line. A box kept in no channel gets its room first, and the room's
 * members follow the choice (ws_mail_room_save()).
 *
 * @param array      $viewer
 * @param array|null $box     null for a new one
 * @param array      $checked ws_mail_box_save_check()
 * @param string     $username
 */
function ws_mail_box_save_write($viewer, $box, $checked, $username)
{
    $values = $checked['values'];
    $auto = $checked['auto'];
    $kept = ws_mail_room_save($viewer, $values, $checked['settings']);
    $settings = $kept['settings'];

    $now = time();

    $set = "name = '" . e($values['name']) . "', address = '" . e($values['address']) . "', channel_id = '" . (int) $kept['channel_id'] . "',
        settings = '" . e(json_encode($settings)) . "', folder = '" . e($values['folder']) . "', after_fetch = '" . e($values['after_fetch']) . "',
        reply_via = '" . e($values['reply_via']) . "', auto_reply = '" . (int) $auto['auto_reply'] . "', auto_reply_text = '" . e($auto['auto_reply_text']) . "',
        enabled = '" . (int) $values['enabled'] . "', updated_at = '" . $now . "'";

    if ($box) {
        db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . (int) $box['id'] . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was changed', 'vars' => $values['name'])), $username);
    } else {
        db("INSERT INTO ws_mail_boxes SET " . $set . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was added', 'vars' => $values['name'])), $username);
    }
}

/**
 * One mailbox's form, empty for a new one.
 *
 * @param string     $self_url
 * @param array|null $box
 * @param array[]    $channels ws_mail_box_channels(); "Kept in" and the
 *                             readers of a box kept in no channel are
 *                             ws_mail_room_fields()
 * @param bool       $ai_ready Pinegrap AI can be asked (ws_mail_triage_ai_ready())
 * @param string|null $form    the Site Settings form's id: no form of its own,
 *                             fields ws_mail[<id>][...] joined to it
 *                             (ws_mail_box_dialog_names()), a named Save
 *                             (ws_mail_box_save, value the box's id)
 * @return string
 */
function ws_mail_box_form($self_url, $box, $channels, $ai_ready = false, $form = null)
{
    $id = $box ? (int) $box['id'] : 0;
    $prefix = 'ws_mail_' . $id . '_';
    $settings = ws_mail_box_settings($box);
    // A box kept in no channel names its room as its channel: none is chosen.
    $channel_id = ($box && (ws_mail_box_kept_in($box) === 'channel')) ? (int) $box['channel_id'] : 0;
    $options = '<option value="">' . h(lang('Choose')) . '</option>';

    if ($box && ($channel_id > 0) && !isset($channels[$channel_id])) {
        $options .= '<option value="' . $channel_id . '" selected>' . h(lang('A channel you do not see')) . '</option>';
    }

    foreach ($channels as $channel) {
        $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === $channel_id) ? ' selected' : '') . '>'
            . h('#' . $channel['name']) . '</option>';
    }

    $fields = '
            <div class="row g-5">
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="name" id="' . $prefix . 'name" maxlength="100" required value="' . h($box ? (string) $box['name'] : '') . '" placeholder="' . h(lang('Support')) . '">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'address">' . h(lang('E-mail address')) . '</label>
                    <input class="form-control form-control-sm" type="email" name="address" id="' . $prefix . 'address" maxlength="255" required value="' . h($box ? (string) $box['address'] : '') . '" placeholder="support@example.com">
                </div>
                ' . ws_mail_room_fields($prefix, $box, $options) . '
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'auth">' . h(lang('Checking server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="auth_server" id="' . $prefix . 'auth" maxlength="255" value="' . h((string) ($settings['auth_server'] ?? '')) . '" placeholder="mx.google.com" autocomplete="off">
                    <div class="form-text">' . h(lang('The server that receives this mailbox\'s mail and writes Authentication-Results. Only its verdict is believed; left empty, every sender stays unverified and no mail is tied to a customer.')) . '</div>
                </div>
                ' . ws_mail_route_fields($prefix, $box, $channels) . '
                ' . ws_mail_box_server_fields($prefix, $box, $settings) . '
                <div class="md:col-12">
                    <label class="form-label" for="' . $prefix . 'signature">' . h(lang('Signature')) . '</label>
                    <textarea class="form-control form-control-sm" name="signature" id="' . $prefix . 'signature" rows="3" maxlength="' . WS_MAIL_SIGNATURE_MAX . '">' . h(ws_mail_box_signature($box)) . '</textarea>
                    <div class="form-text">' . h(lang('Plain text put under a new e-mail sent from this mailbox, after the writer\'s name. Left empty, the mailbox\'s name is put there.')) . '</div>
                </div>
                ' . ws_mail_first_reply_field($prefix, $box) . '
                ' . ws_mail_auto_reply_fields($prefix, $box) . '
                ' . ws_mail_triage_field($prefix, $box, $ai_ready) . '
                <div class="md:col-12">
                    <div class="form-field">
                        <input class="check" type="checkbox" name="reads_bounces" value="1" id="' . $prefix . 'reads_bounces"' . (!empty($settings['reads_bounces']) ? ' checked' : '') . '>
                        <label for="' . $prefix . 'reads_bounces">' . h(lang('Reads bounces')) . '</label>
                    </div>
                    <div class="form-text">' . h(lang('Queued and campaign e-mails are sent with this address as their return path, written with a + and a code after its name. Delivery failures that come back are recorded and leave the channel; an address that keeps failing gets no more mail. The mail server must deliver such addresses to this mailbox, and the site\'s sending address must be on the same domain.')) . '</div>
                </div>
                <div class="md:col-12">
                    <div class="form-field">
                        <div class="switch"><input type="checkbox" name="enabled" value="1" id="' . $prefix . 'enabled"' . ((!$box || (int) $box['enabled'] === 1) ? ' checked' : '') . '></div>
                        <label for="' . $prefix . 'enabled">' . h(lang('On')) . '</label>
                    </div>
                </div>
            </div>';
    $save = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save'));

    if ($form !== null) {
        return '
        <div class="ws-mail-box-form" data-ws-mail-dialog>' . ws_mail_box_dialog_names($fields, $id, $form) . '
            <button type="submit" name="ws_mail_box_save" value="' . $id . '" form="' . h($form) . '" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mt-5">' . $save . '</button>
        </div>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-mail" class="py-5 ws-mail-box-form">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mail_box_save">
            <input type="hidden" name="box_id" value="' . $id . '">
            ' . $fields . '
            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mt-5">' . $save . '</button>
        </form>';
}

/**
 * A mailbox form's fields as the Site Settings dialog posts them: each
 * name="<field>" (or "<field>[]") of the markup the form and its helpers
 * draw (ws_mail_route_fields(), ws_mail_box_server_fields(), the first
 * answer, automatic reply and sorting fields) becomes ws_mail[<id>][<field>]
 * joined to the settings form, and nothing is required: the box's drawer
 * sits outside that form and the pane's Save must not be blocked by it; the
 * save checks every field. Pure.
 *
 * @param string $html
 * @param int    $id   the box's id, 0 for a new one
 * @param string $form the settings form's id
 * @return string
 */
function ws_mail_box_dialog_names($html, $id, $form)
{
    $html = preg_replace('/ name="([a-z][a-z0-9_]*)(\[\])?"/', ' name="ws_mail[' . (int) $id . '][$1]$2" form="' . h($form) . '"', (string) $html);

    return preg_replace('/ required(?=[ >])/', '', $html);
}

/**
 * The fields of a mailbox's drawer under the names Workspace Settings
 * posts, with its box_id. Pure.
 *
 * @param array $post $_POST
 * @param int   $id   the box's id, 0 for a new one
 * @return array
 */
function ws_mail_settings_dialog_input($post, $id)
{
    $id = (int) $id;
    $input = (isset($post['ws_mail'][$id]) && is_array($post['ws_mail'][$id])) ? $post['ws_mail'][$id] : array();
    $input['box_id'] = $id;

    return $input;
}

/**
 * The server half of a mailbox's form: provider, IMAP server, user,
 * password (drawn empty, an empty box keeps the kept one), folder, what
 * happens to mail once read, and how answers are sent.
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
    $via = $box ? (string) ($box['reply_via'] ?? 'box') : 'box';
    $connection_id = (int) ($settings['connection_id'] ?? 0);
    $accounts = '<option value="">' . h(lang('None: sign in with the password')) . '</option>';
    $listed = false;

    foreach (ws_mail_box_accounts() as $account) {
        $listed = $listed || ((int) $account['id'] === $connection_id);
        $accounts .= '<option value="' . (int) $account['id'] . '"' . (((int) $account['id'] === $connection_id) ? ' selected' : '') . '>'
            . h((string) $account['label'] . (((string) $account['status'] !== 'active') ? ' (' . lang('connect again') . ')' : '')) . '</option>';
    }

    // A chosen account since disconnected stays named until another is picked.
    if (($connection_id > 0) && !$listed) {
        $accounts .= '<option value="' . $connection_id . '" selected>' . h(lang('An account no longer connected')) . '</option>';
    }
    $copy = !isset($settings['sent_copy']) || !empty($settings['sent_copy']);

    return '
                <div class="md:col-12"><h3 class="h6 mt-3 mb-0">' . h(lang('Reading the mailbox')) . '</h3>
                    <div class="form-text">' . h(lang('Leave the server empty to bring mail in only as .eml files. A chosen service fills an empty server, checking server and sending server.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'provider">' . h(lang('Mail service')) . '</label>
                    <select class="form-control form-control-sm" name="provider" id="' . $prefix . 'provider">' . $options . '</select>
                </div>
                <div class="md:col-5">
                    <label class="form-label" for="' . $prefix . 'host">' . h(lang('IMAP server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="host" id="' . $prefix . 'host" maxlength="253" value="' . h((string) ($settings['host'] ?? '')) . '" placeholder="imap.example.com" autocomplete="off">
                </div>
                <div class="md:col-3">
                    <label class="form-label" for="' . $prefix . 'port">' . h(lang('Port')) . '</label>
                    <input class="form-control form-control-sm" type="number" name="port" id="' . $prefix . 'port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['port'] ?? 0, $provider, 'port') . '">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'user">' . h(lang('User name')) . '</label>
                    <input class="form-control form-control-sm" type="text" spellcheck="false" autocapitalize="off" name="user" id="' . $prefix . 'user" maxlength="255" value="' . h((string) ($settings['user'] ?? '')) . '" autocomplete="off">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'password">' . h(lang('Password')) . '</label>
                    <input class="form-control form-control-sm" type="password" name="password" id="' . $prefix . 'password" value="" autocomplete="new-password" placeholder="' . h($kept ? lang('Stored. Type a new one to replace it.') : '') . '">
                </div>
                <div class="md:col-12">
                    <label class="form-label" for="' . $prefix . 'connection">' . h(lang('Connected account')) . '</label>
                    <select class="form-control form-control-sm" name="connection_id" id="' . $prefix . 'connection"' . (($via === 'connection') ? ' required' : '') . '>' . $accounts . '</select>
                    <div class="form-text">' . h(lang('Microsoft 365 and Outlook.com sign in with a connected account instead of a password.')) . '</div>
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
                <div class="md:col-4' . ($move ? '' : ' d-none') . '" data-ws-mail-when="after_fetch=move">
                    <label class="form-label" for="' . $prefix . 'move">' . h(lang('Folder to move to')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="move_folder" id="' . $prefix . 'move" maxlength="255" value="' . h((string) ($settings['move_folder'] ?? '')) . '" placeholder="' . h(lang('Processed')) . '">
                </div>
                <div class="md:col-12"><h3 class="h6 mt-3 mb-0">' . h(lang('Sending answers')) . '</h3>
                    <div class="form-text">' . h(lang('Answers leave from the mailbox\'s address. Its own sending server signs in with the user name and password above; the site\'s mail settings send only for an address on the site\'s own mail domain.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'reply_via">' . h(lang('Send through')) . '</label>
                    <select class="form-control form-control-sm" name="reply_via" id="' . $prefix . 'reply_via">
                        <option value="box"' . (($via === 'box') ? ' selected' : '') . '>' . h(lang('The mailbox\'s sending server')) . '</option>
                        <option value="site"' . (($via === 'site') ? ' selected' : '') . '>' . h(lang('The site\'s mail settings')) . '</option>
                        <option value="connection"' . (($via === 'connection') ? ' selected' : '') . '>' . h(lang('The connected account')) . '</option>
                    </select>
                </div>
                <div class="md:col-5' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="form-label" for="' . $prefix . 'smtp_host">' . h(lang('SMTP server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="smtp_host" id="' . $prefix . 'smtp_host" maxlength="253" value="' . h((string) ($settings['smtp_host'] ?? '')) . '" placeholder="smtp.example.com" autocomplete="off">
                </div>
                <div class="md:col-3' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="form-label" for="' . $prefix . 'smtp_port">' . h(lang('Port')) . '</label>
                    <input class="form-control form-control-sm" type="number" name="smtp_port" id="' . $prefix . 'smtp_port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['smtp_port'] ?? 0, $provider, 'smtp_port') . '">
                </div>
                <div class="md:col-8">
                    <div class="form-field mt-2">
                        <input class="check" type="checkbox" name="sent_copy" value="1" id="' . $prefix . 'sent_copy"' . ($copy ? ' checked' : '') . '>
                        <label for="' . $prefix . 'sent_copy">' . h(lang('Keep a copy of each answer in the mailbox\'s sent folder')) . '</label>
                    </div>
                    <div class="form-text">' . h(lang('Needs the IMAP server above. Gmail and Microsoft 365 keep what their own sending server sends, so no copy is added for them.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="form-label" for="' . $prefix . 'sent_folder">' . h(lang('Sent folder')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="sent_folder" id="' . $prefix . 'sent_folder" maxlength="255" value="' . h((string) ($settings['sent_folder'] ?? '')) . '" placeholder="' . h(lang('Found by itself')) . '">
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
 * and the .eml upload. In Site Settings (Channels, mail and voice, with
 * $form) Test connection is a named submit button of the settings form
 * (ws_mail_test, value the box's id), and a box's form with its .eml upload,
 * and Add a mailbox, open in drawers (ws_settings_mail_drawer()).
 *
 * @param string      $self_url
 * @param array       $viewer
 * @param string|null $form the Site Settings form's id
 * @return string
 */
function ws_mail_settings_card($self_url, $viewer, $form = null)
{
    if (!ws_mail_ready()) {
        return '';
    }

    $channels = ws_mail_box_channels($viewer);
    $ai_ready = ws_mail_triage_ai_ready();
    $rows = '';

    foreach (ws_mail_settings_boxes() as $box) {
        $id = (int) $box['id'];
        $channel = $channels[(int) $box['channel_id']] ?? null;
        $place = $channel ? '<a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h((string) $channel['name']) . '</a>' : h(lang('A channel you do not see'));

        // A box kept in no channel is read on the E-mails screen.
        if (ws_mail_box_kept_in($box) === 'mailbox') {
            $place = '<a href="' . h(ws_mail_room_url('', 0, $id)) . '">' . h(lang('Kept in the mailbox itself')) . '</a>';
        }

        $line = '
                <div class="ws-grow">
                    <div class="fw-semibold">' . h((string) $box['name']) . ' <span class="small fg-secondary">' . h((string) $box['address']) . '</span></div>
                    <div class="small fg-secondary">'
                        . $place
                        . ' · ' . h(((int) $box['last_mail_at'] > 0) ? lang(array('string' => 'Last e-mail {var:1}', 'vars' => date('d.m.Y H:i', (int) $box['last_mail_at']))) : lang('No e-mail yet')) . '
                    </div>
                    <div class="small fg-secondary">' . ws_mail_box_check_line($box) . '</div>
                </div>';
        $reads = ((string) (ws_mail_box_settings($box)['host'] ?? '') !== '');
        $test = '<i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('Test connection'));

        if ($form !== null) {
            $rows .= '
            <div class="ws-dept-row flex-wrap' . ((int) $box['enabled'] === 1 ? '' : ' opacity-50') . '">' . $line . '
                ' . ($reads ? '<button type="submit" name="ws_mail_test" value="' . $id . '" form="' . h($form) . '" class="btn btn-sm btn-text theme-secondary">' . $test . '</button>' : '') . '
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-' . $id . '" aria-controls="pgset-ws-mail-' . $id . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            </div>';
            continue;
        }

        $rows .= '
            <div class="ws-dept-row' . ((int) $box['enabled'] === 1 ? '' : ' opacity-50') . '">' . $line . '
                ' . ($reads ? '
                <form method="post" action="' . h($self_url) . '#ws-mail" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mail_test">
                    <input type="hidden" name="box_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-text theme-secondary">' . $test . '</button>
                </form>' : '') . '
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="collapse" data-bs-target="#ws-mail-' . $id . '" aria-expanded="false"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            </div>
            <div class="collapse" id="ws-mail-' . $id . '">
                ' . ws_mail_box_form($self_url, $box, $channels, $ai_ready) . '
                ' . ws_mail_eml_form($self_url . '#ws-mail', $id, false) . '
            </div>';
    }

    if ($rows === '') {
        $rows = '<div class="ws-empty py-5"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('No mailboxes yet. A mailbox brings the e-mail sent to an address into a channel, where the team talks it over.')) . '</div>';
    }

    $intro = '<div class="form-text mt-5">' . h(lang('A mailbox with a server is read every minute while the general scheduled job runs; an .eml file saved from a mail program can also be brought in by hand.')) . '</div>';

    if ($form !== null) {
        return '
    <div id="pgset-ws-mail" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="pg-title-section ws-grow">' . h(lang('Mailboxes')) . '</span>
                <button type="button" class="btn btn-sm btn-outline theme-primary rounded-pill" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-0" aria-controls="pgset-ws-mail-0"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                ' . $rows . '
                ' . $intro . '
                ' . ws_mail_connect_form($viewer, $form) . '
            </div>
        </div>
    </div>' . ws_mail_settings_script();
    }

    return '
        <div class="card mt-7" id="ws-mail">
            <div class="card-header d-flex align-items-center gap-3">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . h(lang('Mailboxes')) . '</h2>
                <button type="button" class="btn btn-sm btn-text theme-primary" data-bs-toggle="collapse" data-bs-target="#ws-mail-new" aria-expanded="false"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                <div class="collapse" id="ws-mail-new">' . ws_mail_box_form($self_url, null, $channels, $ai_ready) . '</div>
                ' . $rows . '
                ' . $intro . '
                ' . ws_mail_connect_form($viewer) . '
            </div>
        </div>' . ws_mail_settings_script();
}

/**
 * The mailboxes, each with the time of its newest e-mail (last_mail_at).
 *
 * @return array[]
 */
function ws_mail_settings_boxes()
{
    return (array) db_items("SELECT b.*, (SELECT MAX(i.created_at) FROM ws_mail_items i WHERE i.box_id = b.id) AS last_mail_at
        FROM ws_mail_boxes b ORDER BY b.name");
}

/**
 * Bring in an .eml file: a form of its own, as a file cannot travel with the
 * Site Settings pane's save. On Workspace Settings it posts mail_eml; in
 * Site Settings it posts to the category's page (its save module reads
 * ws_mail_eml, value the box's id, and the file ws_mail_eml_file), which the
 * browser then shows.
 *
 * @param string $action the address it posts to
 * @param int    $id     the box's id
 * @param bool   $dialog Site Settings
 * @return string
 */
function ws_mail_eml_form($action, $id, $dialog)
{
    return '
                <form method="post" action="' . h($action) . '" enctype="multipart/form-data" class="pb-5 d-flex flex-wrap align-items-end gap-3">
                    ' . get_token_field() . '
                    ' . ($dialog ? '' : '<input type="hidden" name="ws_action" value="mail_eml">
                    <input type="hidden" name="box_id" value="' . (int) $id . '">') . '
                    <div>
                        <label class="form-label" for="ws_mail_eml_' . (int) $id . '">' . h(lang('Bring in an .eml file')) . '</label>
                        <input class="form-control form-control-sm" type="file" name="' . ($dialog ? 'ws_mail_eml_file' : 'eml') . '" id="ws_mail_eml_' . (int) $id . '" accept=".eml,message/rfc822">
                    </div>
                    <button type="submit"' . ($dialog ? ' name="ws_mail_eml" value="' . (int) $id . '"' : '') . ' class="btn btn-sm btn-text theme-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>' . h(lang('Bring in')) . '</button>
                </form>';
}

/**
 * A Site Settings drawer of the mailboxes card: a box's form with its .eml
 * upload (a form of its own after it, ws_mail_eml_form()) or, with $box
 * null, Add a mailbox's.
 *
 * @param array|null $box
 * @param array      $viewer
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog pg_settings_drawer()'s
 * @return string HTML
 */
function ws_settings_mail_drawer($box, $viewer, $form, $in_dialog)
{
    $channels = ws_mail_box_channels($viewer);
    $ai_ready = ws_mail_triage_ai_ready();

    if (!$box) {
        return pg_settings_drawer('pgset-ws-mail-0', h(lang('Add a mailbox')), ws_mail_box_form('', null, $channels, $ai_ready, $form), $in_dialog);
    }

    $id = (int) $box['id'];

    return pg_settings_drawer('pgset-ws-mail-' . $id, h((string) $box['name']) . ' · ' . h((string) $box['address']),
        ws_mail_box_form('', $box, $channels, $ai_ready, $form)
        . '<div class="border-top pt-5 mt-5">' . ws_mail_eml_form(pg_settings_url('workspace_channels'), $id, true) . '</div>', $in_dialog);
}

/**
 * The card's script, bound once: a field that applies to one choice of a
 * select shows only with it; the connected account is required when answers
 * are sent through it, and the channel unless the box is kept in no channel
 * (on Workspace Settings only: in Site Settings the save checks them, as a
 * required field in a drawer would stop the pane's Save).
 *
 * @return string
 */
function ws_mail_settings_script()
{
    return '
        <script>
        (function () {
            if (window.pgWsMailSettingsBound) {
                return;
            }

            window.pgWsMailSettingsBound = true;

            // A field by its name on Workspace Settings (reply_via) or in
            // Site Settings (ws_mail[3][reply_via]).
            function field(form, name) {
                return form.querySelector(\'[name="\' + name + \'"], [name$="[\' + name + \']"]\');
            }

            // A field that applies to one choice of a select shows only with
            // it (data-ws-mail-when="name=value"); the connected account is
            // required when answers are sent through it.
            function sync(form) {
                form.querySelectorAll(\'[data-ws-mail-when]\').forEach(function (part) {
                    var rule = part.getAttribute(\'data-ws-mail-when\').split(\'=\');
                    var select = field(form, rule[0]);

                    part.classList.toggle(\'d-none\', !select || (select.value !== rule[1]));
                });

                var kept = field(form, \'kept_in\');
                var channel = field(form, \'channel_id\');

                if (kept && channel && !form.hasAttribute(\'data-ws-mail-dialog\')) {
                    channel.required = (kept.value !== \'mailbox\');
                }

                var via = field(form, \'reply_via\');
                var account = field(form, \'connection_id\');

                if (via && account && !form.hasAttribute(\'data-ws-mail-dialog\')) {
                    var marker = form.querySelector(\'label[for="\' + account.id + \'"] .pg-required-marker\');

                    account.required = (via.value === \'connection\');

                    if (!account.required && marker) {
                        marker.remove();
                    } else if (account.required && window.pgInjectAriaAndRequiredMarkers) {
                        window.pgInjectAriaAndRequiredMarkers(form);
                    }
                }
            }

            document.addEventListener(\'change\', function (event) {
                var form = (event.target.closest && /(^|\\[)(after_fetch|reply_via|kept_in)\\]?$/.test(event.target.name || \'\')) ? event.target.closest(\'.ws-mail-box-form\') : null;

                if (form) {
                    sync(form);
                }
            });
        })();
        </script>';
}
