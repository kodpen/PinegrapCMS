<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail › Mailboxes: Add a mailbox as a sheet of three
 * steps on a step line (Choose, Sign in, Details). Gmail and Microsoft sign
 * in with the provider's own button on the site's own application
 * (connect.php, purpose mail_read); the return tests the account and opens
 * Details on "Which channel?". Another server and Gmail's app password type
 * their server here and Test connection tries it before Details. Finish
 * stores the box (ws_mail_box_save, value 0) and tests it once more, so the
 * list shows it with its state.
 *
 * What a sign-in or a test found waits in the session until Finish
 * (ws_mail_add_pending()): the fields to fill, the sealed password and the
 * result line. Nothing is stored in a table before Finish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How long a sign-in's or a test's result waits for Finish, in seconds. */
const WS_MAIL_ADD_PENDING_TTL = 1800;

/**
 * The address a provider's sign-in returns to: the Mailboxes tab, told
 * which starting point it came from. Pure.
 *
 * @param string $provider gmail | microsoft
 * @return string
 */
function ws_mail_add_return($provider)
{
    return 'settings_mailboxes.php?ws_mail_add=' . (($provider === 'microsoft') ? 'microsoft' : 'gmail') . '#pgset-ws-mail';
}

/**
 * Which providers' Sign in step shows their own button: the site's
 * application is set up and the person may connect the site's accounts
 * (connect.php asks for a manager).
 *
 * @return array gmail, microsoft => bool
 */
function ws_mail_add_ready()
{
    $may = defined('USER_ROLE') && ((int) USER_ROLE <= 2);

    return array('gmail' => $may && ws_mail_connect_ready('google'), 'microsoft' => $may && ws_mail_connect_ready('microsoft'));
}

/**
 * The id of the form a provider's sign-in button submits (connect.php),
 * printed outside the settings form. Pure.
 *
 * @param string $provider gmail | microsoft
 * @return string
 */
function ws_mail_add_form_id($provider)
{
    return 'pgset-ws-mail-add-' . (($provider === 'microsoft') ? 'microsoft' : 'gmail');
}

/**
 * The two sign-in forms (purpose mail_read on the site's Google or
 * Microsoft application) for $pg_settings_modals.
 *
 * @return string HTML
 */
function ws_mail_add_connect_forms()
{
    return pg_mail_connect_form(ws_mail_add_form_id('gmail'), 'google', 'mail_read', ws_mail_add_return('gmail'))
        . pg_mail_connect_form(ws_mail_add_form_id('microsoft'), 'microsoft', 'mail_read', ws_mail_add_return('microsoft'));
}

/**
 * What a sign-in or a test left for the sheet, read or written. Read, it is
 * null once older than WS_MAIL_ADD_PENDING_TTL; written with false, it goes.
 *
 * @param array|false|null $set
 * @return array|null step (signin | details), fields (name => value),
 *                    secret (sealed password or ''), result (ok, title,
 *                    line), shown (opened once already), at
 */
function ws_mail_add_pending($set = null)
{
    if ($set === false) {
        unset($_SESSION['software']['ws_mail_add']);
        return null;
    }

    if (is_array($set)) {
        $_SESSION['software']['ws_mail_add'] = $set + array('at' => time(), 'shown' => false);
        return $_SESSION['software']['ws_mail_add'];
    }

    $pending = $_SESSION['software']['ws_mail_add'] ?? null;

    return (is_array($pending) && ((int) ($pending['at'] ?? 0) > time() - WS_MAIL_ADD_PENDING_TTL)) ? $pending : null;
}

/**
 * A new box's name from its address: the part before @, its first letter
 * capital ("info@example.com" is "Info"). Pure.
 *
 * @param string $address
 * @return string
 */
function ws_mail_add_name($address)
{
    $local = trim(preg_replace('/[._+-]+/', ' ', strstr((string) $address, '@', true) ?: (string) $address));

    return ($local === '') ? lang('Support') : mb_strtoupper(mb_substr($local, 0, 1)) . mb_substr($local, 1, 99);
}

/**
 * The box Test connection tries before anything is stored: the checked
 * fields and the sealed password or the connected account. Pure.
 *
 * @param array  $values ws_mail_box_input()'s values
 * @param string $secret pg_connect_seal()ed password, '' with an account
 * @return array a ws_mail_boxes row as ws_mail_box_test() reads it
 */
function ws_mail_add_probe_box($values, $secret)
{
    $settings = array();

    foreach (array('provider', 'host', 'port', 'user', 'move_folder', 'connection_id') as $key) {
        $settings[$key] = $values[$key] ?? '';
    }

    if ((string) $secret !== '') {
        $settings['secret'] = (string) $secret;
    }

    return array('id' => 0, 'name' => (string) ($values['name'] ?? ''), 'address' => (string) ($values['address'] ?? ''), 'folder' => (string) ($values['folder'] ?? 'INBOX'),
        'after_fetch' => (string) ($values['after_fetch'] ?? 'seen'), 'enabled' => 1, 'settings' => json_encode($settings));
}

/**
 * The result line of a sign-in or a test. Pure but for the words.
 *
 * @param array  $test    ws_mail_box_test(), or ok false with error text
 * @param string $address
 * @param string $host
 * @return array ok, title, line
 */
function ws_mail_add_result($test, $address, $host)
{
    if (!empty($test['ok'])) {
        return array('ok' => true, 'title' => lang(array('string' => '{var:1} connected', 'vars' => (string) $address)), 'line' => lang('Connection tested: the inbox is read.'));
    }

    $line = isset($test['text']) ? (string) $test['text'] : ws_mail_error_text((string) ($test['error'] ?? ''), (string) ($test['detail'] ?? ''), (string) $host);

    return array('ok' => false, 'title' => lang('The connection test failed'), 'line' => $line);
}

/**
 * A result line drawn: the green line (Result line) when the test passed,
 * else a value row with the rose tile and the server's words. Pure.
 *
 * @param array $result ws_mail_add_result()
 * @return string HTML
 */
function ws_mail_add_result_html($result)
{
    if (!empty($result['ok'])) {
        return '<div class="pg-result mb-5" role="status"><span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-check-lg"></i></span>'
            . '<div><span class="pg-result-title">' . h((string) $result['title']) . '</span><span class="pg-result-line">' . h((string) $result['line']) . '</span></div></div>';
    }

    return '<div class="pg-card-inner mb-5" role="alert"><div class="pg-value-row"><span class="pg-icon-tile pg-tone-rose" aria-hidden="true"><i class="bi bi-exclamation-lg"></i></span>'
        . '<span class="pg-value-row-text"><span class="pg-value-row-name">' . h((string) $result['title']) . '</span><span class="pg-value-row-line">' . h((string) $result['line']) . '</span></span></div></div>';
}

/**
 * The sheet's step line (Step line): Choose, Sign in, Details, the current
 * one marked, the ones before it with the check. The card's script moves
 * it. Pure but for the words.
 *
 * @param int $current 1 to 3
 * @return string HTML
 */
function ws_mail_add_steps_html($current = 1)
{
    $html = '<ol class="stepper pg-steps" aria-label="' . h(lang('Add a mailbox')) . '" data-ws-mail-add-line>';

    foreach (array(1 => lang('Choose'), 2 => lang('Sign in'), 3 => lang('Details')) as $number => $name) {
        $done = $number < $current;
        $html .= '<li class="stepper-item' . ($done ? ' is-done' : '') . '"' . (($number === $current) ? ' aria-current="step"' : '') . '>'
            . '<span class="pg-steps-name">' . h($name) . '<span class="visually-hidden" data-ws-mail-add-done' . ($done ? '' : ' hidden') . '> (' . h(lang('done')) . ')</span></span></li>';
    }

    return $html . '</ol>';
}

/**
 * A choice card (Choice cards: .pg-start-choice on form-field-card radios).
 * Pure.
 *
 * @param string $name  the radios' name
 * @param string $id
 * @param string $value
 * @param array  $card  tone, icon ('' for none), title, line, note
 * @param string $attr  more attributes of the radio
 * @return string HTML
 */
function ws_mail_add_choice($name, $id, $value, $card, $attr = '')
{
    $tile = (($card['icon'] ?? '') !== '') ? '<span class="pg-icon-tile pg-tone-' . h($card['tone']) . '" aria-hidden="true"><i class="bi ' . h($card['icon']) . '"></i></span>' : '';
    $note = (($card['note'] ?? '') !== '') ? '<span class="d-block pg-start-note">' . h($card['note']) . '</span>' : '';

    return '
                    <div class="form-field form-field-card">'
        . '<input class="radio" type="radio" name="' . h($name) . '" value="' . h($value) . '" id="' . h($id) . '" aria-describedby="' . h($id) . '_help"' . $attr . '>'
        . '<label class="pg-start-head" for="' . h($id) . '">' . $tile . '<strong>' . h($card['title']) . '</strong><span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span></label>'
        . '<div class="form-text" id="' . h($id) . '_help">' . h($card['line']) . $note . '</div></div>';
}

/**
 * The providers' own buttons as their brand rules draw them (Provider
 * sign-in); the logos are never recoloured. Pure.
 *
 * @param string $provider gmail | microsoft
 * @return string HTML of the logo
 */
function ws_mail_add_logo($provider)
{
    if ($provider === 'microsoft') {
        return '<svg width="21" height="21" viewBox="0 0 21 21" aria-hidden="true" focusable="false">'
            . '<rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/>'
            . '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>';
    }

    return '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
}

/**
 * The Sign in step's provider part: with the site's application set up and
 * a person who may connect the site's accounts, the provider's one button;
 * else, for Gmail, "Set up Google sign-in" and "Use an app password", for
 * Microsoft the line and the set-up drawer's button. Pure but for the words.
 *
 * @param array $ready gmail, microsoft => the application is set up and
 *                     the person may connect (connect.php's manager)
 * @return string HTML
 */
function ws_mail_add_signin_html($ready)
{
    $line = '<p>' . h(lang('Pinegrap reads and answers this mailbox. Your password is not written to the site.')) . '</p>';
    $button = function ($provider, $class, $label) use ($line) {
        return '<div class="pg-card-inner pg-signin"><button type="submit" form="' . h(ws_mail_add_form_id($provider)) . '" class="' . $class . '" data-ws-mail-add-button="' . $provider . '">'
            . ws_mail_add_logo($provider) . h($label) . '</button>' . $line . '</div>';
    };

    if (!empty($ready['gmail'])) {
        $gmail = $button('gmail', 'pg-signin-google', lang('Sign in with Google'));
    } else {
        $gmail = '<fieldset><legend class="visually-hidden">' . h(lang('How should the mailbox sign in?')) . '</legend><div class="pg-start pg-start-choice">'
            . ws_mail_add_choice('ws_mail_add_google', 'ws_mail_add_google_setup', 'setup', array('title' => lang('Set up Google sign-in'), 'line' => lang('Once; its steps open in a drawer.'), 'note' => lang('About 10 minutes')), ' data-ws-mail-add-google')
            . ws_mail_add_choice('ws_mail_add_google', 'ws_mail_add_google_password', 'password', array('title' => lang('Use an app password'), 'line' => lang('The password is written to the site.'), 'note' => lang('2 minutes')), ' data-ws-mail-add-google')
            . '</div></fieldset>'
            . '<div class="mt-5" data-ws-mail-add-way="setup" hidden><div class="pg-field-help mb-3">' . h(lang('The Google application is not set up yet. Its steps are done once; then Add a mailbox signs in with Google.')) . '</div>' . ws_mail_setup_button('google') . '</div>';
    }

    $microsoft = !empty($ready['microsoft']) ? $button('microsoft', 'pg-signin-microsoft', lang('Sign in with Microsoft'))
        : '<div class="pg-card-inner pg-signin"><p>' . h(lang('The Microsoft application is not set up yet. Its steps are done once; then Add a mailbox signs in with Microsoft.')) . '</p>' . ws_mail_setup_button('microsoft') . '</div>';

    return '<div data-ws-mail-add-for="gmail" hidden>' . $gmail . '</div><div data-ws-mail-add-for="microsoft" hidden>' . $microsoft . '</div>';
}

/**
 * The sheet's foot: Back ghost from the second step on, Cancel quiet, then
 * the step's fill: Continue, Test connection (a server typed here), Finish
 * (the settings form's named buttons ws_mail_add_test and ws_mail_box_save).
 * The card's script shows the ones of the step. Pure but for the words.
 *
 * @param string $form
 * @param bool   $in_dialog
 * @return string HTML
 */
function ws_mail_add_foot($form, $in_dialog)
{
    $f = ' form="' . h($form) . '"';

    return '<button type="button" class="pg-button pg-button-ghost" data-ws-mail-add-back hidden><i class="bi bi-arrow-left" aria-hidden="true"></i>' . h(lang('Back')) . '</button>'
        . '<span class="flex-grow-1"></span>'
        . '<button type="button" class="pg-button pg-button-quiet"' . ($in_dialog ? ' data-pgdrawer-close' : ' data-bs-dismiss="drawer"') . '>' . h(lang('Cancel')) . '</button>'
        . '<button type="button" class="pg-button pg-button-fill" data-ws-mail-add-next>' . h(lang('Continue')) . '</button>'
        . '<button type="submit" name="ws_mail_add_test" value="1"' . $f . ' class="pg-button pg-button-fill" data-ws-mail-add-test hidden>' . h(lang('Test connection')) . '</button>'
        . '<button type="submit" name="ws_mail_box_save" value="0"' . $f . ' class="pg-button pg-button-fill" data-ws-mail-add-finish hidden>' . h(lang('Finish')) . '</button>';
}

/**
 * The fields a pending sign-in or test fills in the sheet, as the script
 * reads them: provider, connection_id, google_way, host, port, user,
 * folder, address, name. Pure.
 *
 * @param array|null $pending ws_mail_add_pending()
 * @return array step, fields
 */
function ws_mail_add_fill($pending)
{
    $fields = array();

    foreach ((array) ($pending['fields'] ?? array()) as $key => $value) {
        if (in_array($key, array('provider', 'connection_id', 'google_way', 'host', 'port', 'user', 'folder', 'address', 'name'), true) && is_scalar($value)) {
            $fields[$key] = (string) $value;
        }
    }

    return array('step' => (($pending['step'] ?? '') === 'signin') ? 'signin' : 'details', 'fields' => $fields);
}

/**
 * Add a mailbox's sheet: the step line in the band, the three steps, the
 * foot. Its fields are the box form's (ws_mail_box_form_parts()) under the
 * names a new box posts, ws_mail[0][...]; Choose's and Sign in's cards post
 * nothing the save reads.
 *
 * @param array      $viewer
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog
 * @param array      $ready     ws_mail_add_signin_html()'s
 * @param array|null $pending   ws_mail_add_pending(), opened once
 * @return string HTML
 */
function ws_mail_add_sheet($viewer, $form, $in_dialog, $ready, $pending = null)
{
    $rooms_only = ws_mail_box_rooms_only(null, ws_enabled());
    $parts = ws_mail_box_form_parts(null, ws_mail_box_channels($viewer), ws_mail_triage_ai_ready(), $rooms_only, 'ws_mail_0_', true);
    $names = function ($html) use ($form) {
        return ws_mail_box_dialog_names($html, 0, $form);
    };
    $result = array('signin' => '', 'details' => '');
    $open = '';

    if ($pending && isset($pending['result']['title'])) {
        $fill = ws_mail_add_fill($pending);
        $result[$fill['step']] = ws_mail_add_result_html($pending['result']);
        $open = ' data-ws-mail-add-pending="' . h(json_encode($fill)) . '"';
    }

    // "Which channel?" is the one question of Details.
    $room = str_replace('>' . h(lang('Channel')) . '</label>', '>' . h(lang('Which channel?')) . '</label>', $parts['room']);
    $choose = array();

    foreach (ws_mail_start_points() as $key => $point) {
        $choose[] = ws_mail_add_choice('ws_mail_start', 'ws_mail_0_start_' . $key, $point['provider'], $point, ' data-ws-mail-start');
    }

    $body = '
            <div class="ws-mail-box-form" data-ws-mail-dialog data-ws-mail-add' . $open . '>
                <div data-ws-mail-add-step="choose">
                    <fieldset><legend class="visually-hidden">' . h(lang('How is the mailbox read?')) . '</legend><div class="pg-start pg-start-choice">' . implode('', $choose) . '
                    </div></fieldset>
                </div>
                <div data-ws-mail-add-step="signin" hidden>' . $result['signin'] . ws_mail_add_signin_html($ready) . '
                    <div data-ws-mail-add-server hidden><div class="row g-5">' . $names(str_replace('<!--ws-mail-test-->', '', $parts['reading'])) . '</div></div>
                </div>
                <div data-ws-mail-add-step="details" hidden>' . $result['details'] . '
                    <div class="row g-5">' . $names($room . $parts['name'] . $parts['address']) . '</div>
                    <details class="pg-start-advanced mt-5">
                        <summary class="small"><b>' . h(lang('Other settings')) . '</b> <span class="small fg-2">' . h(lang('Signature, automatic answer, routing')) . '</span></summary>
                        <div class="row g-5 mt-1">' . $names($parts['enabled'] . $parts['support'] . $parts['signature'] . $parts['answers'] . $parts['routing']) . '</div>
                    </details>
                </div>
            </div>';

    // The set-up drawers' buttons open the dialog's own drawers there.
    if ($in_dialog) {
        $body = str_replace('data-bs-toggle="drawer"', 'data-pgdrawer="open"', $body);
    }

    // A sign-in or a test came back: the sheet opens again where it left.
    $body .= ($open !== '') ? '<script>(function again(n) { if (window.pgWsMailAdd) { window.pgWsMailAdd.resume(); } else if (n < 40) { window.setTimeout(function () { again(n + 1); }, 50); } })(0);</script>' : '';

    return pg_settings_drawer('pgset-ws-mail-0', h(lang('Add a mailbox')), $body . ws_mail_add_script(), $in_dialog, ws_mail_add_foot($form, $in_dialog), '', ws_mail_add_steps_html(1));
}

/**
 * Test connection on the Sign in step: the server's fields as typed (a new
 * box's ws_mail[0][...]), tried before anything is stored. Passed, the
 * fields, the sealed password and the result wait for Finish
 * (ws_mail_add_pending()). Writes no table.
 *
 * @param array $input ws_mail_settings_dialog_input()
 * @return array ok, field (the one to mark), error
 */
function ws_mail_add_test_run($input)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'field' => $field, 'error' => $error);
    };
    $probe = $input;
    $probe['name'] = 'probe';
    $probe['kept_in'] = 'mailbox';
    $probe['connection_id'] = 0;
    $probe['reply_via'] = 'site';
    $user = trim((string) ($input['user'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $address = filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : (filter_var($user, FILTER_VALIDATE_EMAIL) ? $user : '');
    $probe['address'] = ($address !== '') ? $address : 'mailbox@example.com';
    $checked = ws_mail_box_input($probe);

    if (!$checked['ok']) {
        return $fail($checked['error'], $checked['field']);
    }

    $values = $checked['values'];

    if ($values['host'] === '') {
        return $fail(lang('The IMAP server is a host name such as imap.gmail.com, with a port from 1 to 65535.'), 'host');
    }

    // A password typed before for the same server and user is kept until
    // Finish; an empty box uses it.
    $kept = ws_mail_add_pending();
    $secret = '';

    if ($values['password'] !== '') {
        if (!pg_connect_can_seal()) {
            return $fail(lang('This site has no encryption key, so a password cannot be kept.'), 'password');
        }

        $secret = pg_connect_seal(array('password' => $values['password']));
    } elseif ($kept && (($kept['fields']['host'] ?? '') === $values['host']) && (($kept['fields']['user'] ?? '') === $values['user'])) {
        $secret = (string) ($kept['secret'] ?? '');
    }

    if ($secret === '') {
        return $fail(lang('Enter the password for this server and user.'), 'password');
    }

    $test = ws_mail_box_test(ws_mail_add_probe_box($values, $secret));

    if (!$test['ok']) {
        return $fail(ws_mail_error_text($test['error'], $test['detail'], $values['host']), in_array($test['error'], array('auth', 'auth_disabled', 'no_password'), true) ? 'password' : 'host');
    }

    $shown = ($address !== '') ? $address : $values['user'];
    $fields = array('provider' => $values['provider'], 'google_way' => 'password', 'host' => $values['host'], 'port' => (string) $values['port'], 'user' => $values['user'], 'folder' => $values['folder'],
        'address' => strtolower($address), 'name' => ($address !== '') ? ws_mail_add_name($address) : '');

    ws_mail_add_pending(array('step' => 'details', 'fields' => $fields, 'secret' => $secret, 'result' => ws_mail_add_result($test, $shown, $values['host'])));

    return array('ok' => true, 'field' => '', 'error' => '');
}

/**
 * The return from a provider's sign-in (connect.php's message, with the
 * account it connected): the account's box is tried and the sheet opens on
 * Details, or on Sign in with what went wrong.
 *
 * @param string     $provider gmail | microsoft, from the return address
 * @param array|null $flash    pg_connect_flash_take()
 */
function ws_mail_add_returned($provider, $flash)
{
    $provider = ($provider === 'microsoft') ? 'microsoft' : 'gmail';

    if (!is_array($flash)) {
        return;
    }

    $account = ((int) ($flash['connection_id'] ?? 0) > 0) ? pg_connect_account((int) $flash['connection_id']) : null;
    $owner = ($provider === 'microsoft') ? 'microsoft' : 'google';

    if (($flash['kind'] !== 'notice') || !is_array($account) || ((int) $account['user_id'] !== 0) || ($account['provider'] !== $owner)
        || !in_array('mail_read', (array) $account['purposes'], true)) {
        ws_mail_add_pending(array('step' => 'signin', 'fields' => array('provider' => $provider), 'secret' => '',
            'result' => ws_mail_add_result(array('ok' => false, 'text' => (string) $flash['text']), '', '')));
        return;
    }

    $address = strtolower((string) $account['label']);
    $preset = ws_mail_providers()[$provider];
    $values = array('provider' => $provider, 'host' => $preset['host'], 'port' => $preset['port'], 'user' => $address, 'connection_id' => (int) $account['id'],
        'name' => ws_mail_add_name($address), 'address' => $address, 'folder' => 'INBOX', 'after_fetch' => 'seen');
    $test = ws_mail_box_test(ws_mail_add_probe_box($values, ''));

    ws_mail_add_pending(array('step' => 'details', 'secret' => '', 'result' => ws_mail_add_result($test, $address, $preset['host']),
        'fields' => array('provider' => $provider, 'google_way' => 'connection', 'connection_id' => (string) $account['id'], 'address' => $address, 'name' => $values['name'])));
}

/**
 * Finish of the sheet: a new box typed with no password on the server and
 * user a passed test kept takes that password. Pure but for the session.
 *
 * @param array $input ws_mail_settings_dialog_input() for box 0
 * @return array the input, its password filled when it applies
 */
function ws_mail_add_finish_input($input)
{
    $kept = ws_mail_add_pending();

    if (!$kept || ((string) ($input['password'] ?? '') !== '') || ((string) ($kept['secret'] ?? '') === '')
        || (trim((string) ($input['host'] ?? '')) !== ($kept['fields']['host'] ?? '')) || (trim((string) ($input['user'] ?? '')) !== ($kept['fields']['user'] ?? ''))) {
        return $input;
    }

    $secret = pg_connect_unseal((string) $kept['secret']);

    if (isset($secret['password'])) {
        $input['password'] = (string) $secret['password'];
    }

    return $input;
}

/**
 * The sheet's script, bound once: Continue and Back move between the steps
 * and the step line follows; a starting point sets the mail service; Sign
 * in shows the provider's button, Google's two choices or the server's
 * fields with Test connection; a sign-in or a test come back fills the
 * fields and opens Details (resume()); a field the save refused shows its
 * step (reveal(), from ws_mail_settings_script()).
 *
 * @return string
 */
function ws_mail_add_script()
{
    return '
        <script>
        (function () {
            if (window.pgWsMailAdd) {
                return;
            }

            var ORDER = [\'choose\', \'signin\', \'details\'];
            var resuming = false;

            function sheet() {
                return document.querySelector(\'[data-ws-mail-add]\');
            }

            function field(root, name) {
                return root.querySelector(\'[name="ws_mail[0][\' + name + \']"]\');
            }

            function provider(root) {
                var select = field(root, \'provider\');

                return select ? select.value : \'\';
            }

            function googleWay(root) {
                var chosen = root.querySelector(\'[data-ws-mail-add-google]:checked\');

                return chosen ? chosen.value : \'\';
            }

            function set(control, value) {
                if (control && (control.value !== value)) {
                    control.value = value;
                    control.dispatchEvent(new Event(\'change\', {bubbles: true}));
                }
            }

            // Sign in ends with Test connection when the server is typed
            // here: another server, or Gmail\'s app password.
            function typed(root) {
                var service = provider(root);

                return (service === \'other\') || ((service === \'gmail\') && !root.querySelector(\'[data-ws-mail-add-button="gmail"]\') && (googleWay(root) === \'password\'));
            }

            function show(root, step) {
                var drawer = root.closest(\'.pg-sm-drawer, .drawer\') || document;
                var at = ORDER.indexOf(step);
                var service = provider(root);

                root.setAttribute(\'data-ws-mail-add-at\', step);
                root.querySelectorAll(\'[data-ws-mail-add-step]\').forEach(function (pane) {
                    pane.hidden = (pane.getAttribute(\'data-ws-mail-add-step\') !== step);
                });
                drawer.querySelectorAll(\'[data-ws-mail-add-line] > li\').forEach(function (item, index) {
                    var done = item.querySelector(\'[data-ws-mail-add-done]\');

                    item.classList.toggle(\'is-done\', index < at);

                    if (index === at) {
                        item.setAttribute(\'aria-current\', \'step\');
                    } else {
                        item.removeAttribute(\'aria-current\');
                    }

                    if (done) {
                        done.hidden = (index >= at);
                    }
                });
                root.querySelectorAll(\'[data-ws-mail-add-for]\').forEach(function (part) {
                    part.hidden = (part.getAttribute(\'data-ws-mail-add-for\') !== service);
                });
                root.querySelectorAll(\'[data-ws-mail-add-way]\').forEach(function (part) {
                    part.hidden = (part.getAttribute(\'data-ws-mail-add-way\') !== googleWay(root));
                });

                if (service === \'gmail\') {
                    set(field(root, \'google_way\'), (googleWay(root) === \'password\') ? \'password\' : \'connection\');
                }

                var server = root.querySelector(\'[data-ws-mail-add-server]\');

                if (server) {
                    server.hidden = !typed(root);
                }

                var buttons = {back: at > 0, next: at === 0, test: (at === 1) && typed(root), finish: at === 2};

                Object.keys(buttons).forEach(function (key) {
                    var button = drawer.querySelector(\'[data-ws-mail-add-\' + key + \']\');

                    if (button) {
                        button.hidden = !buttons[key];
                    }
                });
            }

            function focusIn(root, step) {
                var pane = root.querySelector(\'[data-ws-mail-add-step="\' + step + \'"]\');
                var first = (step === \'details\') ? field(root, \'channel_id\') : null;

                first = (first && first.offsetParent) ? first : (pane ? pane.querySelector(\'input:not([type="hidden"]), select, button\') : null);
                window.setTimeout(function () {
                    if (first && first.offsetParent) {
                        first.focus();
                    }
                }, 120);
            }

            function go(root, step) {
                show(root, step);
                focusIn(root, step);
            }

            document.addEventListener(\'click\', function (event) {
                var root = sheet();
                var target = event.target.closest ? event.target : null;

                if (!root || !target) {
                    return;
                }

                // Add a mailbox opened by hand starts on Choose.
                if (!resuming && target.closest(\'[data-bs-target="#pgset-ws-mail-0"]\')) {
                    var result = root.querySelectorAll(\'.pg-result, [role="alert"]\');

                    result.forEach(function (line) {
                        line.remove();
                    });
                    set(field(root, \'connection_id\'), \'\');
                    show(root, \'choose\');
                    return;
                }

                if (target.closest(\'[data-ws-mail-add-next]\')) {
                    var card = root.querySelector(\'[data-ws-mail-start]:checked\');

                    if (!card) {
                        var first = root.querySelector(\'[data-ws-mail-start]\');

                        if (first) {
                            first.focus();
                        }

                        return;
                    }

                    set(field(root, \'provider\'), card.value);
                    go(root, (card.value === \'\') ? \'details\' : \'signin\');
                } else if (target.closest(\'[data-ws-mail-add-back]\')) {
                    var at = root.getAttribute(\'data-ws-mail-add-at\') || \'choose\';

                    go(root, ((at === \'details\') && (provider(root) !== \'\')) ? \'signin\' : \'choose\');
                }
            });

            document.addEventListener(\'change\', function (event) {
                var root = sheet();

                if (root && event.target.matches && event.target.matches(\'[data-ws-mail-add-google]\')) {
                    show(root, \'signin\');
                }
            });

            window.pgWsMailAdd = {
                // A field the save refused, or the browser found invalid,
                // shows its step; Gmail\'s refused password its card.
                reveal: function (control) {
                    var root = (control && control.closest) ? control.closest(\'[data-ws-mail-add]\') : null;
                    var pane = root ? control.closest(\'[data-ws-mail-add-step]\') : null;

                    if (!pane) {
                        return;
                    }

                    var card = root.querySelector(\'[data-ws-mail-start][value="\' + provider(root) + \'"]\');
                    var password = root.querySelector(\'[data-ws-mail-add-google][value="password"]\');

                    if (card) {
                        card.checked = true;
                    }

                    if (password && (provider(root) === \'gmail\') && (pane.getAttribute(\'data-ws-mail-add-step\') === \'signin\')) {
                        password.checked = true;
                    }

                    show(root, pane.getAttribute(\'data-ws-mail-add-step\'));
                },

                // A sign-in or a test came back: its fields filled, the
                // sheet opened on the step it named.
                resume: function () {
                    var root = document.querySelector(\'[data-ws-mail-add-pending]\');
                    var fill = null;

                    try {
                        fill = root ? JSON.parse(root.getAttribute(\'data-ws-mail-add-pending\')) : null;
                    } catch (error) {}

                    if (!fill) {
                        return;
                    }

                    root.removeAttribute(\'data-ws-mail-add-pending\');
                    set(field(root, \'provider\'), fill.fields.provider || \'\');
                    Object.keys(fill.fields).forEach(function (key) {
                        set(field(root, key), fill.fields[key]);
                    });

                    var card = root.querySelector(\'[data-ws-mail-start][value="\' + (fill.fields.provider || \'\') + \'"]\');
                    var way = root.querySelector(\'[data-ws-mail-add-google][value="\' + (fill.fields.google_way || \'\') + \'"]\');

                    if (card) {
                        card.checked = true;
                    }

                    if (way) {
                        way.checked = true;
                    }

                    var opener = document.querySelector(\'[data-bs-target="#pgset-ws-mail-0"]\');

                    if (opener && window.pgSettingsFoldReveal) {
                        window.pgSettingsFoldReveal(opener);
                    }

                    resuming = true;

                    if (opener) {
                        opener.click();
                    }

                    resuming = false;
                    go(root, fill.step);
                }
            };
        })();
        </script>';
}
