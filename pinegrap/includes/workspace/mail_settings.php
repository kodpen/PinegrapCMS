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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_add.php');

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
            'hint' => lang('Sign in with a connected Google account or an app password; Google refuses the account\'s own password.')),
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
            'hint' => lang('Signs in with a connected Microsoft account and answers through it; no password is typed.')),
        'other'  => array('label' => lang('Another server'), 'host' => '', 'port' => 993, 'smtp_host' => '', 'smtp_port' => 587, 'auth_server' => '',
            'hint' => lang('Any server that accepts IMAP over TLS, usually on port 993.')),
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
 * mailboxes, then the site's Google and Microsoft accounts that send its
 * e-mail, which ask their provider for reading once chosen
 * (pg_mail_box_account_asks()).
 *
 * @return array[] pg_connect_account() rows
 */
function ws_mail_box_accounts()
{
    if (!function_exists('pg_connect_accounts')) {
        return array();
    }

    $accounts = pg_connect_accounts(array('purpose' => 'mail_read'));

    foreach (pg_connect_accounts(array('user_id' => 0, 'purpose' => 'mail')) as $account) {
        if (pg_mail_box_account_asks($account)) {
            $accounts[] = $account;
        }
    }

    return $accounts;
}

/**
 * One account of a box's "Connected account" select. A sending account that
 * does not read yet says so and carries data-ws-mail-ask, which shows its
 * Allow reading mail (ws_mail_box_account_ask_html()). Pure.
 *
 * @param array $account       pg_connect_account()
 * @param int   $connection_id the box's account
 * @return string HTML
 */
function ws_mail_box_account_option($account, $connection_id)
{
    $id = (int) $account['id'];
    $asks = pg_mail_box_account_asks($account);

    return '<option value="' . $id . '"' . (($id === (int) $connection_id) ? ' selected' : '') . ($asks ? ' data-ws-mail-ask="' . $id . '"' : '') . '>'
        . h((string) $account['label']
            . ($asks ? ' (' . lang('sends the site\'s e-mail') . ')' : '')
            . (((string) $account['status'] !== 'active') ? ' (' . lang('connect again') . ')' : '')) . '</option>';
}

/**
 * Under the select, for a sending account that does not read yet: what
 * reading asks, and Allow reading mail, which asks the provider again for
 * the same account (pg_mail_connect_form(), printed by the Mailboxes tab).
 * Shown while the account is chosen. Pure.
 *
 * @param array $account     pg_connect_account()
 * @param bool  $may_connect the person may connect the site's accounts
 * @param bool  $shown       the account is the one chosen
 * @return string HTML
 */
function ws_mail_box_account_ask_html($account, $may_connect, $shown)
{
    $id = (int) $account['id'];
    $provider = ((string) $account['provider'] === 'google') ? 'Google' : 'Microsoft';

    return '
                    <div class="mt-3" data-ws-mail-ask-for="' . $id . '"' . ($shown ? '' : ' hidden') . '>
                        <div class="pg-field-help mt-0">' . h(lang(array(
                            'string' => '{var:1} sends the site\'s e-mail. To read this mailbox, {var:2} asks once more for the same account; it is not connected twice.',
                            'vars'   => array((string) $account['label'], $provider)))) . '</div>
                        ' . ($may_connect
                            ? '<div class="mt-3">' . pg_mail_connect_button('pgset_mail_read_' . $id, lang('Allow reading mail'), 'bi-envelope-open') . '</div>'
                            : '<div class="pg-text-meta">' . h(lang('A manager or an administrator allows it here.')) . '</div>') . '
                    </div>';
}

/**
 * The forms Allow reading mail submits, one a sending account that does
 * not read yet, printed outside the settings form by the Mailboxes tab.
 *
 * @param string $return the screen connect.php returns to
 * @return string HTML
 */
function ws_mail_box_account_ask_forms($return)
{
    $html = '';

    foreach (ws_mail_box_accounts() as $account) {
        if (pg_mail_box_account_asks($account)) {
            $html .= pg_mail_connect_form('pgset_mail_read_' . (int) $account['id'], (string) $account['provider'], 'mail_read', $return, (int) $account['id']);
        }
    }

    return $html;
}

/**
 * The address of a provider's page on Connections, at its Connect an
 * account for mailboxes action, returning to the Mailboxes card once the
 * account is connected (pg_connections_back()). Pure.
 *
 * @param string $provider microsoft | google
 * @return string
 */
function ws_mail_connections_url($provider)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

    return pg_connections_lead_url(($provider === 'google') ? 'google' : 'microsoft', 'email', 'pgset-ws-mail', 'connections-mail');
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
 * @param bool   $in_box   in a box's set-up drawer: no line on adding a box.
 *                         The page opens in the same tab; the drawer and the
 *                         box's unsaved fields come back with the way back
 *                         (pgSettingsLead() in backend.src.js)
 * @return string HTML
 */
function ws_mail_connection_html($viewer, $provider, $count, $ready, $in_box = false)
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
        $note = $in_box ? '' : lang(array(
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
        $side = '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h(ws_mail_connections_url($provider)) . '">'
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
 * What a provider's set-up drawer shows beside its words: the account's
 * state with a link to the provider's page on Connections, in the same tab:
 * the drawer and a half-filled box form come back with the way back
 * (ws_mail_connection_html()),
 * the addresses the application must list as redirect URIs (Sign in's and
 * connect.php's) and the provider's console.
 *
 * @param array  $viewer
 * @param string $provider google | microsoft
 * @return array connect (HTML), addresses (string[]), console (URL)
 */
function ws_mail_setup_parts($viewer, $provider)
{
    $provider = ($provider === 'google') ? 'google' : 'microsoft';
    $connect = (function_exists('pg_connect_purpose') && pg_connect_purpose('mail_read'))
        ? ws_mail_connection_html($viewer, $provider, ws_mail_connection_read($provider), ws_mail_connect_ready($provider), true)
        : '';
    $entry = function_exists('pg_connect_provider') ? pg_connect_provider($provider) : null;

    return array(
        'connect'   => $connect,
        'addresses' => pg_google_cloud_redirect_addresses($provider),
        'console'   => (is_array($entry) && !empty($entry['console_url'])) ? (string) $entry['console_url'] : '',
    );
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

        // A sending account reads once its provider allowed it on the same
        // account: Allow reading mail under the select asks.
        if (is_array($account) && pg_mail_box_account_asks($account)) {
            return $fail(lang(array('string' => '{var:1} does not read mail yet: press Allow reading mail under it, then save.', 'vars' => (string) $account['label'])), 'connection_id');
        }

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
 * Whether a mailbox may be removed, or turned off or on, by this person,
 * before anything is written: the Workspace settings right, a box that
 * exists, and not a WhatsApp or SMS account's box (kept by its own card).
 * Pure.
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
 * What Remove's confirm says will happen, in the person's words. Removing a
 * box never disconnects its account; one the site's e-mail is sent through
 * is named as staying.
 *
 * @param array       $box
 * @param string|null $kept pg_mail_box_remove_kept(), null to read it for
 *                          the box's account
 * @return string
 */
function ws_mail_box_remove_confirm($box, $kept = null)
{
    if ($kept === null) {
        $kept = '';
        $settings = json_decode((string) ($box['settings'] ?? ''), true);
        $connection_id = is_array($settings) ? (int) ($settings['connection_id'] ?? 0) : 0;
        $account = (($connection_id > 0) && function_exists('pg_connect_account')) ? pg_connect_account($connection_id) : null;

        if (is_array($account)) {
            $kept = pg_mail_box_remove_kept((string) $account['label'], array('sending' => pg_mail_account_sending_kinds($connection_id)));
        }
    }

    return lang(array(
        'string' => 'Remove the mailbox {var:1}? Reading stops, the stored password is deleted and its incoming mail rules go. The conversations stay where they are.',
        'vars'   => (string) $box['name'],
    )) . (($kept !== '') ? ' ' . $kept : '');
}

/**
 * Turn off or Turn on from a box row's menu: the posted value
 * "<box id>:off" or "<box id>:on", read to the box's id and its new state.
 * Pure.
 *
 * @param mixed $value
 * @return array|null id, enabled (0|1); null for anything else
 */
function ws_mail_box_switch_value($value)
{
    if (!is_scalar($value) || !preg_match('/^([1-9][0-9]{0,9}):(on|off)$/', (string) $value, $m)) {
        return null;
    }

    return array('id' => (int) $m[1], 'enabled' => ($m[2] === 'on') ? 1 : 0);
}

/**
 * Switch a box checked by ws_mail_box_remove_check() off or on: an off box
 * is not read and its pill reads Off. A Site Log line names it.
 *
 * @param array  $box
 * @param int    $enabled 0|1
 * @param string $username
 */
function ws_mail_box_switch_write($box, $enabled, $username)
{
    db("UPDATE ws_mail_boxes SET enabled = '" . ($enabled ? 1 : 0) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $box['id'] . "'");
    log_activity(lang(array('string' => $enabled ? 'the mailbox {var:1} was turned on' : 'the mailbox {var:1} was turned off', 'vars' => (string) $box['name'])), $username);
}

/**
 * A box row's "more" menu, the Design examples' Menus part: Turn off (or
 * Turn on for a box that is off), a divider, then Remove in danger, which
 * asks ws_mail_box_remove_confirm() first (ws_mail_settings_script()). Both
 * are submit buttons of the settings form: ws_mail_switch ("<id>:off" or
 * "<id>:on") and ws_mail_remove (the box's id).
 *
 * @param array  $box
 * @param string $form the settings form's id
 * @return string HTML
 */
function ws_mail_box_menu($box, $form)
{
    $id = (int) $box['id'];
    $on = !empty($box['enabled']);
    $f = ' form="' . h($form) . '"';

    return '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
        . '<ul class="menu pg-menu-end">'
        . '<li><button type="submit" name="ws_mail_switch" value="' . $id . ':' . ($on ? 'off' : 'on') . '"' . $f . ' class="menu-item"><i class="bi ' . ($on ? 'bi-pause-circle' : 'bi-play-circle') . ' me-3" aria-hidden="true"></i>' . h($on ? lang('Turn off') : lang('Turn on')) . '</button></li>'
        . '<li><hr class="menu-divider"></li>'
        . '<li><button type="submit" name="ws_mail_remove" value="' . $id . '"' . $f . ' class="menu-item fg-emphasis-danger" data-ws-mail-confirm="' . h(ws_mail_box_remove_confirm($box)) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Remove')) . '</button></li>'
        . '</ul></span>';
}

/**
 * The parts of a mailbox's form, each a run of grid columns: name, address,
 * room (Kept in and the channel), enabled, support, signature, reading (the
 * Reading tab), answers, routing. ws_mail_box_form() puts them in its tabs,
 * Add a mailbox's sheet in its steps (ws_mail_add_sheet()).
 *
 * @param array|null $box
 * @param array      $channels   ws_mail_box_channels()
 * @param bool       $ai_ready
 * @param bool       $rooms_only ws_mail_box_rooms_only()
 * @param string     $prefix     the form's id prefix
 * @param bool       $sheet      the sheet's Reading part: no Mail service
 *                               or "Sign in with" to choose (its steps do)
 * @return string[]
 */
function ws_mail_box_form_parts($box, $channels, $ai_ready, $rooms_only, $prefix, $sheet = false)
{
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

    return array(
        'name'      => '
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="pg-input" type="text" name="name" id="' . $prefix . 'name" maxlength="100" required value="' . h($box ? (string) $box['name'] : '') . '" placeholder="' . h(lang('Support')) . '">
                </div>',
        'address'   => '
                <div class="md:col-6">
                    <label class="pg-field-label" for="' . $prefix . 'address">' . h(lang('E-mail address')) . '</label>
                    <input class="pg-input" type="email" name="address" id="' . $prefix . 'address" maxlength="255" required value="' . h($box ? (string) $box['address'] : '') . '" placeholder="support@example.com">
                </div>',
        'room'      => '
                ' . ws_mail_room_fields($prefix, $box, $options, $rooms_only),
        'enabled'   => '
                <div class="md:col-12">
                    <div class="d-flex align-items-center gap-3">
                        <input class="pg-switch" type="checkbox" name="enabled" value="1" id="' . $prefix . 'enabled"' . ((!$box || (int) $box['enabled'] === 1) ? ' checked' : '') . '>
                        <label for="' . $prefix . 'enabled">' . h(lang('On')) . '</label>
                    </div>
                </div>',
        'support'   => '
                ' . ws_mail_support_field($prefix, $box),
        'signature' => '
                <div class="md:col-12">
                    <label class="pg-field-label" for="' . $prefix . 'signature">' . h(lang('Signature')) . '</label>
                    <textarea class="pg-input" name="signature" id="' . $prefix . 'signature" rows="3" maxlength="' . WS_MAIL_SIGNATURE_MAX . '">' . h(ws_mail_box_signature($box)) . '</textarea>
                    <div class="pg-field-help">' . h(lang('Put under each new e-mail from this mailbox; left empty, its name is put there.')) . '</div>
                </div>',
        'reading'   => ws_mail_box_server_fields($prefix, $box, $settings, $sheet),
        'answers'   => ws_mail_box_send_fields($prefix, $box, $settings) . '
                <div class="md:col-12' . (ws_mail_box_is_support($box) ? '' : ' d-none') . '" data-ws-mail-when="support=1">
                    <div class="row g-5">
                    ' . ws_mail_first_reply_field($prefix, $box) . '
                    ' . ws_mail_auto_reply_fields($prefix, $box) . '
                    </div>
                </div>
                ' . ws_mail_triage_field($prefix, $box, $ai_ready && !$rooms_only),
        'routing'   => $rooms_only ? '' : ws_mail_route_fields($prefix, $box, $channels),
    );
}

/**
 * One mailbox's form, empty for a new one, in four tabs (the segmented
 * control with role tab, as the Design examples' Drawer draws it): General,
 * Reading, Answers and Routing. Every field is drawn in every tab, a hidden
 * tab's too, so the form posts the same names whichever tab is open.
 *
 * @param string     $self_url
 * @param array|null $box
 * @param array[]    $channels ws_mail_box_channels(); "Kept in" and the
 *                             readers of a box kept in no channel are
 *                             ws_mail_room_fields()
 * @param bool       $ai_ready Pinegrap AI can be asked (ws_mail_triage_ai_ready())
 * @param string|null $form    the Site Settings form's id: no form of its own,
 *                             fields ws_mail[<id>][...] joined to it
 *                             (ws_mail_box_dialog_names()); the drawer's foot
 *                             (ws_mail_box_drawer_foot()) holds its Save
 * @param bool       $rooms_only ws_mail_box_rooms_only(): kept in the mailbox
 *                             itself, so no channel, routing or suggestion
 *                             under a card is offered
 * @return string
 */
function ws_mail_box_form($self_url, $box, $channels, $ai_ready = false, $form = null, $rooms_only = false)
{
    $id = $box ? (int) $box['id'] : 0;
    $prefix = 'ws_mail_' . $id . '_';
    $settings = ws_mail_box_settings($box);
    $part = ws_mail_box_form_parts($box, $channels, $ai_ready, $rooms_only, $prefix);
    $fields = array(
        'general' => $part['name'] . $part['address'] . $part['room'] . $part['enabled'] . $part['support'] . $part['signature'],
        'reading' => $part['reading'],
        'answers' => $part['answers'],
        'routing' => $part['routing'],
    );
    $panes = array();

    foreach (ws_mail_box_tab_labels($rooms_only) as $key => $label) {
        $panes[$key] = array($label, $fields[$key]);
    }

    $tabs = ws_mail_box_tabs($prefix, $panes);
    $fields = $tabs['panes'];

    if ($form !== null) {
        // Test connection is the pane's named button, not a field of the box.
        $test = ($box && ((string) ($settings['host'] ?? '') !== ''))
            ? '<div class="md:col-12"><button type="submit" name="ws_mail_test" value="' . $id . '" form="' . h($form) . '" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plug" aria-hidden="true"></i>' . h(lang('Test connection')) . '</button></div>'
            : '';

        return '
        <div class="ws-mail-box-form" data-ws-mail-dialog data-ws-mail-tabs="' . h($prefix) . '">' . str_replace('<!--ws-mail-test-->', $test, ws_mail_box_dialog_names($fields, $id, $form)) . '
        </div>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-mail" class="py-5 ws-mail-box-form" data-ws-mail-tabs="' . h($prefix) . '">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mail_box_save">
            <input type="hidden" name="box_id" value="' . $id . '">
            <div class="mb-5">' . $tabs['bar'] . '</div>
            ' . str_replace('<!--ws-mail-test-->', '', $fields) . '
            <button type="submit" class="pg-button pg-button-fill mt-5">' . h(lang('Save')) . '</button>
        </form>';
}

/**
 * The tabs of a box's form; a box kept in the mailbox itself has no channel
 * to route to, so no Routing. Pure but for the words.
 *
 * @param bool $rooms_only ws_mail_box_rooms_only()
 * @return array key => label
 */
function ws_mail_box_tab_labels($rooms_only = false)
{
    $labels = array('general' => lang('General'), 'reading' => lang('Reading'), 'answers' => lang('Answers'), 'routing' => lang('Routing'));

    if ($rooms_only) {
        unset($labels['routing']);
    }

    return $labels;
}

/**
 * A box form's tabs: the segmented control (role tablist, one tab a pane)
 * and the panes, General open. Pure but for the words.
 *
 * @param string $prefix the form's id prefix
 * @param array  $panes  key => array(label, fields HTML)
 * @return array bar (the tabs, HTML), panes (HTML)
 */
function ws_mail_box_tabs($prefix, $panes)
{
    $bar = '<span class="pg-seg" role="tablist" aria-label="' . h(lang('Mailbox')) . '">';
    $html = '';
    $first = true;

    foreach ($panes as $key => $pane) {
        $tab = $prefix . 'tab_' . $key;
        $id = $prefix . 'pane_' . $key;
        $bar .= '<button type="button" class="pg-seg-item" role="tab" id="' . h($tab) . '" aria-selected="' . ($first ? 'true' : 'false') . '" aria-controls="' . h($id) . '"' . ($first ? '' : ' tabindex="-1"') . ' data-ws-mail-tab>' . h($pane[0]) . '</button>';
        $html .= '
            <div class="ws-mail-box-pane" role="tabpanel" id="' . h($id) . '" aria-labelledby="' . h($tab) . '"' . ($first ? '' : ' hidden') . '>
                <div class="row g-5">' . $pane[1] . '
                </div>
            </div>';
        $first = false;
    }

    return array('bar' => $bar . '</span>', 'panes' => $html);
}

/**
 * The foot of a box's drawer, as the Design examples' Drawer draws it:
 * "Remove mailbox" in danger at the left (a kept box only; it asks
 * ws_mail_box_remove_confirm() first), then Cancel quiet and Save filled.
 * Save and Remove are the settings form's named buttons (ws_mail_box_save
 * and ws_mail_remove, value the box's id). Pure but for the words.
 *
 * @param array|null $box
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog pg_settings_drawer()'s: Cancel closes the
 *                              dialog's panel, else Bootstrap's drawer
 * @return string HTML
 */
function ws_mail_box_drawer_foot($box, $form, $in_dialog)
{
    $id = $box ? (int) $box['id'] : 0;
    $f = ' form="' . h($form) . '"';

    return ($box ? '<button type="submit" name="ws_mail_remove" value="' . $id . '"' . $f . ' class="pg-button pg-button-danger" data-ws-mail-confirm="' . h(ws_mail_box_remove_confirm($box)) . '"><i class="bi bi-trash" aria-hidden="true"></i>' . h(lang('Remove mailbox')) . '</button>' : '')
        . '<span class="flex-grow-1"></span>'
        . '<button type="button" class="pg-button pg-button-quiet"' . ($in_dialog ? ' data-pgdrawer-close' : ' data-bs-dismiss="drawer"') . '>' . h(lang('Cancel')) . '</button>'
        . '<button type="submit" name="ws_mail_box_save" value="' . $id . '"' . $f . ' class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>';
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
 * How a box's form opens its Reading tab on: which sign-in fields show. A
 * box with no service and no server reads nothing ("none"); Microsoft signs
 * in with a connected account; Gmail as its "Sign in with" says; any other
 * service with a connected account once one is chosen, else with the user
 * name and password. The card's script works it out again on each change.
 * Pure.
 *
 * @param string $provider      ws_mail_providers() key, '' for none
 * @param string $google_way    ws_mail_google_way()
 * @param int    $connection_id the chosen connected account, 0 for none
 * @param string $host          the IMAP server
 * @return string none | account | password
 */
function ws_mail_box_signin_way($provider, $google_way, $connection_id, $host)
{
    $provider = (string) $provider;

    if (($provider === '') && ((string) $host === '')) {
        return 'none';
    }

    if ($provider === 'microsoft') {
        return 'account';
    }

    if ($provider === 'gmail') {
        return ($google_way === 'password') ? 'password' : 'account';
    }

    return ((int) $connection_id > 0) ? 'account' : 'password';
}

/**
 * Whether a box's IMAP server and port stand in the Reading tab itself: for
 * another server, or a box with a server and no service; a chosen service
 * fills them, so they wait folded under "Advanced". Pure.
 *
 * @param string $provider
 * @param string $host
 * @return bool
 */
function ws_mail_box_server_own($provider, $host)
{
    return ((string) $provider === 'other') || (((string) $provider === '') && ((string) $host !== ''));
}

/**
 * The Reading tab of a mailbox's form: the mail service with its one line,
 * then only what that service needs (Gmail's "Sign in with", the button of
 * Google's or Microsoft's set-up drawer, the user name and password or the
 * connected account, the folder and what happens after reading); the IMAP
 * server and port when the service fills them, the
 * checking server and Reads bounces folded under "Advanced". The password
 * is drawn empty: an empty box keeps the kept one. The drawer adds Test
 * connection where <!--ws-mail-test--> stands.
 *
 * @param string     $prefix
 * @param array|null $box
 * @param array      $settings ws_mail_box_settings()
 * @param bool       $sheet    Add a mailbox's sheet: Mail service and
 *                             "Sign in with" post unseen, no Microsoft
 *                             set-up button (its Sign in step has them)
 * @return string
 */
function ws_mail_box_server_fields($prefix, $box, $settings, $sheet = false)
{
    $provider = (string) ($settings['provider'] ?? '');
    $host = (string) ($settings['host'] ?? '');
    $options = '<option value="">' . h(lang('None: only .eml files')) . '</option>';
    $hints = '';

    foreach (ws_mail_providers() as $key => $entry) {
        $options .= '<option value="' . h($key) . '"' . (($key === $provider) ? ' selected' : '') . '>' . h($entry['label']) . '</option>';
        $hints .= '<div class="pg-field-help' . (($key === $provider) ? '' : ' d-none') . '" data-ws-mail-when="provider=' . h($key) . '">' . h($entry['hint']) . '</div>';
    }

    $kept = !empty($settings['secret']);
    $move = $box && ((string) $box['after_fetch'] === 'move');
    $via = $box ? (string) ($box['reply_via'] ?? 'box') : 'box';
    $connection_id = (int) ($settings['connection_id'] ?? 0);
    $google_way = ws_mail_google_way($settings);
    $way = ws_mail_box_signin_way($provider, $google_way, $connection_id, $host);
    $own = ws_mail_box_server_own($provider, $host);
    $accounts = '<option value="">' . h(lang('None: sign in with the password')) . '</option>';
    $listed = false;
    $asks = '';
    $may_connect = defined('USER_ROLE') && ((int) USER_ROLE <= 2);

    foreach (ws_mail_box_accounts() as $account) {
        $listed = $listed || ((int) $account['id'] === $connection_id);
        $accounts .= ws_mail_box_account_option($account, $connection_id);

        if (pg_mail_box_account_asks($account)) {
            $asks .= ws_mail_box_account_ask_html($account, $may_connect, (int) $account['id'] === $connection_id);
        }
    }

    // A chosen account since disconnected stays named until another is picked.
    if (($connection_id > 0) && !$listed) {
        $accounts .= '<option value="' . $connection_id . '" selected>' . h(lang('An account no longer connected')) . '</option>';
    }

    $shown = function ($rule) use ($way) {
        return (($rule === '!none') ? ($way !== 'none') : ($way === $rule)) ? '' : ' d-none';
    };
    // Moved between the tab and the fold by the card's script as the
    // service changes; one copy, so the names stay one each.
    $server = '
                        <div class="row g-5" data-ws-mail-server>
                            <div class="col-8">
                                <label class="pg-field-label" for="' . $prefix . 'host">' . h(lang('IMAP server')) . '</label>
                                <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="host" id="' . $prefix . 'host" maxlength="253" value="' . h($host) . '" placeholder="imap.example.com" autocomplete="off">
                            </div>
                            <div class="col-4">
                                <label class="pg-field-label" for="' . $prefix . 'port">' . h(lang('Port')) . '</label>
                                <input class="pg-input" type="number" name="port" id="' . $prefix . 'port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['port'] ?? 0, $provider, 'port') . '">
                            </div>
                        </div>';

    // In Add a mailbox's sheet the starting point sets the service and the
    // Sign in step the way: their fields post, unseen.
    $hidden = $sheet ? ' hidden' : '';

    return '
                <div class="md:col-12"' . $hidden . '>
                    <label class="pg-field-label" for="' . $prefix . 'provider">' . h(lang('Mail service')) . '</label>
                    <select class="pg-input" name="provider" id="' . $prefix . 'provider">' . $options . '</select>
                    <div class="pg-field-help' . (($provider === '') ? '' : ' d-none') . '" data-ws-mail-when="provider=">' . h(lang('Mail is brought in only as .eml files.')) . '</div>' . $hints . '
                </div>
                <div class="md:col-12" data-ws-mail-slot="own">' . ($own ? $server : '') . '</div>
                ' . ($sheet ? '<div hidden>' . ws_mail_google_signin($provider, $prefix, $google_way) . '</div>' : ws_mail_google_signin($provider, $prefix, $google_way)) . ($sheet ? '' : '
                <div class="md:col-12' . (($provider === 'microsoft') ? '' : ' d-none') . '" data-ws-mail-when="provider=microsoft">' . ws_mail_setup_button('microsoft') . '</div>') . '
                <div class="md:col-6' . $shown('password') . '" data-ws-mail-when="signin=password">
                    <label class="pg-field-label" for="' . $prefix . 'user">' . h(lang('User name')) . '</label>
                    <input class="pg-input" type="text" spellcheck="false" autocapitalize="off" name="user" id="' . $prefix . 'user" maxlength="255" value="' . h((string) ($settings['user'] ?? '')) . '" autocomplete="off">
                </div>
                <div class="md:col-6' . $shown('password') . '" data-ws-mail-when="signin=password">
                    <label class="pg-field-label" for="' . $prefix . 'password">' . h(lang('Password')) . '</label>
                    <input class="pg-input" type="password" name="password" id="' . $prefix . 'password" value="" autocomplete="new-password" placeholder="' . h($kept ? lang('Stored. Type a new one to replace it.') : '') . '">
                </div>
                <div class="md:col-12' . $shown('account') . '" data-ws-mail-when="signin=account">
                    <label class="pg-field-label" for="' . $prefix . 'connection">' . h(lang('Connected account')) . '</label>
                    <select class="pg-input" name="connection_id" id="' . $prefix . 'connection"' . (($via === 'connection') ? ' required' : '') . '>' . $accounts . '</select>
                    <div class="pg-field-help">' . h(lang('It signs in instead of a password; the user name is the mailbox\'s address.')) . '</div>' . $asks . '
                </div>
                <div class="md:col-6' . $shown('!none') . '" data-ws-mail-when="signin!=none">
                    <label class="pg-field-label" for="' . $prefix . 'folder">' . h(lang('Folder to read')) . '</label>
                    <input class="pg-input" type="text" name="folder" id="' . $prefix . 'folder" maxlength="255" value="' . h($box ? (string) $box['folder'] : 'INBOX') . '">
                </div>
                <div class="md:col-6' . $shown('!none') . '" data-ws-mail-when="signin!=none">
                    <label class="pg-field-label" for="' . $prefix . 'after">' . h(lang('After reading')) . '</label>
                    <select class="pg-input" name="after_fetch" id="' . $prefix . 'after">
                        <option value="seen"' . ($move ? '' : ' selected') . '>' . h(lang('Mark as read')) . '</option>
                        <option value="move"' . ($move ? ' selected' : '') . '>' . h(lang('Move to a folder')) . '</option>
                    </select>
                </div>
                <div class="md:col-6' . ($move ? '' : ' d-none') . '" data-ws-mail-when="after_fetch=move">
                    <label class="pg-field-label" for="' . $prefix . 'move">' . h(lang('Folder to move to')) . '</label>
                    <input class="pg-input" type="text" name="move_folder" id="' . $prefix . 'move" maxlength="255" value="' . h((string) ($settings['move_folder'] ?? '')) . '" placeholder="' . h(lang('Processed')) . '">
                </div>
                <div class="md:col-12">
                    <details class="pg-start-advanced">
                        <summary class="small"><i class="bi bi-sliders me-1" aria-hidden="true"></i><b>' . h(lang('Advanced')) . '</b> <span class="small fg-2">' . h(lang('Server, checking server and bounces')) . '</span></summary>
                        <div class="mt-3" data-ws-mail-slot="advanced">' . ($own ? '' : $server) . '</div>
                        <div class="mt-5">
                            <label class="pg-field-label" for="' . $prefix . 'auth">' . h(lang('Checking server')) . '</label>
                            <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="auth_server" id="' . $prefix . 'auth" maxlength="255" value="' . h((string) ($settings['auth_server'] ?? '')) . '" placeholder="mx.google.com" autocomplete="off">
                            <div class="pg-field-help">' . h(lang('The server whose sender check is trusted.')) . '</div>
                        </div>
                        <div class="mt-5">
                            <div class="d-flex align-items-center gap-3">
                                <input class="pg-checkbox" type="checkbox" name="reads_bounces" value="1" id="' . $prefix . 'reads_bounces"' . (!empty($settings['reads_bounces']) ? ' checked' : '') . '>
                                <label for="' . $prefix . 'reads_bounces">' . h(lang('Reads bounces')) . '</label>
                            </div>
                            <div class="pg-field-help">' . h(lang('E-mails the site sent that could not be delivered come back here and are recorded.')) . '</div>
                        </div>
                    </details>
                </div>
                <!--ws-mail-test-->';
}

/**
 * The Answers tab's sending half: Send through, the sending server's
 * fields only for the mailbox's own server, the sent copy and its folder,
 * and reading the Sent folder.
 *
 * @param string     $prefix
 * @param array|null $box
 * @param array      $settings ws_mail_box_settings()
 * @return string HTML
 */
function ws_mail_box_send_fields($prefix, $box, $settings)
{
    $provider = (string) ($settings['provider'] ?? '');
    $via = $box ? (string) ($box['reply_via'] ?? 'box') : 'box';
    $copy = !isset($settings['sent_copy']) || !empty($settings['sent_copy']);

    return '
                <div class="md:col-12">
                    <label class="pg-field-label" for="' . $prefix . 'reply_via">' . h(lang('Send through')) . '</label>
                    <select class="pg-input" name="reply_via" id="' . $prefix . 'reply_via">
                        <option value="box"' . (($via === 'box') ? ' selected' : '') . '>' . h(lang('The mailbox\'s sending server')) . '</option>
                        <option value="site"' . (($via === 'site') ? ' selected' : '') . '>' . h(lang('The site\'s mail settings')) . '</option>
                        <option value="connection"' . (($via === 'connection') ? ' selected' : '') . '>' . h(lang('The connected account')) . '</option>
                    </select>
                    <div class="pg-field-help">' . h(lang('Answers leave from the mailbox\'s address.')) . '</div>
                </div>
                <div class="md:col-8' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="pg-field-label" for="' . $prefix . 'smtp_host">' . h(lang('SMTP server')) . '</label>
                    <input class="pg-input font-monospace" type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="smtp_host" id="' . $prefix . 'smtp_host" maxlength="253" value="' . h((string) ($settings['smtp_host'] ?? '')) . '" placeholder="smtp.example.com" autocomplete="off">
                </div>
                <div class="md:col-4' . (($via === 'box') ? '' : ' d-none') . '" data-ws-mail-when="reply_via=box">
                    <label class="pg-field-label" for="' . $prefix . 'smtp_port">' . h(lang('Port')) . '</label>
                    <input class="pg-input" type="number" name="smtp_port" id="' . $prefix . 'smtp_port" min="1" max="65535" value="' . ws_mail_box_port_shown($settings['smtp_port'] ?? 0, $provider, 'smtp_port') . '">
                </div>
                <div class="md:col-8">
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <input class="pg-checkbox" type="checkbox" name="sent_copy" value="1" id="' . $prefix . 'sent_copy"' . ($copy ? ' checked' : '') . '>
                        <label for="' . $prefix . 'sent_copy">' . h(lang('Keep a copy of each answer in the mailbox\'s sent folder')) . '</label>
                    </div>
                    <div class="pg-field-help">' . h(lang('Gmail and Microsoft 365 keep their own copy.')) . '</div>
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
                    <div class="pg-field-help">' . h(lang('Answers written in a mail program join their conversation.')) . '</div>
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
 * How a Gmail or Google Workspace mailbox signs in, shown while that service
 * is chosen: a connected Google account (purpose mail_read, IMAP with
 * XOAUTH2, answers through the Gmail API) or an app password, the account's
 * own password being refused. "Sign in with" only shows the way's fields
 * and is not stored; the steps done at Google stand in their own drawer
 * (ws_mail_setup_button()). Pure but for the words.
 *
 * @param string $provider the box's ws_mail_providers() key
 * @param string $prefix   the form's id prefix
 * @param string $way      ws_mail_google_way()
 * @return string HTML
 */
function ws_mail_google_signin($provider, $prefix = 'ws_mail_0_', $way = 'connection')
{
    $way = ($way === 'password') ? 'password' : 'connection';

    return '
                <div class="md:col-12' . (($provider === 'gmail') ? '' : ' d-none') . '" data-ws-mail-when="provider=gmail">
                    <label class="pg-field-label" for="' . h($prefix) . 'google_way">' . h(lang('Sign in with')) . '</label>
                    <select class="pg-input" name="google_way" id="' . h($prefix) . 'google_way">
                        <option value="connection"' . (($way === 'connection') ? ' selected' : '') . '>' . h(lang('A connected Google account')) . '</option>
                        <option value="password"' . (($way === 'password') ? ' selected' : '') . '>' . h(lang('An app password')) . '</option>
                    </select>
                    <div class="mt-3">' . ws_mail_setup_button('google') . '</div>
                </div>';
}

/**
 * The id of a provider's set-up drawer. Pure.
 *
 * @param string $provider google | microsoft
 * @return string
 */
function ws_mail_setup_drawer_id($provider)
{
    return 'pgset-ws-mail-' . (($provider === 'google') ? 'google' : 'microsoft') . '-setup';
}

/**
 * The one quiet button that opens a provider's set-up drawer: what is done
 * at Google or Microsoft, never in the box's form. Inside the settings
 * dialog ws_settings_mail_drawer() hands it to the dialog's own drawers.
 * Pure but for the words.
 *
 * @param string $provider google | microsoft
 * @return string HTML
 */
function ws_mail_setup_button($provider)
{
    $id = ws_mail_setup_drawer_id($provider);

    return '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $id . '" aria-controls="' . $id . '">'
        . '<i class="bi bi-list-ol" aria-hidden="true"></i>' . h(($provider === 'google') ? lang('Set up the Google application') : lang('Set up the Microsoft application')) . '</button>';
}

/**
 * A provider's set-up drawer's body: the steps done outside the site,
 * numbered by the stepper as the Design examples' numbered steps drawer:
 * the application in the provider's console, its two redirect addresses
 * with Copy, Google's Gmail API, the mailbox's own account on Connections,
 * then what is chosen in the box. Google adds its verification warning and,
 * folded, the app password's steps. Pure but for the words.
 *
 * @param string $provider google | microsoft
 * @param array  $parts    ws_mail_setup_parts()
 * @return string HTML
 */
function ws_mail_setup_steps($provider, $parts)
{
    $google = ($provider === 'google');
    $prefix = 'ws_mail_' . ($google ? 'google' : 'microsoft') . '_';
    $button = function ($url, $text) {
        return '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url) . '" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' . h($text) . '</a>';
    };
    $addresses = '';

    foreach (array_values((array) ($parts['addresses'] ?? array())) as $index => $address) {
        $id = $prefix . 'redirect_' . ($index + 1);
        $addresses .= '
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <input type="text" class="pg-input font-monospace flex-grow-1 min-w-0" id="' . h($id) . '" value="' . h((string) $address) . '" readonly aria-label="' . h(lang('Redirect address')) . '">
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-copy="' . h($id) . '"><i class="bi bi-clipboard" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
                    </div>';
    }

    if ($addresses !== '') {
        $addresses = '<div data-ws-mail-copies>' . $addresses . '
                    <div class="pg-field-help" role="status" aria-live="polite" data-ws-mail-copied="' . h(lang('Copied')) . '" data-ws-mail-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></div></div>';
    }

    $console = (string) ($parts['console'] ?? '');
    $in_box = h(lang('Choose the account as the Connected account in the Reading tab, and The connected account as Send through in the Answers tab.') . ' ' . lang('Save, then press Test connection on the mailbox\'s row.'));

    if ($google) {
        $steps = array(
            array(lang('The application in Google Cloud'),
                h(lang('In Google Cloud Console, open APIs & Services, then Credentials, and create an OAuth client ID of the Web application type.') . ' ' . lang('Its client ID and secret are typed on Google\'s page in Connections.')),
                ($console !== '') ? $button($console, lang('Google Cloud Console')) : ''),
            array(lang('The redirect addresses'), h(lang('Add both addresses to the application\'s OAuth client in Google Cloud, under Authorized redirect URIs:')), $addresses),
            array(lang('The Gmail API'), lang(array('string' => 'Turn on the Gmail API in the same Google Cloud project; answers are sent through it: {var:1}.',
                'vars' => '<a href="' . h(pg_google_cloud_enable_url('gmail.googleapis.com')) . '" target="_blank" rel="noopener noreferrer">' . h(lang('Gmail API')) . '</a>')), ''),
            array(lang('The mailbox\'s Google account'), h(lang('Connect the mailbox\'s own Google account on Google\'s page in Connections (the link below opens it in a new tab); Google asks the account to allow reading and sending its mail.')), (string) ($parts['connect'] ?? '')),
            array(lang('In the mailbox'), $in_box, ''),
        );
    } else {
        $steps = array(
            array(lang('The application in Microsoft Entra'),
                h(lang('In the Microsoft Entra admin center, open App registrations and choose New registration.') . ' ' . lang('Its client ID, secret and directory are typed on Microsoft\'s page in Connections.')),
                ($console !== '') ? $button($console, lang('Microsoft Entra admin center')) : ''),
            array(lang('The redirect addresses'), h(lang('Add both addresses to the application as Web redirect URIs:')), $addresses),
            array(lang('The mailbox\'s Microsoft account'), h(lang('Connect the mailbox\'s own Microsoft account on Microsoft\'s page in Connections (the link below opens it in a new tab); Microsoft asks the account to allow reading and sending its mail.')), (string) ($parts['connect'] ?? '')),
            array(lang('In the mailbox'), $in_box, ''),
        );
    }

    $html = '<ol class="stepper pg-stepper-cards">' . ws_mail_setup_items($steps) . '</ol>';

    if (!$google) {
        return $html;
    }

    $password = array(
        array(lang('2-Step Verification'), lang(array('string' => 'Turn on 2-Step Verification for that account in {var:1}.', 'vars' => '<a href="https://myaccount.google.com/security" target="_blank" rel="noopener noreferrer">' . h(lang('Google Account › Security')) . '</a>')), ''),
        array(lang('App passwords'), lang(array('string' => 'Open {var:1}, type a name such as the site\'s name and create the password. Google shows a 16-character app password.', 'vars' => '<a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">' . h(lang('App passwords')) . '</a>')), ''),
        array(lang('In the mailbox'), h(lang('In the Reading tab, choose An app password under Sign in with, then type the full address as the user name and the app password, without its spaces, as the password.') . ' ' . lang('Save, then press Test connection on the mailbox\'s row.')), ''),
    );
    $notes = array(
        lang('Nothing is turned on in Gmail itself: Google keeps IMAP access on for every account since January 2025.'),
        lang('App passwords are not offered when 2-Step Verification uses only security keys, when the account has Advanced Protection, or for a work or school account whose organisation does not allow them: ask its administrator.'),
        lang('When the account\'s Google password changes, Google revokes its app passwords: create a new one and type it here.'),
    );

    return $html . '
                <h4 class="pg-group-label mt-5">' . h(lang('Google\'s verification warning')) . '</h4>
                <ul class="pg-text-2 mb-0 ps-4"><li>' . implode('</li><li>', array_map('h', pg_google_cloud_verification_notes('gmail'))) . '</li></ul>
                <details class="pg-start-advanced mt-5">
                    <summary class="small"><i class="bi bi-key me-1" aria-hidden="true"></i><b>' . h(lang('Use an app password instead')) . '</b> <span class="small fg-2">' . h(lang('No Google application: the password is written to the site.')) . '</span></summary>
                    <ol class="stepper pg-stepper-cards mt-3">' . ws_mail_setup_items($password) . '</ol>
                    <ul class="pg-field-help mb-0 ps-4"><li>' . implode('</li><li>', array_map('h', $notes)) . '</li></ul>
                </details>';
}

/**
 * The stepper's items: one child each, the title, the text (HTML) and what
 * goes under it (a button, the addresses, the account's state).
 *
 * @param array[] $steps list of array(title, text HTML, extra HTML)
 * @return string HTML
 */
function ws_mail_setup_items($steps)
{
    $html = '';

    foreach ($steps as $step) {
        $html .= '
                    <li class="stepper-item active"><div>
                        <div class="pg-field-label">' . h($step[0]) . '</div>
                        <div class="pg-text-2 fg-secondary' . (($step[2] !== '') ? ' mb-3' : '') . '">' . $step[1] . '</div>' . $step[2] . '
                    </div></li>';
    }

    return $html;
}

/**
 * A provider's set-up drawer for $pg_settings_modals: "Set up the Google
 * application" or "Set up the Microsoft application", opened by
 * ws_mail_setup_button() in a box's Reading tab.
 *
 * @param string $provider  google | microsoft
 * @param array  $viewer
 * @param bool   $in_dialog pg_settings_drawer()'s
 * @return string HTML
 */
function ws_settings_mail_setup_drawer($provider, $viewer, $in_dialog)
{
    $provider = ($provider === 'google') ? 'google' : 'microsoft';

    return pg_settings_drawer(ws_mail_setup_drawer_id($provider),
        h(($provider === 'google') ? lang('Set up the Google application') : lang('Set up the Microsoft application')),
        ws_mail_setup_steps($provider, ws_mail_setup_parts($viewer, $provider)), $in_dialog);
}

/**
 * Add a mailbox's starting points, the Choose step's cards in reading
 * order: each sets the mail service (provider, '' for none). Pure but for
 * the words.
 *
 * @return array[] key => provider, icon, tone, title, line
 */
function ws_mail_start_points()
{
    return array(
        'gmail'     => array('provider' => 'gmail', 'icon' => 'bi-google', 'tone' => 'blue', 'title' => 'Gmail', 'line' => lang('Gmail or Google Workspace.')),
        'microsoft' => array('provider' => 'microsoft', 'icon' => 'bi-microsoft', 'tone' => 'teal', 'title' => 'Microsoft', 'line' => lang('Microsoft 365 or Outlook.')),
        'other'     => array('provider' => 'other', 'icon' => 'bi-hdd-rack', 'tone' => 'gray', 'title' => lang('Another server'), 'line' => lang('Any server that accepts IMAP over TLS.')),
        'none'      => array('provider' => '', 'icon' => 'bi-file-earmark-arrow-up', 'tone' => 'amber', 'title' => lang('No server'), 'line' => lang('You bring in .eml files by hand.')),
    );
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
 * a server) and Edit, quiet and small, and last its menu (ws_mail_box_menu()).
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

    return '<div class="ws-agset-row ws-mail-box-row" data-ws-mail-box="' . $id . '">'
        . '<span class="pg-icon-tile ws-agset-face" aria-hidden="true"><i class="bi bi-inbox"></i></span>'
        . '<div class="ws-agset-main">'
        . '<div class="ws-agset-name"><strong class="pg-list-row-title">' . h((string) $box['name']) . '</strong><span class="pg-text-meta">' . h((string) $box['address']) . '</span></div>'
        . '<div class="pg-text-meta ws-agset-summary">' . ws_mail_box_row_line($box, $place, $state) . '</div>'
        . '</div>'
        . (($state['words'] !== '') ? '<span class="pg-pill ws-agset-state ' . h($state['tone']) . '">' . h($state['words']) . '</span>' : '')
        . '<div class="ws-agset-edit d-flex flex-wrap align-items-center gap-1">' . $test
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-' . $id . '" aria-controls="pgset-ws-mail-' . $id . '"><i class="bi bi-pencil" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>'
        . ws_mail_box_menu($box, $form)
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
                <div class="pg-field-help mt-3">' . h(lang('A mailbox with a server is read every minute while the general scheduled job runs.')) . '</div>
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
 * null, Add a mailbox's three-step sheet (ws_mail_add_sheet(), with no
 * sign-in come back). The head names the box, its address and state
 * pill; the tabs stand in the band under it; the foot holds Remove
 * mailbox, Cancel and Save (ws_mail_box_drawer_foot()). Bring in an .eml
 * file is the pane's last row (ws_mail_eml_pane()).
 *
 * @param array|null $box
 * @param array      $viewer
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog pg_settings_drawer()'s
 * @param array|null $pending   ws_mail_add_pending() for the sheet, opened
 *                              once where a sign-in or a test left it
 * @return string HTML
 */
function ws_settings_mail_drawer($box, $viewer, $form, $in_dialog, $pending = null)
{
    // A new box is Add a mailbox's three-step sheet (mail_add.php), then
    // the forms its provider buttons submit, outside the settings form.
    if (!$box) {
        $ready = ws_mail_add_ready();

        return ws_mail_add_sheet($viewer, $form, $in_dialog, $ready, $pending) . (($ready['gmail'] || $ready['microsoft']) ? ws_mail_add_connect_forms() : '');
    }

    $channels = ws_mail_box_channels($viewer);
    $ai_ready = ws_mail_triage_ai_ready();
    $rooms_only = ws_mail_box_rooms_only($box, ws_enabled());
    $id = $box ? (int) $box['id'] : 0;
    $bar = ws_mail_box_tabs('ws_mail_' . $id . '_', array_map(function ($label) {
        return array($label, '');
    }, ws_mail_box_tab_labels($rooms_only)))['bar'];
    $body = ws_mail_box_form('', $box, $channels, $ai_ready, $form, $rooms_only);
    $foot = ws_mail_box_drawer_foot($box, $form, $in_dialog);

    // The set-up drawers' buttons open the dialog's own drawers there, over
    // this one (pg_settings_own_dialog_triggers() only renames the pane's).
    if ($in_dialog) {
        $body = str_replace('data-bs-toggle="drawer"', 'data-pgdrawer="open"', $body);
    }

    // The head: its name, then its address and state pill (ws_mail_box_state()).
    $state = ws_mail_box_state($box);
    $line = h((string) $box['address']) . (($state['words'] !== '') ? ' <span class="pg-pill ' . h($state['tone']) . '">' . h($state['words']) . '</span>' : '');

    return pg_settings_drawer('pgset-ws-mail-' . $id, h((string) $box['name']), $body, $in_dialog, $foot, $line, $bar);
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

            // A field\'s value: a ticked box\'s value or \'\', the chosen
            // radio\'s (Kept in), else the field\'s own; null when absent.
            function valueOf(form, name) {
                var control = field(form, name);

                if (!control) {
                    return null;
                }

                if (control.type === \'radio\') {
                    var chosen = form.querySelector(\'[name="\' + control.name + \'"]:checked\');

                    return chosen ? chosen.value : \'\';
                }

                return (control.type === \'checkbox\') ? (control.checked ? control.value : \'\') : control.value;
            }

            // How the box signs in, as ws_mail_box_signin_way() works it out.
            function signin(form) {
                var provider = valueOf(form, \'provider\') || \'\';

                if ((provider === \'\') && !(valueOf(form, \'host\') || \'\')) {
                    return \'none\';
                }

                if (provider === \'microsoft\') {
                    return \'account\';
                }

                if (provider === \'gmail\') {
                    return (valueOf(form, \'google_way\') === \'password\') ? \'password\' : \'account\';
                }

                return valueOf(form, \'connection_id\') ? \'account\' : \'password\';
            }

            // A field that applies to one choice of a select, or to a
            // switch that is on, shows only with it
            // (data-ws-mail-when="name=value", or "name!=value"; signin is
            // the sign-in way); the server and port stand in the Reading
            // tab for another server and fold under Advanced for a service
            // that fills them; the connected account is required when
            // answers are sent through it.
            function sync(form) {
                form.querySelectorAll(\'[data-ws-mail-when]\').forEach(function (part) {
                    var rule = part.getAttribute(\'data-ws-mail-when\');
                    var not = rule.indexOf(\'!=\') !== -1;
                    var pair = rule.split(not ? \'!=\' : \'=\');
                    var value = (pair[0] === \'signin\') ? signin(form) : valueOf(form, pair[0]);

                    part.classList.toggle(\'d-none\', not ? (value === pair[1]) : (value !== pair[1]));
                });

                var server = form.querySelector(\'[data-ws-mail-server]\');
                var provider = valueOf(form, \'provider\') || \'\';
                var own = (provider === \'other\') || ((provider === \'\') && !!(valueOf(form, \'host\') || \'\'));
                var slot = form.querySelector(\'[data-ws-mail-slot="\' + (own ? \'own\' : \'advanced\') + \'"]\');

                if (server && slot && (server.parentNode !== slot)) {
                    slot.appendChild(server);
                }

                var kept = valueOf(form, \'kept_in\');
                var channel = field(form, \'channel_id\');

                if ((kept !== null) && channel && !form.hasAttribute(\'data-ws-mail-dialog\')) {
                    channel.required = (kept !== \'mailbox\');
                }

                var via = field(form, \'reply_via\');
                var account = field(form, \'connection_id\');

                // A sending account that does not read yet shows its Allow
                // reading mail while it is the one chosen.
                form.querySelectorAll(\'[data-ws-mail-ask-for]\').forEach(function (ask) {
                    ask.hidden = !account || (ask.getAttribute(\'data-ws-mail-ask-for\') !== account.value);
                });

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

            // A tab of a box\'s form (the segmented control): its pane shows,
            // the others hide; every pane\'s fields still post.
            function showTab(tab) {
                var list = tab.closest(\'[role="tablist"]\');

                if (!list) {
                    return;
                }

                list.querySelectorAll(\'[role="tab"]\').forEach(function (other) {
                    var on = (other === tab);
                    var pane = document.getElementById(other.getAttribute(\'aria-controls\'));

                    other.setAttribute(\'aria-selected\', on ? \'true\' : \'false\');
                    other.tabIndex = on ? 0 : -1;

                    if (pane) {
                        pane.hidden = !on;
                    }
                });
            }

            // The tab of the pane a field sits in, opened: a field the save
            // refused or the browser found invalid is seen where it was typed.
            function showField(control) {
                // Add a mailbox\'s sheet shows the step holding it.
                if (window.pgWsMailAdd) {
                    window.pgWsMailAdd.reveal(control);
                }

                var pane = (control && control.closest) ? control.closest(\'.ws-mail-box-pane\') : null;
                var tab = (pane && pane.hidden) ? document.getElementById(pane.getAttribute(\'aria-labelledby\')) : null;

                if (tab) {
                    showTab(tab);
                }
            }

            document.addEventListener(\'click\', function (event) {
                var tab = event.target.closest ? event.target.closest(\'[data-ws-mail-tab]\') : null;

                if (tab) {
                    showTab(tab);
                }
            });

            document.addEventListener(\'keydown\', function (event) {
                var tab = event.target.closest ? event.target.closest(\'[data-ws-mail-tab]\') : null;
                var step = {ArrowRight: 1, ArrowLeft: -1}[event.key];

                if (!tab || !step) {
                    return;
                }

                var tabs = Array.prototype.slice.call(tab.closest(\'[role="tablist"]\').querySelectorAll(\'[role="tab"]\'));
                var next = tabs[(tabs.indexOf(tab) + step + tabs.length) % tabs.length];

                event.preventDefault();
                showTab(next);
                next.focus();
            });

            document.addEventListener(\'invalid\', function (event) {
                showField(event.target);
            }, true);

            // A refused save marks its field (pgSettingsRestore()) before the
            // drawer opens and focuses it.
            if (window.MutationObserver) {
                new MutationObserver(function (changes) {
                    changes.forEach(function (change) {
                        if (change.target.hasAttribute(\'data-pg-set-marked\')) {
                            showField(change.target);
                        }
                    });
                }).observe(document.documentElement, {attributes: true, attributeFilter: [\'data-pg-set-marked\'], subtree: true});
            }

            // Add a route copies the template\'s row with ids of its own;
            // Remove takes a row away. Never past the most routes a box keeps.
            var routeCount = 0;

            document.addEventListener(\'click\', function (event) {
                var add = event.target.closest ? event.target.closest(\'[data-ws-mail-route-add]\') : null;
                var remove = event.target.closest ? event.target.closest(\'[data-ws-mail-route-remove]\') : null;
                var part = (add || remove) ? (add || remove).closest(\'.md\\\\:col-12\') : null;
                var list = part ? part.querySelector(\'[data-ws-mail-routes]\') : null;

                if (!list) {
                    return;
                }

                var button = part.querySelector(\'[data-ws-mail-route-add]\');
                var max = parseInt(button.getAttribute(\'data-ws-mail-route-max\'), 10) || 0;

                if (remove) {
                    remove.closest(\'[data-ws-mail-route]\').remove();
                } else if (list.children.length < max) {
                    var html = part.querySelector(\'template[data-ws-mail-route-new]\').innerHTML;

                    routeCount++;
                    list.insertAdjacentHTML(\'beforeend\', html.replace(/__n__/g, \'new\' + routeCount));
                    list.lastElementChild.querySelector(\'input\').focus();
                }

                button.hidden = (list.children.length >= max);
                button.classList.toggle(\'mt-3\', list.children.length > 0);
            });

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
                var form = (event.target.closest && /(^|\\[)(after_fetch|reply_via|kept_in|support|provider|google_way|host|connection_id)\\]?$/.test(event.target.name || \'\')) ? event.target.closest(\'.ws-mail-box-form\') : null;

                if (form) {
                    startPlain(form, event.target);
                    sync(form);
                }
            });

            // Remove in a box row\'s menu says what goes before the settings
            // form is sent; a no stops the submit. Captured, so it runs
            // before any submit handler of the pane or the dialog.
            document.addEventListener(\'click\', function (event) {
                var button = event.target.closest ? event.target.closest(\'[data-ws-mail-confirm]\') : null;

                if (button && !window.confirm(button.getAttribute(\'data-ws-mail-confirm\'))) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);

            // On the category\'s own page a set-up drawer opened from a box\'s
            // drawer takes Bootstrap\'s place of it; once it closes the box\'s
            // drawer opens again as it was, its button focused. In the
            // settings dialog the dialog\'s drawers do this themselves.
            document.addEventListener(\'click\', function (event) {
                var button = event.target.closest ? event.target.closest(\'[data-bs-toggle="drawer"][data-bs-target$="-setup"]\') : null;
                var under = button ? button.closest(\'dialog.drawer\') : null;
                var setup = button ? document.querySelector(button.getAttribute(\'data-bs-target\')) : null;

                if (!under || !setup || !window.pgUI) {
                    return;
                }

                window.pgUI.on(setup, \'drawer\', \'hidden\', function () {
                    window.pgUI.drawer.show(under);
                    window.pgUI.on(under, \'drawer\', \'shown\', function () {
                        button.focus();
                    }, {once: true});
                }, {once: true});
            });

            // A redirect address of a set-up drawer is copied; the line under the
            // addresses says whether it was. Plain http has no clipboard API
            // and a write can be refused: the text is then selected.
            document.addEventListener(\'click\', function (event) {
                var button = event.target.closest ? event.target.closest(\'[data-ws-mail-copy]\') : null;
                var input = button ? document.getElementById(button.getAttribute(\'data-ws-mail-copy\')) : null;
                var status = button ? button.closest(\'[data-ws-mail-copies]\').querySelector(\'[data-ws-mail-copied]\') : null;

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
