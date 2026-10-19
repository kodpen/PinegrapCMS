<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Text messages (SMS) through the site's own provider account.
 *
 * One function sends, whatever the provider: pg_sms_send($to, $text, $kind).
 * The provider is a driver under includes/sms/<key>.php, listed in
 * pg_sms_providers(), with two functions: pg_sms_<key>_send() and
 * pg_sms_<key>_senders(). A driver is loaded only when a message or a check
 * needs it.
 *
 * The account is kept in connect_apps under 'sms_<key>', like the carriers'
 * API applications: the user code as client_id, the password sealed with the
 * site's key and never drawn back into a form, the sender name among the
 * options and the Communication settings' switch as enabled. A provider added
 * later needs no schema change.
 *
 * Every message has a kind. Transactional kinds (an order, a shipment, a
 * sign-in code, a reminder) go out as informational messages; a commercial
 * one needs the recipient's consent recorded in İYS, and until the consent
 * records exist pg_sms_send() refuses it.
 *
 * pg_sms_send() makes the network call itself and waits for the answer, so it
 * belongs in a background job or behind an operator's button (the settings'
 * test message), never in a request a visitor waits on.
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
 * The SMS providers, in the order the settings offer them.
 *
 * @return array key => label, file (under includes/sms/), portal
 */
function pg_sms_providers()
{
    return array(
        'netgsm' => array(
            'label'  => 'Netgsm',
            'file'   => 'netgsm.php',
            'portal' => 'https://www.netgsm.com.tr/',
        ),
    );
}

/**
 * @param string $provider
 * @return array|null
 */
function pg_sms_provider_definition($provider)
{
    $providers = pg_sms_providers();

    return (is_string($provider) && isset($providers[$provider])) ? $providers[$provider] : null;
}

/**
 * The connect_apps key a provider's account is kept under. Prefixed, so a
 * provider file added later for a sign-in with the same company does not
 * share it.
 *
 * @param string $provider
 * @return string
 */
function pg_sms_app_key($provider)
{
    return 'sms_' . $provider;
}

/**
 * A provider's account on this site.
 *
 * @param string $provider
 * @return array user, password ('' when none or when the site's key cannot
 *               read it), password_stored, sender, enabled
 */
function pg_sms_app($provider)
{
    $app = function_exists('pg_connect_app') ? pg_connect_app(pg_sms_app_key($provider)) : null;

    if (!is_array($app)) {
        return array(
            'user'            => '',
            'password'        => '',
            'password_stored' => false,
            'sender'          => '',
            'enabled'         => false,
        );
    }

    return array(
        'user'            => (string) $app['client_id'],
        'password'        => (string) $app['client_secret'],
        'password_stored' => (bool) $app['secret_stored'],
        'sender'          => (isset($app['options']['sender']) && is_scalar($app['options']['sender'])) ? (string) $app['options']['sender'] : '',
        'enabled'         => (bool) $app['enabled'],
    );
}

/**
 * Whether an account has everything a message needs.
 *
 * @param array $app pg_sms_app()
 * @return bool
 */
function pg_sms_app_complete($app)
{
    return ($app['user'] !== '') && ($app['password'] !== '') && ($app['sender'] !== '');
}

/**
 * The provider messages go through: the first one switched on with a
 * complete account.
 *
 * @return string provider key, '' when SMS is not set up
 */
function pg_sms_provider()
{
    foreach (array_keys(pg_sms_providers()) as $provider) {
        $app = pg_sms_app($provider);

        if ($app['enabled'] && pg_sms_app_complete($app)) {
            return $provider;
        }
    }

    return '';
}

/**
 * Whether the site can send SMS.
 *
 * @return bool
 */
function pg_sms_ready()
{
    return pg_sms_provider() !== '';
}

/**
 * The kinds of message, and whether each is commercial (İYS checked).
 *
 * @return array kind => commercial
 */
function pg_sms_kinds()
{
    return array(
        'test'      => false,
        'order'     => false,
        'shipping'  => false,
        'otp'       => false,
        'reminder'  => false,
        'marketing' => true,
    );
}

/**
 * A mobile number as the drivers take it: the international form in digits,
 * without the plus (E.164). Spaces, dashes, dots, slashes and brackets are
 * dropped. A number without a country code is read as a Turkish mobile
 * number (05xx…, 5xx…, 90 5xx…); any other one needs its "+" or "00".
 *
 * @param mixed $input
 * @return string '' when it is not such a number
 */
function pg_sms_number($input)
{
    if (!is_scalar($input)) {
        return '';
    }

    $plain = preg_replace('/[\s\x{00A0}().\/-]+/u', '', trim((string) $input));

    if (!is_string($plain) || !preg_match('/^\+?\d+$/', $plain)) {
        return '';
    }

    if ($plain[0] === '+') {
        $digits = substr($plain, 1);
    } elseif (strpos($plain, '00') === 0) {
        $digits = substr($plain, 2);
    } elseif (preg_match('/^(?:90|0)?(5\d{9})$/', $plain, $match)) {
        $digits = '90' . $match[1];
    } else {
        return '';
    }

    // Turkey: the trunk zero people keep after the country code is dropped,
    // and only a mobile number (5xx) can take a text message.
    if (strpos($digits, '90') === 0) {
        $digits = preg_replace('/^900(?=5)/', '90', $digits);

        return preg_match('/^905\d{9}$/', $digits) ? $digits : '';
    }

    // A country code never starts with 0; E.164 allows fifteen digits.
    return preg_match('/^[1-9]\d{7,14}$/', $digits) ? $digits : '';
}

/**
 * A failed send in the shape every send returns.
 *
 * @param string $error
 * @param bool   $retry
 * @return array ok, id, code, error, retry
 */
function pg_sms_failure($error, $retry = false)
{
    return array('ok' => false, 'id' => '', 'code' => '', 'error' => (string) $error, 'retry' => (bool) $retry);
}

/**
 * Loads a provider's driver.
 *
 * @param string $provider
 * @return bool whether its functions are there
 */
function pg_sms_driver($provider)
{
    $definition = pg_sms_provider_definition($provider);

    if ($definition === null) {
        return false;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/sms/' . $definition['file'];

    if (!function_exists('pg_sms_' . $provider . '_send') && is_file($file)) {
        require_once($file);
    }

    return function_exists('pg_sms_' . $provider . '_send') && function_exists('pg_sms_' . $provider . '_senders');
}

/**
 * Sends one message.
 *
 * @param string $to   a mobile number in any form pg_sms_number() reads
 * @param string $text
 * @param string $kind one of pg_sms_kinds()
 * @return array ok, id (the provider's id for the message), code (the
 *               provider's result code), error (a sentence for the operator),
 *               retry (whether trying again later may succeed)
 */
function pg_sms_send($to, $text, $kind)
{
    $kinds = pg_sms_kinds();

    if (!is_string($kind) || !isset($kinds[$kind])) {
        return pg_sms_failure(lang('Unknown kind of SMS message.'));
    }

    if ($kinds[$kind]) {
        return pg_sms_failure(lang('Marketing messages are not sent by SMS: they need the recipient\'s consent recorded in İYS.'));
    }

    $number = pg_sms_number($to);

    if ($number === '') {
        return pg_sms_failure(lang('This is not a mobile number an SMS can be sent to.'));
    }

    $text = trim((string) $text);

    if ($text === '') {
        return pg_sms_failure(lang('The message is empty.'));
    }

    $provider = pg_sms_provider();

    if ($provider === '') {
        return pg_sms_failure(lang('SMS is not set up.'));
    }

    if (!pg_sms_driver($provider)) {
        return pg_sms_failure(lang('The SMS provider could not be loaded.'));
    }

    $send = 'pg_sms_' . $provider . '_send';

    return $send(pg_sms_app($provider), $number, $text, false);
}

/**
 * The sender names approved on an account: the check of a user code and
 * password that costs no credit.
 *
 * @param string $provider
 * @param array  $app pg_sms_app() shape
 * @return array ok, senders, code, error, retry
 */
function pg_sms_senders($provider, $app)
{
    if (!pg_sms_driver($provider)) {
        return array('ok' => false, 'senders' => array(), 'code' => '', 'error' => lang('The SMS provider could not be loaded.'), 'retry' => false);
    }

    $senders = 'pg_sms_' . $provider . '_senders';

    return $senders($app);
}

/**
 * The approved spelling of a sender name, compared without regard to case.
 *
 * @param string $sender
 * @param array  $senders
 * @return string '' when it is not among them
 */
function pg_sms_sender_match($sender, $senders)
{
    $wanted = mb_strtolower(trim((string) $sender), 'UTF-8');

    foreach ($senders as $approved) {
        if ($approved === $sender) {
            return $approved;
        }
    }

    foreach ($senders as $approved) {
        if (mb_strtolower($approved, 'UTF-8') === $wanted) {
            return $approved;
        }
    }

    return '';
}

/**
 * A driver's HTTP call, through the outgoing client that checks the address
 * and does not follow redirects.
 *
 * @param string $method
 * @param array  $request url, headers, body (optional)
 * @return array status (0 when nothing came back), body, error
 */
function pg_sms_http($method, $request)
{
    // The client belongs to a request that went through init.php.
    if (!function_exists('api_http_request')) {
        if (!defined('PG_INIT_LOADED')) {
            return array('status' => 0, 'body' => '', 'error' => 'The outgoing HTTP client is not available here.');
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $options = array(
        'headers'  => $request['headers'],
        'timeout'  => 20,
        'max_body' => 8000,
        'agent'    => 'sms',
    );

    if (isset($request['body'])) {
        $options['body'] = $request['body'];
    }

    $result = api_http_request($method, $request['url'], $options);

    return array(
        'status' => (int) $result['status'],
        'body'   => (string) $result['body'],
        'error'  => (string) $result['error'],
    );
}


/* ---------------------------------------------------------------------------
   Settings › Communication › SMS
   --------------------------------------------------------------------------- */

/**
 * A posted text field, '' when absent or not text.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_sms_post($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
}

/**
 * Whether a sender name can be one: at most eleven characters, no control
 * characters. The provider's list is what says it is approved.
 *
 * @param string $sender
 * @return bool
 */
function pg_sms_sender_valid($sender)
{
    $sender = (string) $sender;

    return ($sender !== '') && (mb_strlen($sender, 'UTF-8') <= 11) && !preg_match('/[\x00-\x1F\x7F]/', $sender);
}

/**
 * The provider whose account the SMS card shows: the one sending, otherwise
 * the first with a stored account, otherwise the first.
 *
 * @return string
 */
function pg_sms_settings_provider()
{
    $active = pg_sms_provider();

    if ($active !== '') {
        return $active;
    }

    foreach (array_keys(pg_sms_providers()) as $provider) {
        if (pg_sms_app($provider)['user'] !== '') {
            return $provider;
        }
    }

    $providers = array_keys(pg_sms_providers());

    return $providers[0];
}

/**
 * The fields of the SMS card: the switch, the provider and its account (the
 * password never drawn back) and the test message.
 *
 * @return string
 */
function pg_sms_settings_fields()
{
    $shown = pg_sms_settings_provider();
    $app = pg_sms_app($shown);
    $enabled = $app['enabled'];

    $warning = '';

    if (!pg_connect_schema_ready()) {
        $warning = lang('The database has not been upgraded yet. Run the software update first.');
    } elseif (!pg_connect_can_seal()) {
        $warning = lang('This site has no encryption key, so it cannot keep the password of an SMS account.');
    } elseif ($enabled && !pg_sms_app_complete($app)) {
        $warning = $app['password_stored'] && ($app['password'] === '')
            ? lang('SMS is switched on but cannot send: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang('SMS is switched on but cannot send until the account is complete.');
    }

    $options = '';

    foreach (pg_sms_providers() as $key => $definition) {
        $options .= '<option value="' . h($key) . '"' . (($key === $shown) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
    }

    $html = '
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="sms_enabled" id="sms_enabled" value="1"' . ($enabled ? ' checked="checked"' : '') . ' class="form-check-input collapse-switcher" data-bs-target="#sms_row"/>
                                                <label class="form-check-label" for="sms_enabled">' . lang('Send SMS') . '<br/><span class="form-text">' . lang('Text messages through an SMS provider account of the site. Only transactional messages are sent and they carry no promotion: a marketing message needs the recipient\'s consent recorded in İYS.') . '</span></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="sms_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">';

    if ($warning !== '') {
        $html .= '
                                                        <div class="col-12"><div class="alert alert-warning mb-0">' . h($warning) . '</div></div>';
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="sms_provider" class="form-label">' . lang('Provider') . '</label>
                                                            <select name="sms_provider" id="sms_provider" class="form-select">' . $options . '</select>
                                                        </div>';

    foreach (pg_sms_providers() as $key => $definition) {
        $html .= pg_sms_settings_account_fields($key, $definition, ($key === $shown) ? $app : pg_sms_app($key));
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="sms_test_to" class="form-label">' . lang('Test Number') . '</label>
                                                            <input type="tel" name="sms_test_to" id="sms_test_to" class="form-control" value="" maxlength="30" autocomplete="off" inputmode="tel"/>
                                                            <div class="form-text">' . lang('A mobile number. The message goes out at once and the provider charges it.') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="submit" name="sms_test" value="1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Save and send a test message') . '</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    return $html;
}

/**
 * One provider's account fields.
 *
 * @param string $provider
 * @param array  $definition
 * @param array  $app pg_sms_app()
 * @return string
 */
function pg_sms_settings_account_fields($provider, $definition, $app)
{
    $name = 'sms_' . $provider . '_';

    // "Stored" only for a password this site can still read; the card's
    // warning says what to do with one it cannot.
    $placeholder = ($app['password_stored'] && ($app['password'] !== ''))
        ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"'
        : '';

    return '
                                                        <div class="col-12 form-text">' . lang(array(
                                                            'string' => 'Enter the user code and password of an API user of your {var:1} account, and a sender name approved on it. Saving checks them with {var:1}. To remove the account from this site, switch SMS off and empty the user code.',
                                                            'vars'   => array('<a href="' . h($definition['portal']) . '" target="_blank" rel="noopener">' . h($definition['label']) . '</a>'))) . '</div>
                                                        <div class="pg-f-md">
                                                            <label for="' . h($name) . 'user" class="form-label">' . lang('User Code') . '</label>
                                                            <input type="text" name="' . h($name) . 'user" id="' . h($name) . 'user" class="form-control" value="' . h($app['user']) . '" maxlength="255" autocomplete="off"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="' . h($name) . 'password" class="form-label">' . lang('Password') . '</label>
                                                            <input type="password" name="' . h($name) . 'password" id="' . h($name) . 'password" class="form-control" value="" maxlength="255" autocomplete="new-password"' . $placeholder . '/>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="' . h($name) . 'sender" class="form-label">' . lang('Sender Name') . '</label>
                                                            <input type="text" name="' . h($name) . 'sender" id="' . h($name) . 'sender" class="form-control" value="' . h($app['sender']) . '" maxlength="11" autocomplete="off"/>
                                                        </div>';
}

/**
 * The provider a post of the SMS card is about.
 *
 * @param array $post
 * @return string|null null when the post has no SMS card, or not the fields
 *                     of the provider it names (a pane that does not draw
 *                     them says nothing about that account)
 */
function pg_sms_settings_posted_provider($post)
{
    if (!isset($post['sms_provider'])) {
        return null;
    }

    $provider = pg_sms_post($post, 'sms_provider');

    if (pg_sms_provider_definition($provider) === null) {
        return null;
    }

    return isset($post['sms_' . $provider . '_user']) ? $provider : null;
}

/**
 * What a post of the SMS card asks for, decided from the post and the stored
 * account alone: no database, no provider. pg_sms_settings_check() marks what
 * this refuses and asks the provider when this says so.
 *
 * Only a change is written. An account left as it was is not written again,
 * whatever else the Communication pane saves, so an account this site can no
 * longer read does not stop the chat or campaign settings from being saved.
 *
 * @param array  $post
 * @param string $provider pg_sms_settings_posted_provider()
 * @param array  $before   pg_sms_app() of that provider
 * @param string $refusal  why no account can be kept here now (database not
 *                         upgraded, no encryption key), '' when one can
 * @return array provider, user, password (as typed), sender, enabled,
 *               action ('none', 'save', 'disable' or 'forget'), ask (whether
 *               the provider is to check the account), password_effective
 *               (the typed or the stored one), test_to (E.164 digits or ''),
 *               marks (field => sentence), notices
 */
function pg_sms_settings_decide($post, $provider, $before, $refusal)
{
    $name = 'sms_' . $provider . '_';

    $decision = array(
        'provider'           => $provider,
        'user'               => pg_sms_post($post, $name . 'user'),
        'password'           => pg_sms_post($post, $name . 'password'),
        'sender'             => pg_sms_post($post, $name . 'sender'),
        'enabled'            => !empty($post['sms_enabled']),
        'action'             => 'none',
        'ask'                => false,
        'password_effective' => '',
        'test_to'            => '',
        'marks'              => array(),
        'notices'            => array(),
    );

    // The test number is read only when its button was pressed. Enter in any
    // field of the pane presses it too (it is the form's only submit button),
    // so an empty number is a plain save.
    if (!empty($post['sms_test']) && (pg_sms_post($post, 'sms_test_to') !== '')) {
        $decision['test_to'] = pg_sms_number(pg_sms_post($post, 'sms_test_to'));

        if ($decision['test_to'] === '') {
            $decision['marks']['sms_test_to'] = lang('This is not a mobile number an SMS can be sent to.');
        }
    }

    $stored = ($before['user'] !== '') || $before['password_stored'];

    if (!$decision['enabled'] && ($decision['user'] === '')) {
        // Off with no user code: the stored account, if any, is removed.
        $decision['action'] = $stored ? 'forget' : 'none';
        return $decision;
    }

    $unchanged = ($decision['user'] === $before['user'])
        && ($decision['password'] === '')
        && ($decision['sender'] === $before['sender']);

    if ($unchanged && ($decision['enabled'] === $before['enabled'])) {
        return $decision;
    }

    // Switching off writes the switch alone, which needs no sealing.
    if ($unchanged && !$decision['enabled']) {
        $decision['action'] = 'disable';
        return $decision;
    }

    if ($refusal !== '') {
        if ($decision['enabled']) {
            $decision['marks']['sms_enabled'] = $refusal;
        } else {
            $decision['action'] = $before['enabled'] ? 'disable' : 'none';
            $decision['notices'][] = lang(array('string' => 'The SMS account was not saved: {var:1}', 'vars' => array($refusal)));
        }

        return $decision;
    }

    $decision['action'] = 'save';

    if (!$decision['enabled']) {
        return $decision;
    }

    $decision['password_effective'] = ($decision['password'] !== '') ? $decision['password'] : $before['password'];

    if ($decision['user'] === '') {
        $decision['marks'][$name . 'user'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('User Code'))));
    }

    if ($decision['password_effective'] === '') {
        $decision['marks'][$name . 'password'] = $before['password_stored']
            ? lang('SMS is switched on but cannot send: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Password'))));
    }

    if ($decision['sender'] === '') {
        $decision['marks'][$name . 'sender'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Sender Name'))));
    } elseif (!pg_sms_sender_valid($decision['sender'])) {
        $decision['marks'][$name . 'sender'] = lang('A sender name has at most 11 characters.');
    }

    $decision['ask'] = !$decision['marks'];

    return $decision;
}

/**
 * Checks the SMS card's fields before the Communication settings are
 * written, and asks the provider about a changed account. A refusal is marked
 * on its field, so nothing of the category is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array|null what pg_sms_settings_save() writes; null when the
 *                    posted pane had no SMS card
 */
function pg_sms_settings_check($post, $liveform)
{
    $provider = pg_sms_settings_posted_provider($post);

    if ($provider === null) {
        return null;
    }

    $refusal = '';

    if (!pg_connect_schema_ready()) {
        $refusal = lang('The database has not been upgraded yet. Run the software update first.');
    } elseif (!pg_connect_can_seal()) {
        $refusal = lang('This site has no encryption key, so it cannot keep the password of an SMS account.');
    }

    $decision = pg_sms_settings_decide($post, $provider, pg_sms_app($provider), $refusal);

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    // A category another card already refused is not written, so the
    // provider is not kept waiting for.
    if (!$decision['ask'] || $liveform->check_form_errors()) {
        return $decision;
    }

    $name = 'sms_' . $provider . '_';
    $label = h(pg_sms_provider_definition($provider)['label']);

    $check = pg_sms_senders($provider, array(
        'user'     => $decision['user'],
        'password' => $decision['password_effective'],
        'sender'   => $decision['sender']));

    if ($check['ok']) {
        $approved = pg_sms_sender_match($decision['sender'], $check['senders']);

        if ($approved !== '') {
            $decision['sender'] = $approved;
        } elseif ($check['senders']) {
            $liveform->mark_error($name . 'sender', lang(array(
                'string' => 'The sender name {var:1} is not approved on this {var:2} account. Approved sender names: {var:3}',
                'vars'   => array(h($decision['sender']), $label, h(implode(', ', $check['senders']))))));
        } else {
            $liveform->mark_error($name . 'sender', lang(array(
                'string' => 'This {var:1} account has no approved sender name yet.',
                'vars'   => array($label))));
        }
    } elseif ($check['retry']) {
        // The provider did not answer: the account is kept unchecked rather
        // than the settings refused for an outage.
        $decision['notices'][] = lang(array(
            'string' => 'The SMS account was saved without being checked: {var:1}',
            'vars'   => array(h($check['error']))));
    } else {
        $liveform->mark_error(($check['code'] === '30') ? $name . 'password' : $name . 'user', h($check['error']));
    }

    return $decision;
}

/**
 * Writes what pg_sms_settings_check() decided, then sends the test message
 * when its button was pressed. Runs after the Communication settings were
 * written, and only when nothing was refused.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_sms_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision)) {
        return;
    }

    $key = pg_sms_app_key($decision['provider']);

    if ($decision['action'] === 'forget') {
        db("DELETE FROM connect_apps WHERE provider = '" . e($key) . "'");
    } elseif ($decision['action'] === 'disable') {
        db("UPDATE connect_apps
            SET
                enabled = 0,
                updated_user_id = '" . (int) $user_id . "',
                updated_timestamp = UNIX_TIMESTAMP()
            WHERE provider = '" . e($key) . "'");
    } elseif ($decision['action'] === 'save') {
        pg_connect_app_save(
            $key,
            $decision['user'],
            ($decision['password'] !== '') ? $decision['password'] : null,
            array('sender' => $decision['sender']),
            $decision['enabled'],
            $user_id);

        // One provider sends: another one's account stays stored, off.
        if ($decision['enabled']) {
            foreach (array_keys(pg_sms_providers()) as $provider) {
                if (($provider !== $decision['provider']) && pg_sms_app($provider)['enabled']) {
                    db("UPDATE connect_apps SET enabled = 0 WHERE provider = '" . e(pg_sms_app_key($provider)) . "'");
                }
            }
        }
    }

    foreach ($decision['notices'] as $notice) {
        $liveform->add_notice($notice);
    }

    if ($decision['test_to'] !== '') {
        pg_sms_settings_test($decision['test_to'], $liveform);
    }
}

/**
 * Sends the settings' test message.
 *
 * @param string   $number pg_sms_number()
 * @param liveform $liveform
 */
function pg_sms_settings_test($number, $liveform)
{
    $provider = pg_sms_provider();

    if ($provider === '') {
        $liveform->add_notice(lang('The test message was not sent: SMS is not set up.'));
        return;
    }

    // Every test is a paid message: a ceiling per site and clock hour,
    // counted in the firewall's rate buckets (fails open when they are not
    // there). Every press counts, a refused one too.
    if (function_exists('waf_rate_exceeded') && isset(db::$con) && db::$con
        && waf_rate_exceeded('site', 'sms-test', 10, 3600)) {
        $liveform->add_notice(lang('The test message was not sent: the limit of ten tests an hour was reached. Try again in the next hour.'));
        return;
    }

    $result = pg_sms_send($number, lang(array(
        'string' => 'Test message from {var:1}. SMS sending works.',
        'vars'   => array(HOSTNAME_SETTING))), 'test');

    if ($result['ok']) {
        $liveform->add_notice(lang(array(
            'string' => '{var:1} accepted the test message to {var:2} (job {var:3}). It may take a minute to arrive.',
            'vars'   => array(h(pg_sms_provider_definition($provider)['label']), '+' . $number, h($result['id'])))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'The test message was not sent: {var:1}',
            'vars'   => array(h($result['error'])))));
    }
}
