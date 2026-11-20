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
        $ready = ws_mail_on();
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
            'hint' => lang('Connect the Google account for Reading mailboxes and choose it as the connected account, or turn on 2-Step Verification and use an app password; Google refuses the account\'s own password.')),
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
 *                     reply_via, sent_copy, sent_folder, read_sent,
 *                     connection_id, reads_bounces, signature
 *                     (ws_mail_signature_input())
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
        'read_sent'   => !empty($input['read_sent']) ? 1 : 0,
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
 * The address of a provider's page on Connections, at its Connect an
 * account for mailboxes action, with the place to come back to once the
 * account is connected (pg_connections_screen_mail_return()). Pure.
 *
 * @param string $provider microsoft | google
 * @param string $return   a Site Settings address
 * @return string
 */
function ws_mail_connections_url($provider, $return = 'settings_mailboxes.php#pgset-ws-mail')
{
    return 'connections.php?service=' . (($provider === 'google') ? 'google' : 'microsoft')
        . '&mail_return=' . rawurlencode($return) . '#connections-mail';
}

/**
 * A provider's accounts as the Mailboxes card reads them
 * (ws_mail_connection_state()).
 *
 * @param string $provider microsoft | google
 * @return array count, other
 */
function ws_mail_connection_read($provider)
{
    if (!function_exists('pg_connect_accounts') || !function_exists('pg_connect_purpose') || !pg_connect_purpose('mail_read')) {
        return ws_mail_connection_state(array());
    }

    return ws_mail_connection_state(pg_connect_accounts(array('provider' => ($provider === 'google') ? 'google' : 'microsoft')));
}

/**
 * The state of a provider's connected accounts for Reading mailboxes. An
 * account connected on Connections for another purpose (Gmail's row there
 * connects for Sending e-mail, purpose mail) reads as connected on that
 * screen but holds no permission to read mail; the purposes it holds are
 * returned so the card can name the sign-in still missing. Pure.
 *
 * @param array[] $accounts the provider's pg_connect_accounts() rows
 * @return array count (int, accounts serving mail_read), other (string[],
 *               the purposes held when none serves mail_read)
 */
function ws_mail_connection_state($accounts)
{
    $count = 0;
    $other = array();

    foreach ((array) $accounts as $account) {
        $purposes = (isset($account['purposes']) && is_array($account['purposes'])) ? $account['purposes'] : array();

        if (in_array('mail_read', $purposes, true)) {
            $count++;
            continue;
        }

        foreach ($purposes as $purpose) {
            if (!in_array((string) $purpose, $other, true)) {
                $other[] = (string) $purpose;
            }
        }
    }

    return array('count' => $count, 'other' => ($count > 0) ? array() : $other);
}

/**
 * The names of purposes, for the card's words.
 *
 * @param string[] $keys
 * @return string
 */
function ws_mail_connection_purpose_names($keys)
{
    $names = array();

    foreach ((array) $keys as $key) {
        $purpose = function_exists('pg_connect_purpose') ? pg_connect_purpose((string) $key) : null;
        $names[] = (is_array($purpose) && isset($purpose['label'])) ? (string) $purpose['label'] : (string) $key;
    }

    return implode(', ', $names);
}

/**
 * The state of a provider's accounts for Reading mailboxes, shown on the
 * Mailboxes card and in a Gmail box's form: Connected or Not connected and
 * a link to the provider's page on Connections, where the account is
 * connected. A user below manager cannot open Connections, so they are told
 * who connects it. Pure but for the words.
 *
 * An account connected for other purposes only reads "Connected for
 * another purpose", names them and the missing Reading mailboxes sign-in
 * and leads to it; a connected one, on the card, says where the mailbox is
 * added next.
 *
 * @param array     $viewer
 * @param string    $provider microsoft | google
 * @param array|int $count    ws_mail_connection_state(), or the number of
 *                            accounts serving mail_read
 * @param bool   $ready    the provider's application is set up
 *                         (ws_mail_connect_ready())
 * @param bool   $new_tab  open the page in a new tab: a box's drawer, whose
 *                         form may be half filled
 * @return string HTML
 */
function ws_mail_connection_html($viewer, $provider, $count, $ready, $new_tab = false)
{
    $google = ($provider === 'google');
    $provider = $google ? 'google' : 'microsoft';
    $state = is_array($count) ? $count : array('count' => (int) $count, 'other' => array());
    $connected = (isset($state['count']) && ((int) $state['count'] > 0));
    $other = (!$connected && !empty($state['other'])) ? (array) $state['other'] : array();

    if ($connected) {
        $pill = '<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>';
    } elseif ($other) {
        $pill = '<span class="pg-pill pg-tone-amber">' . h(lang('Connected for another purpose')) . '</span>';
    } else {
        $pill = '<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>';
    }

    if ($connected) {
        $note = $new_tab ? '' : lang(array(
            'string' => 'Next, add the mailbox with Add a mailbox and choose Mail service: {var:1}.',
            'vars'   => $google ? lang('Gmail or Google Workspace') : lang('Microsoft 365 or Outlook.com')));
    } elseif ($other) {
        $note = lang(array(
            'string' => 'The account is connected for {var:1} only; reading mail needs one more sign-in, for {var:2}.',
            'vars'   => array(ws_mail_connection_purpose_names($other), lang('Reading mailboxes'))));
    } else {
        $note = '';
    }

    if ((int) $viewer['role'] > 2) {
        $side = '<span class="pg-text-meta">' . h($google
            ? lang('A Gmail mailbox can be read through a connected account; a manager or an administrator connects it here or in Settings › Connections.')
            : lang('A Microsoft 365 mailbox is read through a connected account; a manager or an administrator connects it here or in Settings › Connections.')) . '</span>';
    } else {
        $side = '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h(ws_mail_connections_url($provider)) . '"'
            . ($new_tab ? ' target="_blank" rel="noopener"' : '') . '>'
            . h($connected ? lang('Manage in Connections') : ($other ? lang('Sign in for mailboxes in Connections') : lang('Connect in Connections'))) . '</a>';
    }

    return '
                <div class="mt-3 d-flex flex-wrap align-items-center gap-3">
                    <span class="pg-text-2"><i class="bi bi-' . $provider . '" aria-hidden="true"></i> '
                        . h($google ? lang('Google account for mailboxes') : lang('Microsoft account for mailboxes')) . '</span>
                    ' . $pill . '
                    ' . $side . '
                </div>' . (($note === '') ? '' : '
                <div class="pg-field-help mt-1">' . h($note) . '</div>') . ($ready ? '' : '
                <div class="pg-field-help mt-1">' . h($google ? lang('The Google application is not set up yet.') : lang('The Microsoft application is not set up yet.')) . '</div>');
}

/**
 * Both providers' states on the Mailboxes card.
 *
 * @param array $viewer
 * @return string HTML
 */
function ws_mail_connections_html($viewer)
{
    if (!function_exists('pg_connect_purpose') || !pg_connect_purpose('mail_read')) {
        return '';
    }

    $html = '';

    foreach (array('microsoft', 'google') as $provider) {
        $html .= ws_mail_connection_html($viewer, $provider, ws_mail_connection_read($provider), ws_mail_connect_ready($provider));
    }

    return $html;
}

/**
 * Whether an account of a provider can be connected for Reading mailboxes:
 * the site's application for it is set up (pg_connect_app_ready()).
 *
 * @param string $provider microsoft | google
 * @return bool
 */
function ws_mail_connect_ready($provider = 'microsoft')
{
    return function_exists('pg_connect_app') && function_exists('pg_connect_app_ready')
        && pg_connect_app_ready(pg_connect_app(($provider === 'google') ? 'google' : 'microsoft'));
}

/**
 * What the Gmail part of a box's form shows beside its words: the Google
 * account's state with a link to Google's page on Connections, opened in a
 * new tab so the form stays as typed (ws_mail_connection_html()), and the
 * addresses Google Cloud must list as redirect URIs (Sign in with Google's
 * and connect.php's).
 *
 * @param array $viewer
 * @return array connect (HTML), addresses (string[])
 */
function ws_mail_google_parts($viewer)
{
    $connect = (function_exists('pg_connect_purpose') && pg_connect_purpose('mail_read'))
        ? ws_mail_connection_html($viewer, 'google', ws_mail_connection_read('google'), ws_mail_connect_ready('google'), true)
        : '';

    return array('connect' => $connect, 'addresses' => pg_google_cloud_redirect_addresses('google'));
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

        case 'blocked':
            return array('notice' => true, 'text' => lang('This sender is blocked: the e-mail was kept for 30 days and opened no conversation.'));

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
    // A WhatsApp or SMS account's box is kept by its own card.
    $box = ws_mail_box_is_phone($box) ? null : $box;

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

    // With the Workspace off a new box, or one kept in no channel, is kept in
    // the mailbox itself whatever was posted (ws_mail_box_rooms_only()).
    $rooms_only = ws_mail_box_rooms_only($box, ws_enabled());

    if ($rooms_only) {
        $post['kept_in'] = 'mailbox';
    }

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

    // The form drew no routing: the box keeps the routes it had.
    if ($rooms_only) {
        $routes = array('ok' => true, 'error' => '', 'routes' => ws_mail_routes($box));
    }

    if (!$routes['ok']) {
        return $fail($routes['error'], 'route_match');
    }

    // A plain box keeps the target and the automatic answer it had, hidden
    // and unchecked, for the day it is a support box again.
    $support = !empty($post['support']);
    $auto = $support ? ws_mail_auto_reply_input($post)
        : array('ok' => true, 'error' => '', 'auto_reply' => (int) ($box['auto_reply'] ?? 0), 'auto_reply_text' => (string) ($box['auto_reply_text'] ?? ''));

    if (!$auto['ok']) {
        return $fail($auto['error'], 'auto_reply_text');
    }

    $first = $support ? ws_mail_first_reply_input($post) : array('ok' => true, 'error' => '', 'hours' => ws_mail_first_reply_hours($box, true));

    if (!$first['ok']) {
        return $fail($first['error'], 'first_reply_hours');
    }

    $settings = ws_mail_box_settings($box);
    $settings['routes'] = $routes['routes'];
    $settings['support'] = $support;
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

    foreach (array('auth_server', 'provider', 'host', 'port', 'user', 'move_folder', 'smtp_host', 'smtp_port', 'sent_copy', 'sent_folder', 'read_sent', 'connection_id', 'reads_bounces', 'signature') as $key) {
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

    // The Sent folder's position starts again when reading it stops or
    // another folder is named, so the next first read begins at the present.
    if ($box && ws_mail_sent_position_resets(ws_mail_box_settings($box), $settings) && pg_schema_has('ws_mail_boxes', 'sent_last_uid')) {
        $set .= ", sent_uidvalidity = 0, sent_last_uid = 0";
    }

    if ($box) {
        db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . (int) $box['id'] . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was changed', 'vars' => $values['name'])), $username);
    } else {
        db("INSERT INTO ws_mail_boxes SET " . $set . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was added', 'vars' => $values['name'])), $username);
    }
}

/**
 * Whether a box's save forgets the Sent folder's position: reading it is
 * off now, or it was on and another Sent folder is named. Pure.
 *
 * @param array $before the box's settings as stored
 * @param array $after  the settings being saved
 * @return bool
 */
function ws_mail_sent_position_resets($before, $after)
{
    if (empty($after['read_sent'])) {
        return true;
    }

    return !empty($before['read_sent']) && ((string) ($before['sent_folder'] ?? '') !== (string) ($after['sent_folder'] ?? ''));
}

/**
 * Whether a mailbox may be removed by this person, before anything is
 * written: the Workspace settings right, a box that exists, and not a
 * WhatsApp or SMS account's box (kept by its own card). Pure.
 *
 * @param array      $viewer
 * @param array|null $box ws_mail_box()
 * @return array ok, error
 */
function ws_mail_box_remove_check($viewer, $box)
{
    if (empty($viewer['settings'])) {
        return array('ok' => false, 'error' => lang('You do not have access to Workspace settings.'));
    }

    if (!is_array($box) || ((int) ($box['id'] ?? 0) < 1) || ws_mail_box_is_phone($box)) {
        return array('ok' => false, 'error' => lang('That mailbox could not be found.'));
    }

    return array('ok' => true, 'error' => '');
}

/**
 * What removing a mailbox writes. The box's row goes, and with it
 * everything kept in its settings: the stored password (sealed there),
 * the incoming mail rules, the routes, the reading position; its own
 * blocked senders go too. A conversation, a mail, its card in the channel
 * and a box's room (kept in the mailbox itself) are never touched: they
 * stay where they are. Pure.
 *
 * @param int $box_id
 * @return string[] SQL
 */
function ws_mail_box_remove_queries($box_id)
{
    $box_id = (int) $box_id;

    return array(
        "DELETE FROM ws_mail_blocks WHERE box_id = '" . $box_id . "'",
        "DELETE FROM ws_mail_boxes WHERE id = '" . $box_id . "'",
    );
}

/**
 * Remove a mailbox checked by ws_mail_box_remove_check(): reading stops at
 * once, as the row the fetch reads is gone. A Site Log line names it.
 *
 * @param array  $box
 * @param string $username
 */
function ws_mail_box_remove_write($box, $username)
{
    foreach (ws_mail_box_remove_queries((int) $box['id']) as $sql) {
        // The block list may predate its table's step.
        if ((strpos($sql, 'ws_mail_blocks') !== false) && function_exists('ws_mail_blocks_ready') && !ws_mail_blocks_ready()) {
            continue;
        }

        db($sql);
    }

    log_activity(lang(array('string' => 'the mailbox {var:1} was removed', 'vars' => (string) $box['name'])), $username);
}

/**
 * What Remove's confirm says will happen, in the person's words.
 *
 * @param array $box
 * @return string
 */
function ws_mail_box_remove_confirm($box)
{
    return lang(array(
        'string' => 'Remove the mailbox {var:1}? Reading stops, the stored password is deleted and its incoming mail rules go. The conversations stay where they are.',
        'vars'   => (string) $box['name'],
    ));
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
 * @param bool       $rooms_only ws_mail_box_rooms_only(): kept in the mailbox
 *                             itself, so no channel, routing or suggestion
 *                             under a card is offered
 * @param array      $google   ws_mail_google_parts(): the Gmail part's Google
 *                             account state and redirect addresses
 * @return string
 */
function ws_mail_box_form($self_url, $box, $channels, $ai_ready = false, $form = null, $rooms_only = false, $google = array())
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
                    <label class="pg-field-label" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="pg-input" type="text" name="name" id="' . $prefix . 'name" maxlength="100" required value="' . h($box ? (string) $box['name'] : '') . '" placeholder="' . h(lang('Support')) . '">
                </div>
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'address">' . h(lang('E-mail address')) . '</label>
                    <input class="pg-input" type="email" name="address" id="' . $prefix . 'address" maxlength="255" required value="' . h($box ? (string) $box['address'] : '') . '" placeholder="support@example.com">
                </div>
                ' . ws_mail_room_fields($prefix, $box, $options, $rooms_only) . '
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'auth">' . h(lang('Checking server')) . '</label>
                    <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="auth_server" id="' . $prefix . 'auth" maxlength="255" value="' . h((string) ($settings['auth_server'] ?? '')) . '" placeholder="mx.google.com" autocomplete="off">
                    <div class="pg-field-help">' . h(lang('The server that receives this mailbox\'s mail and writes Authentication-Results. Only its verdict is believed; left empty, every sender stays unverified and no mail is tied to a customer.')) . '</div>
                </div>
                ' . ($rooms_only ? '' : ws_mail_route_fields($prefix, $box, $channels)) . '
                ' . ws_mail_box_server_fields($prefix, $box, $settings, $google) . '
                <div class="md:col-12">
                    <label class="pg-field-label" for="' . $prefix . 'signature">' . h(lang('Signature')) . '</label>
                    <textarea class="pg-input" name="signature" id="' . $prefix . 'signature" rows="3" maxlength="' . WS_MAIL_SIGNATURE_MAX . '">' . h(ws_mail_box_signature($box)) . '</textarea>
                    <div class="pg-field-help">' . h(lang('Plain text put under a new e-mail sent from this mailbox, after the writer\'s name. Left empty, the mailbox\'s name is put there.')) . '</div>
                </div>
                ' . ws_mail_support_field($prefix, $box) . '
                <div class="md:col-12' . (ws_mail_box_is_support($box) ? '' : ' d-none') . '" data-ws-mail-when="support=1">
                    <div class="row g-5">
                    ' . ws_mail_first_reply_field($prefix, $box) . '
                    ' . ws_mail_auto_reply_fields($prefix, $box) . '
                    </div>
                </div>
                ' . ws_mail_triage_field($prefix, $box, $ai_ready && !$rooms_only) . '
                <div class="md:col-12">
                    <div class="d-flex align-items-center gap-3">
                        <input class="pg-checkbox" type="checkbox" name="reads_bounces" value="1" id="' . $prefix . 'reads_bounces"' . (!empty($settings['reads_bounces']) ? ' checked' : '') . '>
                        <label for="' . $prefix . 'reads_bounces">' . h(lang('Reads bounces')) . '</label>
                    </div>
                    <div class="pg-field-help">' . h(lang('Queued and campaign e-mails are sent with this address as their return path, written with a + and a code after its name. Delivery failures that come back are recorded and leave the channel; an address that keeps failing gets no more mail. The mail server must deliver such addresses to this mailbox, and the site\'s sending address must be on the same domain.')) . '</div>
                </div>
                <div class="md:col-12">
                    <div class="d-flex align-items-center gap-3">
                        <input class="pg-switch" type="checkbox" name="enabled" value="1" id="' . $prefix . 'enabled"' . ((!$box || (int) $box['enabled'] === 1) ? ' checked' : '') . '>
                        <label for="' . $prefix . 'enabled">' . h(lang('On')) . '</label>
                    </div>
                </div>
            </div>';
    $save = h(lang('Save'));

    if ($form !== null) {
        return '
        <div class="ws-mail-box-form" data-ws-mail-dialog>' . ws_mail_box_dialog_names($fields, $id, $form) . '
            <button type="submit" name="ws_mail_box_save" value="' . $id . '" form="' . h($form) . '" class="pg-button pg-button-fill mt-5">' . $save . '</button>
        </div>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-mail" class="py-5 ws-mail-box-form">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mail_box_save">
            <input type="hidden" name="box_id" value="' . $id . '">
            ' . $fields . '
            <button type="submit" class="pg-button pg-button-fill mt-5">' . $save . '</button>
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
 * @param array      $google   ws_mail_google_parts()
 * @return string
 */
function ws_mail_box_server_fields($prefix, $box, $settings, $google = array())
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
                <div class="md:col-12"><h3 class="pg-group-label mt-3 mb-0">' . h(lang('Reading the mailbox')) . '</h3>
                    <div class="pg-field-help">' . h(lang('Leave the server empty to bring mail in only as .eml files. A chosen service fills an empty server, checking server and sending server.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="pg-field-label" for="' . $prefix . 'provider">' . h(lang('Mail service')) . '</label>
                    <select class="pg-input" name="provider" id="' . $prefix . 'provider">' . $options . '</select>
                </div>
                <div class="md:col-5">
                    <label class="pg-field-label" for="' . $prefix . 'host">' . h(lang('IMAP server')) . '</label>
                    <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="host" id="' . $prefix . 'host" maxlength="253" value="' . h((string) ($settings['host'] ?? '')) . '" placeholder="imap.example.com" autocomplete="off">
                </div>
                <div class="md:col-3">
                    <label class="pg-field-label" for="' . $prefix . 'port">' . h(lang('Port')) . '</label>
                    <input class="pg-input" type="number" name="port" id="' . $prefix . 'port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['port'] ?? 0, $provider, 'port') . '">
                </div>
                ' . ws_mail_google_steps($provider, $prefix, ws_mail_google_way($settings), $google) . '
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'user">' . h(lang('User name')) . '</label>
                    <input class="pg-input" type="text" spellcheck="false" autocapitalize="off" name="user" id="' . $prefix . 'user" maxlength="255" value="' . h((string) ($settings['user'] ?? '')) . '" autocomplete="off">
                </div>
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'password">' . h(lang('Password')) . '</label>
                    <input class="pg-input" type="password" name="password" id="' . $prefix . 'password" value="" autocomplete="new-password" placeholder="' . h($kept ? lang('Stored. Type a new one to replace it.') : '') . '">
                </div>
                <div class="md:col-12">
                    <label class="pg-field-label" for="' . $prefix . 'connection">' . h(lang('Connected account')) . '</label>
                    <select class="pg-input" name="connection_id" id="' . $prefix . 'connection"' . (($via === 'connection') ? ' required' : '') . '>' . $accounts . '</select>
                    <div class="pg-field-help">' . h(lang('Microsoft 365 and Outlook.com sign in with a connected account instead of a password; Gmail can too.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="pg-field-label" for="' . $prefix . 'folder">' . h(lang('Folder to read')) . '</label>
                    <input class="pg-input" type="text" name="folder" id="' . $prefix . 'folder" maxlength="255" value="' . h($box ? (string) $box['folder'] : 'INBOX') . '">
                </div>
                <div class="md:col-4">
                    <label class="pg-field-label" for="' . $prefix . 'after">' . h(lang('After reading')) . '</label>
                    <select class="pg-input" name="after_fetch" id="' . $prefix . 'after">
                        <option value="seen"' . ($move ? '' : ' selected') . '>' . h(lang('Mark as read')) . '</option>
                        <option value="move"' . ($move ? ' selected' : '') . '>' . h(lang('Move to a folder')) . '</option>
                    </select>
                </div>
                <div class="md:col-4' . ($move ? '' : ' d-none') . '" data-ws-mail-when="after_fetch=move">
                    <label class="pg-field-label" for="' . $prefix . 'move">' . h(lang('Folder to move to')) . '</label>
                    <input class="pg-input" type="text" name="move_folder" id="' . $prefix . 'move" maxlength="255" value="' . h((string) ($settings['move_folder'] ?? '')) . '" placeholder="' . h(lang('Processed')) . '">
                </div>
                <div class="md:col-12"><h3 class="pg-group-label mt-3 mb-0">' . h(lang('Sending answers')) . '</h3>
                    <div class="pg-field-help">' . h(lang('Answers leave from the mailbox\'s address. Its own sending server signs in with the user name and password above; the site\'s mail settings send only for an address on the site\'s own mail domain.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="pg-field-label" for="' . $prefix . 'reply_via">' . h(lang('Send through')) . '</label>
                    <select class="pg-input" name="reply_via" id="' . $prefix . 'reply_via">
                        <option value="box"' . (($via === 'box') ? ' selected' : '') . '>' . h(lang('The mailbox\'s sending server')) . '</option>
                        <option value="site"' . (($via === 'site') ? ' selected' : '') . '>' . h(lang('The site\'s mail settings')) . '</option>
                        <option value="connection"' . (($via === 'connection') ? ' selected' : '') . '>' . h(lang('The connected account')) . '</option>
                    </select>
                </div>
                <div class="md:col-5' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="pg-field-label" for="' . $prefix . 'smtp_host">' . h(lang('SMTP server')) . '</label>
                    <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="smtp_host" id="' . $prefix . 'smtp_host" maxlength="253" value="' . h((string) ($settings['smtp_host'] ?? '')) . '" placeholder="smtp.example.com" autocomplete="off">
                </div>
                <div class="md:col-3' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="pg-field-label" for="' . $prefix . 'smtp_port">' . h(lang('Port')) . '</label>
                    <input class="pg-input" type="number" name="smtp_port" id="' . $prefix . 'smtp_port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['smtp_port'] ?? 0, $provider, 'smtp_port') . '">
                </div>
                <div class="md:col-8">
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <input class="pg-checkbox" type="checkbox" name="sent_copy" value="1" id="' . $prefix . 'sent_copy"' . ($copy ? ' checked' : '') . '>
                        <label for="' . $prefix . 'sent_copy">' . h(lang('Keep a copy of each answer in the mailbox\'s sent folder')) . '</label>
                    </div>
                    <div class="pg-field-help">' . h(lang('Needs the IMAP server above. Gmail and Microsoft 365 keep what their own sending server sends, so no copy is added for them.')) . '</div>
                </div>
                <div class="md:col-4">
                    <label class="pg-field-label" for="' . $prefix . 'sent_folder">' . h(lang('Sent folder')) . '</label>
                    <input class="pg-input" type="text" name="sent_folder" id="' . $prefix . 'sent_folder" maxlength="255" value="' . h((string) ($settings['sent_folder'] ?? '')) . '" placeholder="' . h(lang('Found by itself')) . '">
                </div>
                <div class="md:col-12">
                    <div class="d-flex align-items-center gap-3">
                        <input class="pg-checkbox" type="checkbox" name="read_sent" value="1" id="' . $prefix . 'read_sent"' . (!empty($settings['read_sent']) ? ' checked' : '') . '>
                        <label for="' . $prefix . 'read_sent">' . h(lang('Read the Sent folder')) . '</label>
                    </div>
                    <div class="pg-field-help">' . h(lang('An answer written in a mail program joins its conversation as a sent mail. Only mail sent after this is switched on is read; a mail that answers no conversation is left out.')) . '</div>
                </div>
                <div class="md:col-12">
                    <details class="small fg-secondary"><summary>' . h(lang('What each service needs')) . '</summary><ul class="mb-0 mt-2">' . $hints . '</ul></details>
                </div>';
}

/**
 * Which way a Gmail box's form opens on: the connected account for a box
 * that has one or keeps no password (a new box), the app password for a
 * box that keeps one, so a box working today shows what it uses. Pure.
 *
 * @param array $settings ws_mail_box_settings()
 * @return string connection | password
 */
function ws_mail_google_way($settings)
{
    if ((int) ($settings['connection_id'] ?? 0) > 0) {
        return 'connection';
    }

    return !empty($settings['secret']) ? 'password' : 'connection';
}

/**
 * The ways a Gmail or Google Workspace mailbox signs in, shown while that
 * service is chosen: a connected Google account (purpose mail_read, IMAP
 * with XOAUTH2, answers through the Gmail API), with the Google
 * application's redirect addresses, the API to enable and Google's
 * verification warning; or an app password, the account's own password
 * being refused. "Sign in with" only shows one of them and is not stored.
 * Pure but for the words.
 *
 * @param string $provider the box's ws_mail_providers() key
 * @param string $prefix   the form's id prefix
 * @param string $way      ws_mail_google_way()
 * @param array  $google   ws_mail_google_parts()
 * @return string HTML
 */
function ws_mail_google_steps($provider, $prefix = 'ws_mail_0_', $way = 'connection', $google = array())
{
    $link = function ($url, $text) {
        return '<a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($text) . '</a>';
    };
    $way = ($way === 'password') ? 'password' : 'connection';
    $connected = array(
        h(lang('Connect the mailbox\'s own Google account on Google\'s page in Connections (the link below opens it in a new tab); Google asks the account to allow reading and sending its mail.')),
        h(lang('Choose that account as the Connected account below. The user name is the mailbox\'s address; no password is typed.')),
        h(lang('Under Sending answers, choose The connected account: answers leave from the Google account through the Gmail API.')),
        h(lang('Save, then press Test connection on the mailbox\'s row.')),
    );
    $steps = array(
        h(lang('Use the mailbox\'s own Google account: the address typed above.')),
        lang(array('string' => 'Turn on 2-Step Verification for that account in {var:1}.', 'vars' => $link('https://myaccount.google.com/security', lang('Google Account › Security')))),
        lang(array('string' => 'Open {var:1}, type a name such as the site\'s name and create the password. Google shows a 16-character app password.', 'vars' => $link('https://myaccount.google.com/apppasswords', lang('App passwords')))),
        h(lang('Here, keep Gmail or Google Workspace as the mail service: it fills the empty IMAP, sending and checking servers.')),
        h(lang('Type the full address as the user name and the app password, without its spaces, as the password. Leave the connected account at None.')),
        h(lang('Save, then press Test connection on the mailbox\'s row.')),
    );
    $notes = array(
        lang('Nothing is turned on in Gmail itself: Google keeps IMAP access on for every account since January 2025.'),
        lang('App passwords are not offered when 2-Step Verification uses only security keys, when the account has Advanced Protection, or for a work or school account whose organisation does not allow them: ask its administrator.'),
        lang('When the account\'s Google password changes, Google revokes its app passwords: create a new one and type it here.'),
    );
    $warning = pg_google_cloud_verification_notes('gmail');
    $addresses = '';

    foreach (array_values((array) ($google['addresses'] ?? array())) as $index => $address) {
        $id = $prefix . 'google_redirect_' . ($index + 1);
        $addresses .= '
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <input type="text" class="pg-input font-monospace flex-grow-1 min-w-0" id="' . h($id) . '" value="' . h((string) $address) . '" readonly aria-label="' . h(lang('Redirect address')) . '">
                                    <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-copy="' . h($id) . '"><i class="bi bi-clipboard" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
                                </div>';
    }

    if ($addresses !== '') {
        $addresses = '
                                <p class="pg-text-2 mt-3 mb-0">' . h(lang('Add both addresses to the application\'s OAuth client in Google Cloud, under Authorized redirect URIs:')) . '</p>' . $addresses . '
                                <div class="pg-field-help" role="status" aria-live="polite" data-ws-mail-copied="' . h(lang('Copied')) . '" data-ws-mail-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></div>';
    }

    return '
                <div class="md:col-12' . (($provider === 'gmail') ? '' : ' d-none') . '" data-ws-mail-when="provider=gmail">
                    <div class="pg-card-inner p-3">
                        <h4 class="pg-group-label mb-2">' . h(lang('Connecting a Gmail or Google Workspace mailbox')) . '</h4>
                        <label class="pg-field-label" for="' . h($prefix) . 'google_way">' . h(lang('Sign in with')) . '</label>
                        <select class="pg-input" name="google_way" id="' . h($prefix) . 'google_way">
                            <option value="connection"' . (($way === 'connection') ? ' selected' : '') . '>' . h(lang('A connected Google account')) . '</option>
                            <option value="password"' . (($way === 'password') ? ' selected' : '') . '>' . h(lang('An app password')) . '</option>
                        </select>
                        <div class="mt-3' . (($way === 'connection') ? '' : ' d-none') . '" data-ws-mail-when="google_way=connection">
                            <ol class="pg-text-2 mb-2 ps-4"><li>' . implode('</li><li>', $connected) . '</li></ol>
                            ' . (string) ($google['connect'] ?? '') . '
                            <details class="mt-3"><summary class="pg-text-2">' . h(lang('Setting up the Google application')) . '</summary>' . $addresses . '
                                <p class="pg-text-2 mt-3 mb-0">' . lang(array('string' => 'Turn on the Gmail API in the same Google Cloud project; answers are sent through it: {var:1}.', 'vars' => $link(pg_google_cloud_enable_url('gmail.googleapis.com'), lang('Gmail API')))) . '</p>
                                <h5 class="pg-group-label mt-3 mb-2">' . h(lang('Google\'s verification warning')) . '</h5>
                                <ul class="pg-field-help mb-0 ps-4"><li>' . implode('</li><li>', array_map('h', $warning)) . '</li></ul>
                            </details>
                        </div>
                        <div class="mt-3' . (($way === 'password') ? '' : ' d-none') . '" data-ws-mail-when="google_way=password">
                            <ol class="pg-text-2 mb-2 ps-4"><li>' . implode('</li><li>', $steps) . '</li></ol>
                            <ul class="pg-field-help mb-0 ps-4"><li>' . implode('</li><li>', array_map('h', $notes)) . '</li></ul>
                        </div>
                    </div>
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
 * and the .eml upload. In Site Settings (Mailboxes, with $form) the pane is
 * folded rows (ws_mail_settings_rows(), pg_settings_fold()): Test connection
 * is a named submit button of the settings form (ws_mail_test, value the
 * box's id), and a box's form and Add a mailbox open in drawers
 * (ws_settings_mail_drawer()). includes/settings/fold.php must be loaded.
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
    $workspace_on = ws_enabled();
    $rows = '';
    $boxes = ws_mail_settings_boxes();

    foreach ($boxes as $box) {
        $id = (int) $box['id'];
        $channel = $channels[(int) $box['channel_id']] ?? null;
        $place = $channel ? '<a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h((string) $channel['name']) . '</a>' : h(lang('A channel you do not see'));

        // With the Workspace off no channel opens: its name only.
        if ($channel && !$workspace_on) {
            $place = h('#' . (string) $channel['name']);
        }

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
        $test = '<i class="bi bi-plug" aria-hidden="true"></i>' . h(lang('Test connection'));

        // In Site Settings each box is a list row of the pane's Mailboxes
        // row (ws_mail_settings_box_row()).
        if ($form !== null) {
            $rows .= ws_mail_settings_box_row($box, $place, $form);
            continue;
        }

        $rows .= '
            <div class="ws-dept-row' . ((int) $box['enabled'] === 1 ? '' : ' opacity-50') . '">' . $line . '
                ' . ($reads ? '
                <form method="post" action="' . h($self_url) . '#ws-mail" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mail_test">
                    <input type="hidden" name="box_id" value="' . $id . '">
                    <button type="submit" class="pg-button pg-button-quiet pg-button-sm">' . $test . '</button>
                </form>' : '') . '
                <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="collapse" data-bs-target="#ws-mail-' . $id . '" aria-expanded="false"><i class="bi bi-pencil" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            </div>
            <div class="collapse" id="ws-mail-' . $id . '">
                ' . ws_mail_box_form($self_url, $box, $channels, $ai_ready) . '
                ' . ws_mail_eml_form($self_url . '#ws-mail', $id, false) . '
            </div>';
    }

    if ($rows === '') {
        $rows = '<div class="ws-empty small py-5"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('No mailboxes yet. A mailbox brings the e-mail sent to an address into a channel, where the team talks it over.')) . '</div>';
    }

    $intro = '<div class="pg-field-help mt-5">' . h(lang('A mailbox with a server is read every minute while the general scheduled job runs; an .eml file saved from a mail program can also be brought in by hand.')) . '</div>';

    if ($form !== null) {
        // The list's rows, or its empty state (the Design examples' "A
        // list's empty state").
        $rows = $boxes ? '<div class="pg-card-inner ws-agset-list">' . $rows . '</div>'
            : '<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-inbox" aria-hidden="true"></i>' . h(lang('No mailbox yet.')) . '</div></div>';

        return pg_settings_fold(ws_mail_settings_rows($viewer, $boxes, $rows, $form)) . ws_mail_settings_script();
    }

    return '
        <div class="card mt-7" id="ws-mail">
            <div class="card-header d-flex align-items-center gap-3">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . h(lang('Mailboxes')) . '</h2>
                <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="collapse" data-bs-target="#ws-mail-new" aria-expanded="false"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                <div class="collapse" id="ws-mail-new">' . ws_mail_box_form($self_url, null, $channels, $ai_ready) . '</div>
                ' . $rows . '
                ' . $intro . '
                ' . ws_mail_connections_html($viewer) . '
            </div>
        </div>' . ws_mail_settings_script();
}

/**
 * A box's state for its pill: Off (switched off), Sign-in needed (the
 * password or the connected account is refused or gone), Error (the last
 * check failed otherwise), Reading. A box with no server is read from
 * nothing: no pill, its line says so. Pure.
 *
 * @param array $box ws_mail_boxes row
 * @return array key (off|sign_in|error|reading|none), words, tone (a
 *               pg-tone- class), error (the failed check's first sentence)
 */
function ws_mail_box_state($box)
{
    $settings = ws_mail_box_settings($box);

    if (empty($box['enabled'])) {
        return array('key' => 'off', 'words' => lang('Off'), 'tone' => 'pg-tone-gray', 'error' => '');
    }

    if ((string) ($settings['host'] ?? '') === '') {
        return array('key' => 'none', 'words' => '', 'tone' => '', 'error' => '');
    }

    $parts = explode('|', (string) ($box['last_error'] ?? ''), 2);
    $error = (string) $parts[0];

    if ($error === '') {
        return array('key' => 'reading', 'words' => lang('Reading'), 'tone' => 'pg-tone-teal', 'error' => '');
    }

    // The code's own sentence, without what the server added after it.
    $first = ws_mail_error_text($error, '', '');

    if (in_array($error, array('auth', 'auth_disabled', 'no_password', 'no_account'), true)) {
        return array('key' => 'sign_in', 'words' => lang('Sign-in needed'), 'tone' => 'pg-tone-amber', 'error' => $first);
    }

    return array('key' => 'error', 'words' => lang('Error'), 'tone' => 'pg-tone-rose', 'error' => $first);
}

/**
 * The line under a box's name: where it is kept, then its last check (with
 * the failed check's first sentence). Pure but for the words.
 *
 * @param array  $box
 * @param string $place HTML: the channel's link or "Kept in the mailbox itself"
 * @param array  $state ws_mail_box_state()
 * @return string HTML
 */
function ws_mail_box_row_line($box, $place, $state)
{
    $settings = ws_mail_box_settings($box);

    if ((string) ($settings['host'] ?? '') === '') {
        $check = h(lang('Not read from a server'));
    } elseif ((int) ($box['checked_at'] ?? 0) === 0) {
        $check = h(lang('Not checked yet'));
    } else {
        $check = h(lang(array('string' => 'Checked {var:1}', 'vars' => date('d.m.Y H:i', (int) $box['checked_at']))))
            . (($state['error'] !== '') ? ': ' . h($state['error']) : '');
    }

    return $place . ' · ' . $check;
}

/**
 * A box's row in Site Settings › Mailboxes, the settings pane's list row of
 * the Design examples screen: icon tile, name and address, where it is kept
 * and the last check, the state pill, then Test connection (a box read from
 * a server) and Edit, quiet and small.
 *
 * @param array  $box
 * @param string $place HTML
 * @param string $form  the settings form's id
 * @return string HTML
 */
function ws_mail_settings_box_row($box, $place, $form)
{
    $id = (int) $box['id'];
    $state = ws_mail_box_state($box);
    $test = ($state['key'] !== 'none' && $state['key'] !== 'off')
        ? '<button type="submit" name="ws_mail_test" value="' . $id . '" form="' . h($form) . '" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plug" aria-hidden="true"></i>' . h(lang('Test connection')) . '</button>'
        : '';

    return '<div class="ws-agset-row" data-ws-mail-box="' . $id . '">'
        . '<span class="pg-icon-tile ws-agset-face" aria-hidden="true"><i class="bi bi-inbox"></i></span>'
        . '<div class="ws-agset-main">'
        . '<div class="ws-agset-name"><strong class="pg-list-row-title">' . h((string) $box['name']) . '</strong><span class="pg-text-meta">' . h((string) $box['address']) . '</span></div>'
        . '<div class="pg-text-meta ws-agset-summary">' . ws_mail_box_row_line($box, $place, $state) . '</div>'
        . '</div>'
        . (($state['words'] !== '') ? '<span class="pg-pill ws-agset-state ' . h($state['tone']) . '">' . h($state['words']) . '</span>' : '')
        . '<div class="ws-agset-edit d-flex flex-wrap align-items-center gap-1">' . $test
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-' . $id . '" aria-controls="pgset-ws-mail-' . $id . '"><i class="bi bi-pencil" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>'
        . '</div></div>';
}

/**
 * The Mailboxes row's line and pill: how many boxes, and the state that
 * most needs a look (Error, then Sign-in needed), else Reading while one
 * reads, else Off when all are off. Pure but for the words.
 *
 * @param array[] $boxes
 * @return array line, pill (words, tone class; or null), attention
 */
function ws_mail_settings_boxes_face($boxes)
{
    $keys = array();

    foreach ((array) $boxes as $box) {
        $keys[] = ws_mail_box_state($box)['key'];
    }

    $count = count($keys);

    if ($count === 0) {
        return array('line' => lang('No mailbox yet.'), 'pill' => null, 'attention' => false);
    }

    $line = ($count === 1) ? lang('1 mailbox') : lang(array('string' => '{var:1} mailboxes', 'vars' => $count));

    if (in_array('error', $keys, true)) {
        return array('line' => $line, 'pill' => array(lang('Error'), 'pg-tone-rose'), 'attention' => true);
    }

    if (in_array('sign_in', $keys, true)) {
        return array('line' => $line, 'pill' => array(lang('Sign-in needed'), 'pg-tone-amber'), 'attention' => true);
    }

    if (in_array('reading', $keys, true)) {
        return array('line' => $line, 'pill' => array(lang('Reading'), 'pg-tone-teal'), 'attention' => false);
    }

    return array('line' => $line, 'pill' => (count(array_unique($keys)) === 1 && $keys[0] === 'off') ? array(lang('Off'), 'pg-tone-gray') : null, 'attention' => false);
}

/**
 * Google's or Microsoft's connect row under "Accounts for mailboxes", the
 * Design examples' connect row: the state in the words Connections uses
 * (ws_mail_connection_html()), Connect after Not connected. A person below
 * manager cannot open Connections: no link, the line says who connects it.
 *
 * @param array     $viewer
 * @param string    $provider microsoft | google
 * @param array|int $state    ws_mail_connection_state()
 * @param bool      $ready    ws_mail_connect_ready()
 * @return string HTML
 */
function ws_mail_account_row($viewer, $provider, $state, $ready)
{
    $google = ($provider === 'google');
    $state = is_array($state) ? $state : array('count' => (int) $state, 'other' => array());
    $connected = ((int) ($state['count'] ?? 0) > 0);
    $other = (!$connected && !empty($state['other'])) ? (array) $state['other'] : array();

    if ($connected) {
        $pill = array('teal', lang('Connected'));
        $button = lang('Manage');
        $line = lang('Reading mailboxes');
    } elseif ($other) {
        $pill = array('amber', lang('Connected for another purpose'));
        $button = lang('Connect');
        $line = lang(array(
            'string' => 'The account is connected for {var:1} only; reading mail needs one more sign-in, for {var:2}.',
            'vars'   => array(ws_mail_connection_purpose_names($other), lang('Reading mailboxes'))));
    } else {
        $pill = array('gray', lang('Not connected'));
        $button = lang('Connect');
        $line = $ready ? ($google ? lang('Gmail or Google Workspace') : lang('Microsoft 365 or Outlook.com'))
            : ($google ? lang('The Google application is not set up yet.') : lang('The Microsoft application is not set up yet.'));
    }

    $manager = ((int) ($viewer['role'] ?? 3) <= 2);

    if (!$manager && !$connected) {
        $line = $google
            ? lang('A Gmail mailbox can be read through a connected account; a manager or an administrator connects it here or in Settings › Connections.')
            : lang('A Microsoft 365 mailbox is read through a connected account; a manager or an administrator connects it here or in Settings › Connections.');
    }

    $inner = '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-' . ($google ? 'google' : 'microsoft') . '"></i></span>'
        . '<span class="connections-row-main"><span class="connections-row-name">' . ($google ? 'Google' : 'Microsoft') . '</span><span class="pg-text-meta d-block">' . h($line) . '</span></span>'
        . '<span class="connections-row-side"><span class="pg-pill pg-tone-' . $pill[0] . '">' . h($pill[1]) . '</span>'
        . ($manager ? '<span class="pg-button pg-button-sm pg-button-quiet">' . h($button) . '</span>' : '') . '</span>';

    return $manager
        ? '<a class="pg-card-inner-row connections-row" href="' . h(ws_mail_connections_url($google ? 'google' : 'microsoft')) . '">' . $inner . '</a>'
        : '<div class="pg-card-inner-row connections-row">' . $inner . '</div>';
}

/**
 * Bring in an .eml file as the pane's last row: the mailbox, the file and
 * Bring in. A file cannot travel with the pane's save, so the fields belong
 * to a form of their own printed outside the settings form
 * (ws_mail_eml_pane_form()); the save module reads ws_mail_eml (the box's
 * id) and the file ws_mail_eml_file.
 *
 * @param array[] $boxes
 * @return string HTML
 */
function ws_mail_eml_pane($boxes)
{
    if (!$boxes) {
        return '<p class="pg-text-meta">' . h(lang('Add a mailbox first.')) . '</p>';
    }

    $f = ' form="pgset-ws-mail-eml-form"';
    $choices = '';

    foreach ($boxes as $box) {
        $choices .= '<option value="' . (int) $box['id'] . '">' . h((string) $box['name']) . ' · ' . h((string) $box['address']) . '</option>';
    }

    return '<div id="pgset-ws-mail-eml" class="d-flex flex-wrap align-items-end gap-3">'
        . '<div><label class="pg-field-label" for="ws-mail-eml-box">' . h(lang('Mailbox')) . '</label><select class="pg-input" id="ws-mail-eml-box" name="ws_mail_eml"' . $f . '>' . $choices . '</select></div>'
        . '<div><label class="pg-field-label" for="ws-mail-eml-file">' . h(lang('E-mail file')) . '</label><input class="pg-input" type="file" id="ws-mail-eml-file" name="ws_mail_eml_file" accept=".eml,message/rfc822"' . $f . '></div>'
        . '<button type="submit" class="pg-button pg-button-quiet pg-button-sm"' . $f . '><i class="bi bi-upload" aria-hidden="true"></i>' . h(lang('Bring in')) . '</button>'
        . '</div>'
        . '<div class="pg-field-help">' . h(lang('An e-mail saved from a mail program comes in as if the mailbox had read it.')) . '</div>';
}

/**
 * The form Bring in an .eml file posts, empty and outside the settings form
 * (for $pg_settings_modals): its fields sit in the pane and name it.
 *
 * @return string HTML
 */
function ws_mail_eml_pane_form()
{
    return '
            <form method="post" action="' . h(pg_settings_url('mailboxes')) . '" enctype="multipart/form-data" id="pgset-ws-mail-eml-form" class="disable_shortcut d-none">' . get_token_field() . '</form>';
}

/**
 * The folded rows of Site Settings › Mailboxes, in reading order:
 * Mailboxes (open unless another needs a look), Accounts for mailboxes,
 * Incoming mail rules, Blocked senders, Bring in an .eml file.
 *
 * @param array   $viewer
 * @param array[] $boxes ws_mail_settings_boxes()
 * @param string  $list  HTML: the boxes' rows, or the empty state
 * @param string  $form  the settings form's id
 * @return array[] rows for pg_settings_fold()
 */
function ws_mail_settings_rows($viewer, $boxes, $list, $form)
{
    $rows = array();

    $rows[] = array('key' => 'ws-mail', 'name' => lang('Mailboxes'), 'icon' => 'bi-inbox', 'tone' => 'pg-tone-team',
        'html' => '
    <div id="pgset-ws-mail" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="pg-title-section ws-grow">' . h(lang('Mailboxes')) . '</span>
                <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-0" aria-controls="pgset-ws-mail-0"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                ' . $list . '
                <div class="pg-field-help mt-3">' . h(lang('A mailbox with a server is read every minute while the general scheduled job runs.'))
                    . ' ' . lang(array('string' => 'For sending: {var:1}', 'vars' => '<a href="' . h(pg_settings_url('mail_sending')) . '">' . h(lang('E-mail sending')) . '</a>')) . '</div>
            </div>
        </div>
    </div>') + ws_mail_settings_boxes_face($boxes);

    if (function_exists('pg_connect_purpose') && pg_connect_purpose('mail_read')) {
        $accounts = '';
        $connected = 0;

        foreach (array('google', 'microsoft') as $provider) {
            $state = ws_mail_connection_read($provider);
            $connected += ((int) $state['count'] > 0) ? 1 : 0;
            $accounts .= ws_mail_account_row($viewer, $provider, $state, ws_mail_connect_ready($provider));
        }

        $rows[] = array('key' => 'ws-mail-accounts', 'name' => lang('Accounts for mailboxes'), 'icon' => 'bi-person-badge', 'tone' => 'pg-tone-blue',
            'line' => lang('Gmail and Microsoft 365 mailboxes are read through a connected account.'),
            'pill' => ($connected > 0) ? array(lang('Connected'), 'pg-tone-teal') : array(lang('Not connected'), 'pg-tone-gray'),
            'attention' => false,
            'html' => '<div class="pg-card-inner connections-list">' . $accounts . '</div>');
    }

    if (function_exists('ws_mail_rules_html')) {
        $count = 0;
        $support = false;

        foreach ($boxes as $box) {
            if (ws_mail_rule_box_ok($box)) {
                $support = true;
                $count += count(ws_mail_rules($box));
            }
        }

        $html = ws_mail_rules_html($boxes, $support ? ws_mail_rule_people() : array(), $form);
        $rows[] = array('key' => 'ws-mail-rules', 'name' => lang('Incoming mail rules'), 'icon' => 'bi-funnel', 'tone' => '',
            'line' => !$support ? lang('Rules work in a support box.') : (($count === 0) ? lang('No rules yet.') : (($count === 1) ? lang('1 rule') : lang(array('string' => '{var:1} rules', 'vars' => $count)))),
            'pill' => null, 'attention' => false,
            'html' => ($html !== '') ? $html : '<p class="pg-text-meta">' . h(lang('Rules work in a support box.')) . '</p>');
    }

    if (function_exists('ws_mail_blocks_html')) {
        $blocks = ws_mail_blocks_list();
        $html = ws_mail_blocks_html($blocks, $form);
        $count = count($blocks);
        $rows[] = array('key' => 'ws-mail-blocks', 'name' => lang('Blocked senders'), 'icon' => 'bi-slash-circle', 'tone' => '',
            'line' => ($count === 0) ? lang('No sender is blocked.') : (($count === 1) ? lang('1 blocked sender') : lang(array('string' => '{var:1} blocked senders', 'vars' => $count))),
            'pill' => null, 'attention' => false,
            'html' => ($html !== '') ? $html : '<p class="pg-text-meta">' . h(lang('No sender is blocked.')) . '</p>');
    }

    $rows[] = array('key' => 'ws-mail-eml', 'name' => lang('Bring in an .eml file'), 'icon' => 'bi-upload', 'tone' => '',
        'line' => lang('An e-mail saved from a mail program, into a mailbox.'), 'pill' => null, 'attention' => false,
        'html' => ws_mail_eml_pane($boxes));

    return $rows;
}

/**
 * The mailboxes, each with the time of its newest e-mail (last_mail_at).
 *
 * @return array[]
 */
function ws_mail_settings_boxes()
{
    // A WhatsApp or SMS account's box is kept by its own card, not here.
    return array_values(array_filter((array) db_items("SELECT b.*, (SELECT MAX(i.created_at) FROM ws_mail_items i WHERE i.box_id = b.id) AS last_mail_at
        FROM ws_mail_boxes b ORDER BY b.name"), function ($box) {
            return !ws_mail_box_is_phone($box);
        }));
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
                        <label class="pg-field-label" for="ws_mail_eml_' . (int) $id . '">' . h(lang('Bring in an .eml file')) . '</label>
                        <input class="pg-input" type="file" name="' . ($dialog ? 'ws_mail_eml_file' : 'eml') . '" id="ws_mail_eml_' . (int) $id . '" accept=".eml,message/rfc822">
                    </div>
                    <button type="submit"' . ($dialog ? ' name="ws_mail_eml" value="' . (int) $id . '"' : '') . ' class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-upload" aria-hidden="true"></i>' . h(lang('Bring in')) . '</button>
                </form>';
}

/**
 * A Site Settings drawer of the mailboxes card: a box's form or, with $box
 * null, Add a mailbox's. Bring in an .eml file is the pane's last row
 * (ws_mail_eml_pane()).
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
    $rooms_only = ws_mail_box_rooms_only($box, ws_enabled());

    if (!$box) {
        return pg_settings_drawer('pgset-ws-mail-0', h(lang('Add a mailbox')), ws_mail_box_form('', null, $channels, $ai_ready, $form, $rooms_only, ws_mail_google_parts($viewer)), $in_dialog);
    }

    $id = (int) $box['id'];

    return pg_settings_drawer('pgset-ws-mail-' . $id, h((string) $box['name']) . ' · ' . h((string) $box['address']),
        ws_mail_box_form('', $box, $channels, $ai_ready, $form, $rooms_only, ws_mail_google_parts($viewer)), $in_dialog);
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

            // A field that applies to one choice of a select, or to a
            // switch that is on, shows only with it
            // (data-ws-mail-when="name=value"); the connected account is
            // required when answers are sent through it.
            function sync(form) {
                form.querySelectorAll(\'[data-ws-mail-when]\').forEach(function (part) {
                    var rule = part.getAttribute(\'data-ws-mail-when\').split(\'=\');
                    var select = field(form, rule[0]);
                    var value = !select ? null : ((select.type === \'checkbox\') ? (select.checked ? select.value : \'\') : select.value);

                    part.classList.toggle(\'d-none\', value !== rule[1]);
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

            // A new box kept in the mailbox itself starts plain, one kept in
            // a channel as a support box, until the person sets the switch.
            function startPlain(form, target) {
                var support = field(form, \'support\');

                if (!support || !support.hasAttribute(\'data-ws-mail-new\')) {
                    return;
                }

                if (target === support) {
                    support.removeAttribute(\'data-ws-mail-new\');
                } else if (/(^|\\[)kept_in\\]?$/.test(target.name || \'\')) {
                    support.checked = (target.value !== \'mailbox\');
                }
            }

            document.addEventListener(\'change\', function (event) {
                var form = (event.target.closest && /(^|\\[)(after_fetch|reply_via|kept_in|support|provider|google_way)\\]?$/.test(event.target.name || \'\')) ? event.target.closest(\'.ws-mail-box-form\') : null;

                if (form) {
                    startPlain(form, event.target);
                    sync(form);
                }
            });

            // A redirect address of the Gmail part is copied; the line under the
            // addresses says whether it was. Plain http has no clipboard API
            // and a write can be refused: the text is then selected.
            document.addEventListener(\'click\', function (event) {
                var button = event.target.closest ? event.target.closest(\'[data-ws-mail-copy]\') : null;
                var input = button ? document.getElementById(button.getAttribute(\'data-ws-mail-copy\')) : null;
                var status = button ? button.closest(\'details\').querySelector(\'[data-ws-mail-copied]\') : null;

                if (!input || !status) {
                    return;
                }

                var done = function () {
                    status.textContent = status.getAttribute(\'data-ws-mail-copied\');
                };
                var fallback = function () {
                    var copied = false;

                    input.focus();
                    input.select();

                    try {
                        copied = document.execCommand(\'copy\');
                    } catch (error) {}

                    status.textContent = status.getAttribute(copied ? \'data-ws-mail-copied\' : \'data-ws-mail-refused\');
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(done, fallback);
                } else {
                    fallback();
                }
            });
        })();
        </script>';
}
