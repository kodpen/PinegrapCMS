<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * WhatsApp through the site's own WhatsApp Business account on Meta's Cloud
 * API: the account, the Communication › WhatsApp settings card with its
 * check and test message, and the webhook address Meta calls.
 *
 * The site connects with its own Meta app and a system user token that never
 * expires; no Kodpen app stands in between. The account is kept in
 * connect_apps under 'whatsapp', like the SMS providers' accounts: the App ID
 * as client_id; the App secret (client_secret), the access token and the
 * webhook's verify token sealed together with the site's key and never drawn
 * back into a form; the phone number id, the WhatsApp Business account id and
 * what the last check read among the options; the card's switch as enabled.
 * Nothing here needs a schema change.
 *
 * Outside the 24 hours after a person's own message WhatsApp takes only
 * approved templates, so every message the site sends is a template. The
 * driver is includes/whatsapp/cloud_api.php, loaded only when a check or a
 * message needs it.
 *
 * whatsapp_webhook.php answers Meta's verify call (the verify token shown on
 * the card) and accepts a POST only when its X-Hub-Signature-256 is the
 * body's HMAC with the app secret; what the POST says is read by
 * includes/fn/whatsapp_webhook.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_offer.php');

// The template the settings' test message sends: Meta's sample, which a new
// WhatsApp Business account has.
define('PG_WHATSAPP_TEST_TEMPLATE', 'hello_world');
define('PG_WHATSAPP_TEST_LANGUAGE', 'en_US');

// Tests a site may send in a clock hour; every one is a paid message.
define('PG_WHATSAPP_TESTS_PER_HOUR', 10);

// The largest webhook body read; Meta's limit is 3 MB.
define('PG_WHATSAPP_WEBHOOK_MAX_BODY', 3145728);

// Webhook calls one address may make a minute.
define('PG_WHATSAPP_WEBHOOK_PER_MINUTE', 600);

/**
 * The connect_apps key the account is kept under.
 *
 * @return string
 */
function pg_whatsapp_app_key()
{
    return 'whatsapp';
}

/**
 * Loads the Cloud API driver.
 *
 * @return void
 */
function pg_whatsapp_driver()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');
}

/**
 * The account on this site.
 *
 * @return array stored (a row exists), app_id, app_secret, token,
 *               verify_token ('' when none or when the site's key cannot read
 *               them), secrets_stored, secrets_unreadable (stored but this
 *               site's key cannot open them), phone_number_id, account_id,
 *               display_phone_number, verified_name, quality_rating, checked
 *               (when the account was last checked with Meta, 0 for never),
 *               enabled
 */
function pg_whatsapp_app()
{
    $app = array(
        'stored'               => false,
        'app_id'               => '',
        'app_secret'           => '',
        'token'                => '',
        'verify_token'         => '',
        'secrets_stored'       => false,
        'secrets_unreadable'   => false,
        'phone_number_id'      => '',
        'account_id'           => '',
        'display_phone_number' => '',
        'verified_name'        => '',
        'quality_rating'       => '',
        'checked'              => 0,
        'enabled'              => false,
    );

    $row = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready())
        ? db_item("SELECT client_id, secret, options, enabled FROM connect_apps WHERE provider = '" . e(pg_whatsapp_app_key()) . "'")
        : null;

    if (!is_array($row)) {
        return $app;
    }

    $secrets = pg_connect_unseal($row['secret']);
    $options = json_decode((string) $row['options'], true);
    $options = is_array($options) ? $options : array();

    $app['stored'] = true;
    $app['app_id'] = (string) $row['client_id'];
    $app['enabled'] = ((int) $row['enabled'] === 1);
    $app['secrets_stored'] = ((string) $row['secret'] !== '');
    $app['secrets_unreadable'] = $app['secrets_stored'] && !$secrets;

    foreach (array('app_secret' => 'client_secret', 'token' => 'access_token', 'verify_token' => 'verify_token') as $name => $sealed) {
        $app[$name] = (isset($secrets[$sealed]) && is_string($secrets[$sealed])) ? $secrets[$sealed] : '';
    }

    foreach (array('phone_number_id', 'account_id', 'display_phone_number', 'verified_name', 'quality_rating') as $name) {
        $app[$name] = (isset($options[$name]) && is_scalar($options[$name])) ? (string) $options[$name] : '';
    }

    $app['checked'] = isset($options['checked']) ? (int) $options['checked'] : 0;

    return $app;
}

/**
 * Whether an account has everything a message needs.
 *
 * @param array $app pg_whatsapp_app()
 * @return bool
 */
function pg_whatsapp_app_complete($app)
{
    foreach (array('app_id', 'app_secret', 'token', 'phone_number_id', 'account_id') as $name) {
        if ((string) $app[$name] === '') {
            return false;
        }
    }

    return true;
}

/**
 * Whether the site can send WhatsApp messages.
 *
 * @return bool
 */
function pg_whatsapp_ready()
{
    $app = pg_whatsapp_app();

    return $app['enabled'] && pg_whatsapp_app_complete($app);
}

/**
 * A Graph API call. Meta's address is fixed in the driver, never typed by
 * anyone, so this is a fixed-host call (pg_curl_run()) with the certificate
 * checks the SMS providers use; a redirect is not followed. The cURL error
 * number, a proxy's answer to the tunnel and the bytes that went out tell
 * whether the request can have reached Meta (pg_sms_http_never_sent()).
 *
 * @param string $method
 * @param array  $request url, headers, body (optional)
 * @return array status (0 when nothing came back), body, error, errno,
 *               connect, request_size
 */
function pg_whatsapp_http($method, $request)
{
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => '', 'error' => lang('cURL is not available on this server.'), 'errno' => 2, 'connect' => 0, 'request_size' => 0);
    }

    $options = array(
        'headers'         => (array) $request['headers'],
        'timeout'         => 20,
        'connect_timeout' => 10,
        'user_agent'      => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+whatsapp)',
    );

    if (isset($request['body']) && ((string) $request['body'] !== '')) {
        $options['body'] = (string) $request['body'];
    }

    $answer = pg_curl_run((string) $method, (string) $request['url'], $options);

    if ($answer['errno'] !== 0) {
        return array(
            'status'       => 0,
            'body'         => '',
            'error'        => trim($answer['error'] . pg_curl_tls_hint($answer['errno'])),
            'errno'        => $answer['errno'],
            'connect'      => $answer['connect'],
            'request_size' => $answer['request_size'],
        );
    }

    return array('status' => $answer['status'], 'body' => $answer['body'], 'error' => '', 'errno' => 0, 'connect' => $answer['connect'], 'request_size' => $answer['request_size']);
}

/**
 * Whether a typed id can be one of Meta's (app, phone number, account): digits.
 *
 * @param string $id
 * @return bool
 */
function pg_whatsapp_id_valid($id)
{
    return (bool) preg_match('/^[0-9]{5,25}$/D', (string) $id);
}

/**
 * Whether a typed secret (app secret, access token) can be one: printable
 * characters without spaces, as copied.
 *
 * @param string $secret
 * @param int    $min shortest length
 * @return bool
 */
function pg_whatsapp_secret_valid($secret, $min)
{
    $secret = (string) $secret;

    return (strlen($secret) >= (int) $min) && (strlen($secret) <= 4096) && (bool) preg_match('/^[\x21-\x7E]+$/D', $secret);
}

/**
 * A new verify token for the webhook address.
 *
 * @return string 40 hexadecimal characters
 */
function pg_whatsapp_verify_token_new()
{
    return bin2hex(random_bytes(20));
}

/**
 * The address Meta posts the account's events to.
 *
 * @return string
 */
function pg_whatsapp_webhook_url()
{
    // init.php defines the site's address; without it (a unit test run) the
    // address is relative.
    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY : '';

    return $base . '/whatsapp_webhook.php';
}

/**
 * The quality rating as the card says it.
 *
 * @param string $rating GREEN, YELLOW, RED, NA or ''
 * @return string
 */
function pg_whatsapp_quality_label($rating)
{
    $labels = array(
        'GREEN'  => lang('high'),
        'YELLOW' => lang('medium'),
        'RED'    => lang('low'),
    );

    return isset($labels[(string) $rating]) ? $labels[(string) $rating] : lang('not rated yet');
}

/**
 * Why no WhatsApp account can be set up on this site now, '' when one can. A
 * demonstration site, which anyone can sign in to, takes none.
 *
 * @return string
 */
function pg_whatsapp_settings_refusal()
{
    if (function_exists('pg_demo') && pg_demo()) {
        return lang('This is a demonstration site: no WhatsApp account can be set up here.');
    }

    if (!pg_connect_schema_ready()) {
        return lang('The database has not been upgraded yet. Run the software update first.');
    }

    if (!pg_connect_can_seal()) {
        return lang('This site has no encryption key, so it cannot keep the secrets of a WhatsApp account.');
    }

    return '';
}

/**
 * The fields of the WhatsApp card, for the signed-in person.
 *
 * @return string
 */
function pg_whatsapp_settings_fields()
{
    $number = '';

    if (defined('USER_CONTACT_ID') && USER_CONTACT_ID) {
        $mobile = (string) db_value("SELECT mobile_phone FROM contacts WHERE id = '" . (int) USER_CONTACT_ID . "'");
        $digits = pg_sms_number($mobile);
        $number = ($digits !== '') ? '+' . $digits : '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');

    return pg_whatsapp_settings_html(pg_whatsapp_app(), array(
        'refusal'      => pg_whatsapp_settings_refusal(),
        'webhook_url'  => pg_whatsapp_webhook_url(),
        'test_to'      => $number,
        'channel_html' => pg_whatsapp_channel_field(defined('USER_ID') ? (int) USER_ID : 0),
    ));
}

/**
 * A numbered step's heading cell: the name and what to do at Meta.
 *
 * @param int    $number
 * @param string $title
 * @param string $help  HTML
 * @return string
 */
function pg_whatsapp_settings_step($number, $title, $help)
{
    return '
                                                        <div class="col-12">
                                                            <h6>' . (int) $number . '. ' . h($title) . '</h6>
                                                            <div class="form-text">' . $help . '</div>
                                                        </div>';
}

/**
 * A link to one of Meta's screens, for a step's help line.
 *
 * @param string $label
 * @param string $url
 * @return string HTML
 */
function pg_whatsapp_settings_link($label, $url)
{
    return '<a href="' . h($url) . '" target="_blank" rel="noopener">' . h($label) . '</a>';
}

/**
 * The card's markup: the switch and, under it, the warning, the state of the
 * connection, the five numbered steps of the setup and the test message. No
 * secret is drawn back.
 *
 * @param array $app     pg_whatsapp_app()
 * @param array $context refusal (pg_whatsapp_settings_refusal()), webhook_url,
 *                       test_to (the signed-in person's mobile number, '' for
 *                       none), channel_html (the Workspace's Channel field,
 *                       optional: drawn as step 6 when given)
 * @return string
 */
function pg_whatsapp_settings_html($app, $context)
{
    $enabled = $app['enabled'];
    $warning = (string) $context['refusal'];

    if (($warning === '') && $enabled && $app['secrets_unreadable']) {
        $warning = lang('WhatsApp is switched on but cannot send: the stored App secret and access token cannot be read with this site\'s encryption key. Enter them again.');
    } elseif (($warning === '') && $enabled && !pg_whatsapp_app_complete($app)) {
        $warning = lang('WhatsApp is switched on but cannot send until every step below is filled in.');
    }

    $stored = ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"';
    $secret_placeholder = ($app['app_secret'] !== '') ? $stored : '';
    $token_placeholder = ($app['token'] !== '') ? $stored : '';

    $html = '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="whatsapp_enabled" id="whatsapp_enabled" value="1"' . ($enabled ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#whatsapp_row"/></div>
                                                <label class="" for="whatsapp_enabled">' . lang('Send WhatsApp messages') . '</label><div class="form-text">' . lang('Messages through the site\'s own WhatsApp Business account on Meta\'s Cloud API: templates WhatsApp approved, to one person at a time. E-mails go out as before. To remove the account from this site, switch WhatsApp off and empty the App ID.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="whatsapp_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">';

    if ($warning !== '') {
        $html .= '
                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . h($warning) . '</div></div>';
    }

    if ($enabled && pg_whatsapp_app_complete($app) && ($app['checked'] > 0) && ($app['display_phone_number'] !== '')) {
        $html .= '
                                                        <div class="col-12 form-text">' . lang(array(
                                                            'string' => 'Connected: {var:1}, {var:2}, quality {var:3}. Checked with Meta on {var:4}.',
                                                            'vars'   => array(h($app['verified_name']), h($app['display_phone_number']), h(pg_whatsapp_quality_label($app['quality_rating'])), h(date('Y-m-d H:i', $app['checked']))))) . '</div>';
    } elseif ($enabled && pg_whatsapp_app_complete($app)) {
        $html .= '
                                                        <div class="col-12 form-text">' . lang('Saved without being checked with Meta. The test message below shows whether it works.') . '</div>';
    }

    $html .= pg_whatsapp_settings_step(1, lang('Meta app'), lang(array(
        'string' => 'In {var:1}, create an app of the Business type and add WhatsApp to it. Copy its App ID and App secret from App settings › Basic.',
        'vars'   => array(pg_whatsapp_settings_link('Meta for Developers', 'https://developers.facebook.com/apps/')))));

    $html .= '
                                                        <div class="pg-f-sm">
                                                            <label for="whatsapp_app_id" class="form-label">' . lang('App ID') . '</label>
                                                            <input type="text" name="whatsapp_app_id" id="whatsapp_app_id" class="form-control" value="' . h($app['app_id']) . '" maxlength="25" inputmode="numeric" autocomplete="off" spellcheck="false"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="whatsapp_app_secret" class="form-label">' . lang('App Secret') . '</label>
                                                            <input type="password" name="whatsapp_app_secret" id="whatsapp_app_secret" class="form-control" value="" maxlength="255" autocomplete="new-password"' . $secret_placeholder . '/>
                                                        </div>';

    $html .= pg_whatsapp_settings_step(2, lang('Permanent access token'), lang(array(
        'string' => 'In {var:1} › System users, add a system user and assign it the app and the WhatsApp account. Then press Generate token: choose the app, Never as the expiry, and the permissions whatsapp_business_messaging, whatsapp_business_management and business_management.',
        'vars'   => array(pg_whatsapp_settings_link(lang('Business settings'), 'https://business.facebook.com/settings/system-users')))));

    $html .= '
                                                        <div class="pg-f-lg">
                                                            <label for="whatsapp_token" class="form-label">' . lang('Access Token') . '</label>
                                                            <input type="password" name="whatsapp_token" id="whatsapp_token" class="form-control" value="" maxlength="4096" autocomplete="new-password"' . $token_placeholder . '/>
                                                        </div>';

    $html .= pg_whatsapp_settings_step(3, lang('Phone number'), lang('In the app\'s WhatsApp › API Setup, add and verify the business number messages go out from, then copy its Phone number ID (not the number itself).'));

    $html .= '
                                                        <div class="pg-f-sm">
                                                            <label for="whatsapp_phone_number_id" class="form-label">' . lang('Phone Number ID') . '</label>
                                                            <input type="text" name="whatsapp_phone_number_id" id="whatsapp_phone_number_id" class="form-control" value="' . h($app['phone_number_id']) . '" maxlength="25" inputmode="numeric" autocomplete="off" spellcheck="false"/>
                                                        </div>';

    $html .= pg_whatsapp_settings_step(4, lang('WhatsApp Business account'), lang('On the same page, copy the WhatsApp Business Account ID. Saving with WhatsApp switched on checks the four steps with Meta.'));

    $html .= '
                                                        <div class="pg-f-sm">
                                                            <label for="whatsapp_account_id" class="form-label">' . lang('WhatsApp Business Account ID') . '</label>
                                                            <input type="text" name="whatsapp_account_id" id="whatsapp_account_id" class="form-control" value="' . h($app['account_id']) . '" maxlength="25" inputmode="numeric" autocomplete="off" spellcheck="false"/>
                                                        </div>';

    if ($app['verify_token'] === '') {
        $html .= pg_whatsapp_settings_step(5, lang('Webhook'), lang('The callback URL and verify token to enter in the app appear here once the account is saved.'));
    } else {
        $https = (strncmp((string) $context['webhook_url'], 'https://', 8) === 0);
        $help = lang('In the app\'s WhatsApp › Configuration, enter this callback URL and verify token, press Verify and save, then subscribe to the fields messages and message_template_status_update. Delivery reports, template approvals and replies then reach this site.');

        if (!$https) {
            $help .= ' ' . lang('Meta calls only an https address: this site needs a certificate first.');
        }

        $html .= pg_whatsapp_settings_step(5, lang('Webhook'), $help) . '
                                                        <div class="col-12">
                                                            <label for="whatsapp_webhook_url" class="form-label">' . lang('Callback URL') . '</label>
                                                            <input type="text" id="whatsapp_webhook_url" class="form-control" value="' . h($context['webhook_url']) . '" readonly="readonly"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="whatsapp_verify_token" class="form-label">' . lang('Verify Token') . '</label>
                                                            <input type="text" id="whatsapp_verify_token" class="form-control" value="' . h($app['verify_token']) . '" readonly="readonly"/>
                                                        </div>';
    }

    if ((string) ($context['channel_html'] ?? '') !== '') {
        $html .= pg_whatsapp_settings_step(6, lang('Conversations'), lang('Where customers\' WhatsApp messages are read and handled in the Workspace.')) . $context['channel_html'];
    }

    $html .= '
                                                        <div class="pg-f-sm">
                                                            <label for="whatsapp_test_to" class="form-label">' . lang('Test Number') . '</label>
                                                            <input type="tel" name="whatsapp_test_to" id="whatsapp_test_to" class="form-control" value="' . h($context['test_to']) . '" maxlength="30" autocomplete="off" inputmode="tel"/>
                                                            <div class="form-text">' . lang(array(
                                                                'string' => 'A mobile number with WhatsApp, yours to begin with. The test is Meta\'s sample template {var:1} in English, which a new WhatsApp Business account has; it goes out at once and Meta charges it.',
                                                                'vars'   => array('<code>' . h(PG_WHATSAPP_TEST_TEMPLATE) . '</code>'))) . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="submit" name="whatsapp_test" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>' . lang('Save and send a test message') . '</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    return $html;
}

/**
 * A posted text field, '' when absent or not text.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_whatsapp_post($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
}

/**
 * What a post of the WhatsApp card asks for, decided from the post and the
 * stored account alone: no database, no Meta. pg_whatsapp_settings_check()
 * marks what this refuses and asks Meta when this says so.
 *
 * Only a change is written: an account left as it was is not written again,
 * whatever else the Communication pane saves, so an account this site can no
 * longer read does not stop the other cards from saving.
 *
 * @param array  $post
 * @param array  $before    pg_whatsapp_app()
 * @param string $refusal   pg_whatsapp_settings_refusal()
 * @param string $new_token a verify token for an account that has none
 * @return array enabled, app_id, app_secret and token (as typed),
 *               phone_number_id, account_id, action ('none', 'save',
 *               'disable' or 'forget'), ask (whether Meta is to check the
 *               account), app_secret_effective and token_effective (typed
 *               or stored), verify_token, test_to (E.164 digits or ''),
 *               marks (field => sentence), notices
 */
function pg_whatsapp_settings_decide($post, $before, $refusal, $new_token)
{
    // Ids are copied with spaces now and then; they are digits.
    $id = function ($name) use ($post) {
        return (string) preg_replace('/\s+/u', '', pg_whatsapp_post($post, $name));
    };

    $decision = array(
        'enabled'              => !empty($post['whatsapp_enabled']),
        'app_id'               => $id('whatsapp_app_id'),
        'app_secret'           => pg_whatsapp_post($post, 'whatsapp_app_secret'),
        'token'                => pg_whatsapp_post($post, 'whatsapp_token'),
        'phone_number_id'      => $id('whatsapp_phone_number_id'),
        'account_id'           => $id('whatsapp_account_id'),
        'action'               => 'none',
        'ask'                  => false,
        'app_secret_effective' => '',
        'token_effective'      => '',
        'verify_token'         => ($before['verify_token'] !== '') ? $before['verify_token'] : (string) $new_token,
        'test_to'              => '',
        'marks'                => array(),
        'notices'              => array(),
    );

    // The test number is read only when its button was pressed: Enter in a
    // field of the pane presses the form's first submit button, which is a
    // hidden plain save (settings-card-own-button.md).
    if (!empty($post['whatsapp_test']) && (pg_whatsapp_post($post, 'whatsapp_test_to') !== '')) {
        $decision['test_to'] = pg_sms_number(pg_whatsapp_post($post, 'whatsapp_test_to'));

        if ($decision['test_to'] === '') {
            $decision['marks']['whatsapp_test_to'] = lang('This is not a mobile number a message can be sent to.');
        }
    }

    if (!$decision['enabled'] && ($decision['app_id'] === '')) {
        // Off with no App ID: the stored account, if any, is removed.
        $decision['action'] = $before['stored'] ? 'forget' : 'none';
        return $decision;
    }

    $unchanged = ($decision['app_id'] === $before['app_id'])
        && ($decision['app_secret'] === '')
        && ($decision['token'] === '')
        && ($decision['phone_number_id'] === $before['phone_number_id'])
        && ($decision['account_id'] === $before['account_id']);

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
            $decision['marks']['whatsapp_enabled'] = $refusal;
        } else {
            $decision['action'] = $before['enabled'] ? 'disable' : 'none';
            $decision['notices'][] = lang(array('string' => 'The WhatsApp account was not saved: {var:1}', 'vars' => array($refusal)));
        }

        return $decision;
    }

    $decision['action'] = 'save';
    $decision['app_secret_effective'] = ($decision['app_secret'] !== '') ? $decision['app_secret'] : $before['app_secret'];
    $decision['token_effective'] = ($decision['token'] !== '') ? $decision['token'] : $before['token'];

    if (!$decision['enabled']) {
        return $decision;
    }

    $required = function ($label) {
        return lang(array('string' => '{var:1} is required.', 'vars' => array(h($label))));
    };
    $unreadable = lang('The stored value cannot be read with this site\'s encryption key. Enter it again.');

    foreach (array('whatsapp_app_id' => array('app_id', lang('App ID')), 'whatsapp_phone_number_id' => array('phone_number_id', lang('Phone Number ID')), 'whatsapp_account_id' => array('account_id', lang('WhatsApp Business Account ID'))) as $field => $named) {
        if ($decision[$named[0]] === '') {
            $decision['marks'][$field] = $required($named[1]);
        } elseif (!pg_whatsapp_id_valid($decision[$named[0]])) {
            $decision['marks'][$field] = lang(array('string' => '{var:1} is a number of digits, as Meta shows it.', 'vars' => array(h($named[1]))));
        }
    }

    if ($decision['app_secret_effective'] === '') {
        $decision['marks']['whatsapp_app_secret'] = $before['secrets_unreadable'] ? $unreadable : $required(lang('App Secret'));
    } elseif (!pg_whatsapp_secret_valid($decision['app_secret_effective'], 16)) {
        $decision['marks']['whatsapp_app_secret'] = lang('This is not an App secret: copy it again from App settings › Basic.');
    }

    if ($decision['token_effective'] === '') {
        $decision['marks']['whatsapp_token'] = $before['secrets_unreadable'] ? $unreadable : $required(lang('Access Token'));
    } elseif (!pg_whatsapp_secret_valid($decision['token_effective'], 20)) {
        $decision['marks']['whatsapp_token'] = lang('This is not an access token: copy it again, without spaces.');
    }

    $decision['ask'] = !$decision['marks'];

    return $decision;
}

/**
 * Whether a post has the WhatsApp card.
 *
 * @param array $post
 * @return bool
 */
function pg_whatsapp_settings_posted($post)
{
    return isset($post['whatsapp_app_id']);
}

/**
 * Checks the WhatsApp card's fields before the Communication settings are
 * written, and asks Meta about a changed account. A refusal is marked on its
 * field, so nothing of the category is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array|null what pg_whatsapp_settings_save() writes; null when the
 *                    posted pane had no WhatsApp card
 */
function pg_whatsapp_settings_check($post, $liveform)
{
    if (!pg_whatsapp_settings_posted($post)) {
        return null;
    }

    $decision = pg_whatsapp_settings_decide($post, pg_whatsapp_app(), pg_whatsapp_settings_refusal(), pg_whatsapp_verify_token_new());
    $decision['info'] = null;

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    // A category another card already refused is not written, so Meta is
    // not kept waiting for.
    if (!$decision['ask'] || $liveform->check_form_errors()) {
        return $decision;
    }

    pg_whatsapp_driver();

    $check = pg_whatsapp_cloud_check(array(
        'app_id'          => $decision['app_id'],
        'app_secret'      => $decision['app_secret_effective'],
        'token'           => $decision['token_effective'],
        'phone_number_id' => $decision['phone_number_id'],
        'account_id'      => $decision['account_id']));

    if ($check['ok']) {
        $decision['info'] = $check['info'];
    } elseif ($check['refused']) {
        $liveform->mark_error('whatsapp_' . $check['field'], $check['error']);
    } else {
        // Meta could not be asked (no answer, an unreadable one, an outage):
        // the account is kept unchecked rather than the settings refused.
        $decision['notices'][] = lang(array(
            'string' => 'The WhatsApp account was saved without being checked: {var:1}',
            'vars'   => array($check['error'])));
    }

    return $decision;
}

/**
 * Writes the account row: the App ID, the three secrets sealed together, the
 * ids and what the check read.
 *
 * @param array    $decision pg_whatsapp_settings_check()
 * @param int      $user_id
 * @return bool false when the site has no encryption key
 */
function pg_whatsapp_app_save($decision, $user_id)
{
    if (!pg_connect_can_seal()) {
        return false;
    }

    $key = pg_whatsapp_app_key();
    $existing = db_item("SELECT secret FROM connect_apps WHERE provider = '" . e($key) . "'");
    $secrets = is_array($existing) ? pg_connect_unseal($existing['secret']) : array();

    $secrets['client_secret'] = $decision['app_secret_effective'];
    $secrets['access_token'] = $decision['token_effective'];
    $secrets['verify_token'] = $decision['verify_token'];

    // An empty value is not kept: a stored one this key cannot open stays
    // unreadable rather than turning into an empty secret.
    $secrets = array_filter($secrets, function ($value) {
        return is_string($value) && ($value !== '');
    });

    $info = is_array($decision['info']) ? $decision['info'] : array();

    $options = array(
        'phone_number_id'      => $decision['phone_number_id'],
        'account_id'           => $decision['account_id'],
        'display_phone_number' => isset($info['display_phone_number']) ? (string) $info['display_phone_number'] : '',
        'verified_name'        => isset($info['verified_name']) ? (string) $info['verified_name'] : '',
        'quality_rating'       => isset($info['quality_rating']) ? (string) $info['quality_rating'] : '',
        'checked'              => $info ? time() : 0,
    );

    $columns = "client_id = '" . e($decision['app_id']) . "',
        secret = '" . e($secrets ? pg_connect_seal($secrets) : '') . "',
        options = '" . e(json_encode($options)) . "',
        enabled = '" . ($decision['enabled'] ? 1 : 0) . "',
        updated_user_id = '" . (int) $user_id . "',
        updated_timestamp = UNIX_TIMESTAMP()";

    if (is_array($existing)) {
        db("UPDATE connect_apps SET " . $columns . " WHERE provider = '" . e($key) . "'");
    } else {
        db("INSERT INTO connect_apps SET provider = '" . e($key) . "', " . $columns);
    }

    return true;
}

/**
 * Writes what pg_whatsapp_settings_check() decided, then sends the test
 * message when its button was pressed. Runs after the Communication settings
 * were written, and only when nothing was refused.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_whatsapp_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision)) {
        return;
    }

    $key = pg_whatsapp_app_key();

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
        pg_whatsapp_app_save($decision, $user_id);
    }

    foreach ($decision['notices'] as $notice) {
        $liveform->add_notice($notice);
    }

    if ($decision['test_to'] !== '') {
        pg_whatsapp_settings_test($decision['test_to'], $liveform);
    }
}

/**
 * Sends the settings' test message: Meta's sample template.
 *
 * @param string   $number pg_sms_number()
 * @param liveform $liveform
 */
function pg_whatsapp_settings_test($number, $liveform)
{
    $refusal = pg_whatsapp_settings_refusal();

    if ($refusal !== '') {
        $liveform->add_notice(lang(array('string' => 'The test message was not sent: {var:1}', 'vars' => array($refusal))));
        return;
    }

    $app = pg_whatsapp_app();

    if (!$app['enabled'] || !pg_whatsapp_app_complete($app)) {
        $liveform->add_notice(lang('The test message was not sent: WhatsApp is not set up.'));
        return;
    }

    // Every test is a paid message: a ceiling per site and clock hour, in
    // the firewall's rate buckets (fails open when they are not there).
    if (function_exists('waf_rate_exceeded') && isset(db::$con) && db::$con
        && waf_rate_exceeded('site', 'whatsapp-test', PG_WHATSAPP_TESTS_PER_HOUR, 3600)) {
        $liveform->add_notice(lang('The test message was not sent: the limit of ten tests an hour was reached. Try again in the next hour.'));
        return;
    }

    pg_whatsapp_driver();

    $result = pg_whatsapp_cloud_send($app, $number, PG_WHATSAPP_TEST_TEMPLATE, PG_WHATSAPP_TEST_LANGUAGE);

    if ($result['ok']) {
        $liveform->add_notice(lang(array(
            'string' => 'Meta accepted the test message to {var:1}. It should arrive in that number\'s WhatsApp within a minute.',
            'vars'   => array('+' . $number))));
    } elseif ($result['uncertain']) {
        $liveform->add_notice(lang(array(
            'string' => 'Meta may have the test message to {var:1}, but its answer did not say so: {var:2}',
            'vars'   => array('+' . $number, $result['error']))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'The test message was not sent: {var:1}',
            'vars'   => array($result['error']))));
    }
}


/* ---------------------------------------------------------------------------
   The webhook address (whatsapp_webhook.php)
   --------------------------------------------------------------------------- */

/**
 * The answer to Meta's verify call. PHP reads hub.mode, hub.verify_token and
 * hub.challenge as hub_mode, hub_verify_token and hub_challenge.
 *
 * @param array  $get    $_GET
 * @param string $stored the account's verify token, '' for none
 * @return array status (HTTP), body (the challenge when it is 200), wrong
 *               (a token was offered and it was not the account's)
 */
function pg_whatsapp_webhook_verify_answer($get, $stored)
{
    $read = function ($name) use ($get) {
        return (isset($get[$name]) && is_string($get[$name])) ? $get[$name] : '';
    };

    $offered = $read('hub_verify_token');
    $challenge = $read('hub_challenge');

    if (($read('hub_mode') !== 'subscribe') || ($offered === '')) {
        return array('status' => 400, 'body' => '', 'wrong' => false);
    }

    if (((string) $stored === '') || !hash_equals((string) $stored, $offered)) {
        return array('status' => 403, 'body' => '', 'wrong' => true);
    }

    // Meta's challenge is a number; nothing else is echoed back.
    if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/D', $challenge)) {
        return array('status' => 400, 'body' => '', 'wrong' => false);
    }

    return array('status' => 200, 'body' => $challenge, 'wrong' => false);
}

/**
 * Whether a POST's X-Hub-Signature-256 is the body's HMAC-SHA256 with the
 * app secret.
 *
 * @param string $raw    the body as it came
 * @param string $header the header's value, "sha256=<hex>"
 * @param string $secret the app secret
 * @return bool
 */
function pg_whatsapp_webhook_signed($raw, $header, $secret)
{
    $header = trim((string) $header);

    if (((string) $secret === '') || (strncmp($header, 'sha256=', 7) !== 0)) {
        return false;
    }

    return hash_equals(hash_hmac('sha256', (string) $raw, (string) $secret), strtolower(substr($header, 7)));
}

/**
 * Sends the webhook's answer and ends the request.
 *
 * @param int    $http
 * @param string $body
 * @param string $type
 */
function pg_whatsapp_webhook_answer($http, $body, $type = 'application/json')
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $http);
    header('Content-Type: ' . $type . '; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    echo $body;

    exit;
}

/**
 * Counts a wrong verify token or signature against the caller's address, as
 * the other webhook addresses do.
 */
function pg_whatsapp_webhook_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * Whether the caller's address made more calls this minute than Meta's
 * deliveries need.
 *
 * @return bool
 */
function pg_whatsapp_webhook_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    return ($ip !== '') && waf_ip_is_counted($ip) && waf_rate_exceeded(waf_ip_subject($ip), 'whatsapp_webhook', PG_WHATSAPP_WEBHOOK_PER_MINUTE, 60);
}

/**
 * Answers a call to whatsapp_webhook.php: Meta's verify call (GET) and its
 * signed event posts. A signed post's events are written down
 * (includes/fn/whatsapp_webhook.php) and it is answered 200, so Meta does
 * not send it again; a body that is not JSON is answered 200 as well, since
 * sending it again would not change it.
 */
function pg_whatsapp_webhook_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

    if (!in_array($method, array('GET', 'POST'), true)) {
        pg_whatsapp_webhook_answer(405, json_encode(array('status' => 'method_not_allowed')));
    }

    if (pg_whatsapp_webhook_throttled()) {
        pg_whatsapp_webhook_answer(429, json_encode(array('status' => 'rate_limited')));
    }

    $app = pg_whatsapp_app();

    if ($method === 'GET') {
        $answer = pg_whatsapp_webhook_verify_answer($_GET, $app['verify_token']);

        if ($answer['wrong']) {
            pg_whatsapp_webhook_offence();
        }

        pg_whatsapp_webhook_answer($answer['status'], $answer['body'], 'text/plain');
    }

    if ($app['app_secret'] === '') {
        pg_whatsapp_webhook_answer(403, json_encode(array('status' => 'off')));
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $body = ($length > PG_WHATSAPP_WEBHOOK_MAX_BODY) ? false : @file_get_contents('php://input', false, null, 0, PG_WHATSAPP_WEBHOOK_MAX_BODY + 1);

    if (($body === false) || (strlen($body) > PG_WHATSAPP_WEBHOOK_MAX_BODY)) {
        pg_whatsapp_webhook_answer(413, json_encode(array('status' => 'too_large')));
    }

    $signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? (string) $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';

    if (!pg_whatsapp_webhook_signed($body, $signature, $app['app_secret'])) {
        pg_whatsapp_webhook_offence();
        pg_whatsapp_webhook_answer(401, json_encode(array('status' => 'wrong_signature')));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');

    pg_whatsapp_webhook_apply(pg_whatsapp_webhook_events(json_decode($body, true), $app['phone_number_id']), $app);

    pg_whatsapp_webhook_answer(200, json_encode(array('ok' => true)));
}


/* ---------------------------------------------------------------------------
   The queue (whatsapp_outbox)
   --------------------------------------------------------------------------- */

// Meta's template categories, as a queued message records the one it had.
define('PG_WHATSAPP_CATEGORIES', 'MARKETING,UTILITY,AUTHENTICATION');

// The largest payload stored: the filled components of one template.
define('PG_WHATSAPP_PAYLOAD_MAX', 65535);

/**
 * Whether the 2026.11.0 upgrade has created whatsapp_outbox.
 *
 * @return bool
 */
function pg_whatsapp_queue_ready()
{
    return pg_schema_has('whatsapp_outbox');
}

/**
 * The row pg_whatsapp_enqueue() writes, from the caller's template and
 * options; no database.
 *
 * The number goes through the SMS layer's normalising (pg_sms_number()), so
 * a national form reads as a Turkish mobile as it does for a text message.
 * A reference is refused rather than dropped when it is not a name, since
 * dropping it would lose the guard against queueing the same message twice.
 *
 * @param mixed $to
 * @param array $template name, language, category (MARKETING, UTILITY or
 *                        AUTHENTICATION, as Meta has it), components (the
 *                        filled header, body and button parameters),
 *                        id (the template's row, 0 for none)
 * @param array $options  kind (what queued it: letters, digits and _, at most
 *                        16), reference, send_after, expires_at, contact_id
 * @param int   $now
 * @return array the columns of the row; error alone when something is refused
 */
function pg_whatsapp_queue_row($to, $template, $options, $now)
{
    $now = (int) $now;
    $template = is_array($template) ? $template : array();
    $options = is_array($options) ? $options : array();

    $text = function ($from, $name) {
        return (isset($from[$name]) && is_scalar($from[$name])) ? trim((string) $from[$name]) : '';
    };

    $number = pg_sms_number(is_scalar($to) ? (string) $to : '');

    if ($number === '') {
        return array('error' => lang('This is not a mobile number a WhatsApp message can be sent to.'));
    }

    $name = $text($template, 'name');

    if (!preg_match('/^[a-z0-9_]{1,512}$/D', $name)) {
        return array('error' => lang('A template name has only lower-case letters, digits and _.'));
    }

    $language = $text($template, 'language');

    if (!preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]{2,4})?$/D', $language)) {
        return array('error' => lang('The template language is not a code such as en_US or tr.'));
    }

    $category = strtoupper($text($template, 'category'));

    if (!in_array($category, explode(',', PG_WHATSAPP_CATEGORIES), true)) {
        return array('error' => lang('The template category is not marketing, utility or authentication.'));
    }

    $components = (isset($template['components']) && is_array($template['components'])) ? array_values($template['components']) : array();
    $payload = json_encode($components, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($payload) || (strlen($payload) > PG_WHATSAPP_PAYLOAD_MAX)) {
        return array('error' => lang('The template\'s variables could not be stored.'));
    }

    $kind = $text($options, 'kind');

    if (!preg_match('/^[a-z0-9_]{1,16}$/D', $kind)) {
        return array('error' => lang('The kind of a WhatsApp message has at most 16 lower-case letters, digits and _.'));
    }

    $reference = $text($options, 'reference');

    if (($reference !== '') && !preg_match('#^[A-Za-z0-9:._/-]{1,64}$#D', $reference)) {
        return array('error' => lang('The reference of a WhatsApp message has at most 64 letters, digits and the characters : . _ - /'));
    }

    $send_after = isset($options['send_after']) ? max($now, (int) $options['send_after']) : $now;
    $expires_at = isset($options['expires_at']) ? max(0, (int) $options['expires_at']) : 0;

    if (($expires_at > 0) && ($expires_at <= $send_after)) {
        return array('error' => lang('The message would expire before it could be sent.'));
    }

    return array(
        'created_at'        => $now,
        'send_after'        => $send_after,
        'expires_at'        => $expires_at,
        'status'            => 'queued',
        'kind'              => $kind,
        'recipient'         => $number,
        'reference'         => ($reference === '') ? null : $reference,
        'contact_id'        => isset($options['contact_id']) ? max(0, (int) $options['contact_id']) : 0,
        'template_id'       => isset($template['id']) ? max(0, (int) $template['id']) : 0,
        'template_name'     => $name,
        'template_language' => $language,
        'category'          => $category,
        'payload'           => $payload,
    );
}

/**
 * Puts one template message in the queue for the general job
 * (pg_whatsapp_queue_run()). This is what a request calls: it makes no call
 * to Meta. A marketing template, and any message that carries an offer
 * code, goes only to a number with SMS marketing consent and no refusal
 * (pg_sms_marketing_permission()), asked here and again when the message is
 * sent. Nothing is queued while WhatsApp is not set up, so nothing collects
 * on a site without it.
 *
 * Components that hold PG_WHATSAPP_OFFER_PLACEHOLDER get the template's
 * offer code here: the offer's own code, or a single-use key made for this
 * number (its id into key_code_id), so a retry sends the same code.
 *
 * db_q() rather than db(): a request that only wanted to send a message must
 * not end because the queue refused a row.
 *
 * @param string $to
 * @param array  $template pg_whatsapp_queue_row()
 * @param array  $options  pg_whatsapp_queue_row()
 * @return array ok, row (the queue's id), duplicate (a message with this
 *               reference was already queued; row is that one), error
 */
function pg_whatsapp_enqueue($to, $template, $options = array())
{
    $refused = array('ok' => false, 'row' => 0, 'duplicate' => false, 'error' => '');

    $row = pg_whatsapp_queue_row($to, $template, $options, time());

    if (isset($row['error'])) {
        $refused['error'] = $row['error'];
        return $refused;
    }

    $components = json_decode($row['payload'], true);
    $offered = pg_whatsapp_offer_needed($components);

    if (($row['category'] === 'MARKETING') || $offered) {
        $refusal = pg_sms_marketing_permission($row['recipient']);

        if ($refusal !== '') {
            $refused['error'] = $refusal;
            return $refused;
        }
    }

    if (function_exists('pg_demo') && pg_demo()) {
        $refused['error'] = lang('No WhatsApp message is sent from a demonstration site.');
        return $refused;
    }

    if (!pg_whatsapp_queue_ready()) {
        $refused['error'] = lang('The WhatsApp queue is not available yet; run the upgrade.');
        return $refused;
    }

    if (!pg_whatsapp_ready()) {
        $refused['error'] = lang('WhatsApp is not set up.');
        return $refused;
    }

    // An offer code is made only for a message not queued already: a second
    // enqueue of the same reference must not leave a key nobody gets.
    $row['key_code_id'] = 0;

    if ($offered) {
        if (($row['reference'] !== null) && ($existing = (int) db_value("SELECT id FROM whatsapp_outbox WHERE reference = '" . e($row['reference']) . "'"))) {
            return array('ok' => true, 'row' => $existing, 'duplicate' => true, 'error' => '');
        }

        $code = pg_whatsapp_offer_code_for_send(
            pg_whatsapp_offer_read($row['template_id'] ? db_value("SELECT offer FROM whatsapp_templates WHERE id = '" . (int) $row['template_id'] . "'") : ''),
            $row['template_name'], $row['recipient']);

        if (!$code['ok']) {
            $refused['error'] = $code['error'];
            return $refused;
        }

        $row['payload'] = json_encode(pg_whatsapp_offer_fill($components, $code['code']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row['key_code_id'] = $code['key_code_id'];
    }

    $columns = array('created_at', 'send_after', 'expires_at', 'status', 'kind', 'recipient', 'reference', 'contact_id',
        'template_id', 'template_name', 'template_language', 'category', 'payload', 'key_code_id');
    $values = array();

    foreach ($columns as $column) {
        $values[] = ($row[$column] === null) ? null : (string) $row[$column];
    }

    $result = db_q(
        "INSERT INTO whatsapp_outbox (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
        $values);

    if ($result !== false) {
        return array('ok' => true, 'row' => (int) mysqli_insert_id(db::$con), 'duplicate' => false, 'error' => '');
    }

    $errno = (int) mysqli_errno(db::$con);
    pg_whatsapp_offer_key_disable($row['key_code_id']);

    // ER_DUP_ENTRY on the reference: this message is already in the queue.
    if (($row['reference'] !== null) && ($errno === 1062)) {
        $existing = (int) db_value("SELECT id FROM whatsapp_outbox WHERE reference = '" . e($row['reference']) . "'");

        return array('ok' => true, 'row' => $existing, 'duplicate' => true, 'error' => '');
    }

    $refused['error'] = lang('The message could not be queued.');
    return $refused;
}

/**
 * The send a claimed row gets, or why it gets none; no database except the
 * marketing consent, which is asked again here because it may have been
 * withdrawn since the message was queued.
 *
 * @param array $row      kind, recipient, category, template_name,
 *                        template_language, payload
 * @param array $app      pg_whatsapp_app()
 * @param bool  $consent  whether a marketing template may go to this number
 * @return array|null null when the row should be sent; else a refusal in
 *                    pg_whatsapp_cloud_send_parse()'s shape
 */
function pg_whatsapp_queue_refusal($row, $app, $consent)
{
    $refuse = function ($error) {
        return array('ok' => false, 'id' => '', 'code' => '', 'error' => (string) $error, 'retry' => false, 'uncertain' => false);
    };

    if (!$app['enabled'] || !pg_whatsapp_app_complete($app)) {
        return $refuse(lang('WhatsApp is not set up.'));
    }

    if (($row['category'] === 'MARKETING') && !$consent) {
        return $refuse(lang('This number has no marketing consent for WhatsApp messages.'));
    }

    if (!is_array(json_decode((string) $row['payload'], true))) {
        return $refuse(lang('The template\'s variables could not be read.'));
    }

    return null;
}

/**
 * Whether a queued message is gated as marketing at the send (pure): it was
 * queued as one, or Meta has moved its template to marketing since.
 *
 * @param string $queued  the category the row was queued with
 * @param string $current the template's category now ('' when not found)
 * @return bool
 */
function pg_whatsapp_queue_marketing($queued, $current)
{
    return (strtoupper((string) $queued) === 'MARKETING') || (strtoupper((string) $current) === 'MARKETING');
}

/**
 * Whether a claimed row is gated as marketing at the send: its category as
 * queued, its template's category now, and a key made for it (a message
 * that carries an offer code).
 *
 * @param int    $id       whatsapp_outbox.id
 * @param string $category the row's category
 * @return bool
 */
function pg_whatsapp_queue_row_marketing($id, $category)
{
    $row = db_item(
        "SELECT whatsapp_outbox.key_code_id, whatsapp_templates.category
        FROM whatsapp_outbox
        LEFT JOIN whatsapp_templates ON whatsapp_templates.id = whatsapp_outbox.template_id
        WHERE whatsapp_outbox.id = '" . (int) $id . "'");

    if (!is_array($row)) {
        return pg_whatsapp_queue_marketing($category, '');
    }

    return pg_whatsapp_queue_marketing($category, (string) $row['category']) || ((int) $row['key_code_id'] > 0);
}

/**
 * Sends what is due in the queue. Called by job.php on every run. Bounded by
 * a row count and wall-clock time: Meta not answering costs its timeout per
 * message, and this must not hold the general job past its next tick.
 *
 * The SMS queue's rules (pg_sms_queue_outcome()): a message is sent again
 * only when nothing reached Meta or Meta's error code says a later try may
 * work; one whose outcome is unknown is never sent again by itself.
 *
 * @param int $limit
 * @param int $seconds
 * @return array sent, failed (sends that did not get through in this run,
 *               tried again later or not)
 */
function pg_whatsapp_queue_run($limit = 10, $seconds = 15)
{
    $result = array('sent' => 0, 'failed' => 0);

    if (!pg_whatsapp_queue_ready()) {
        return $result;
    }

    // An empty queue, the case of every site without WhatsApp, costs this
    // one read.
    if (!db_value("SELECT id FROM whatsapp_outbox LIMIT 1")) {
        return $result;
    }

    // A run that stopped while sending leaves its row in 'sending'. Meta may
    // have taken the message, so it is not queued again.
    db(
        "UPDATE whatsapp_outbox
        SET
            status = 'uncertain',
            claimed_at = 0,
            last_error = '" . e(pg_mail_clip(lang('The run that was sending this message stopped before Meta\'s answer was recorded, so it may have been sent.'), 500)) . "'
        WHERE
            (status = 'sending')
            AND (claimed_at < " . (time() - 900) . ")");

    $app = pg_whatsapp_app();
    $driver = false;

    $deadline = microtime(true) + $seconds;

    for ($looked = 0; ($looked < (int) $limit) && (microtime(true) < $deadline); $looked++) {

        $id = (int) db_value(
            "SELECT id
            FROM whatsapp_outbox
            WHERE
                (status = 'queued')
                AND (send_after <= UNIX_TIMESTAMP())
            ORDER BY send_after, id
            LIMIT 1");

        if (!$id) {
            break;
        }

        // Claimed before it is sent; the condition on status lets exactly one
        // of two overlapping runs change the row.
        db(
            "UPDATE whatsapp_outbox
            SET status = 'sending', claimed_at = UNIX_TIMESTAMP()
            WHERE
                (id = '" . $id . "')
                AND (status = 'queued')");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $row = db_item(
            "SELECT id, attempts, send_after, expires_at, kind, reference, recipient, category, template_name, template_language, payload
            FROM whatsapp_outbox
            WHERE id = '" . $id . "'");

        if (!is_array($row)) {
            continue;
        }

        $expired = ((int) $row['expires_at'] > 0) && ((int) $row['expires_at'] <= time());
        $send = null;

        if (!$expired) {
            $consent = !pg_whatsapp_queue_row_marketing($id, $row['category']) || (pg_sms_marketing_permission($row['recipient']) === '');
            $send = pg_whatsapp_queue_refusal($row, $app, $consent);

            if ($send === null) {
                if (!$driver) {
                    pg_whatsapp_driver();
                    $driver = true;
                }

                $payload = json_decode((string) $row['payload'], true);

                // An answer inside the customer's 24 hours is free text
                // (includes/fn/whatsapp_reply.php); everything else a template.
                $send = (($payload['type'] ?? '') === 'text')
                    ? pg_whatsapp_cloud_send_text($app, $row['recipient'], (string) ($payload['text'] ?? ''))
                    : pg_whatsapp_cloud_send($app, $row['recipient'], $row['template_name'], $row['template_language'], $payload);
            }
        }

        $outcome = pg_sms_queue_outcome($row, $send, time());

        db(
            "UPDATE whatsapp_outbox
            SET
                status = '" . e($outcome['status']) . "',
                attempts = '" . (int) $outcome['attempts'] . "',
                send_after = '" . (int) $outcome['send_after'] . "',
                sent_at = '" . (int) $outcome['sent_at'] . "',
                last_error = '" . e(pg_mail_clip($outcome['last_error'], 500)) . "',
                wamid = '" . e(pg_mail_clip($outcome['provider_id'], 128)) . "',
                error_code = '" . e(pg_mail_clip(($send === null) ? '' : (string) $send['code'], 16)) . "',
                claimed_at = 0
            WHERE id = '" . $id . "'");

        // A Workspace answer's message learns how it went.
        if (($row['kind'] === 'reply') && in_array($outcome['status'], array('sent', 'failed', 'uncertain'), true)) {
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_reply.php');
            pg_whatsapp_reply_outcome($id, (string) $row['reference'], $outcome['status'], (string) $outcome['last_error']);
        }

        if ($outcome['status'] === 'sent') {
            $result['sent']++;
            continue;
        }

        $result['failed']++;

        // A key whose message did not go reached nobody; one that may have
        // gone stays usable.
        if ($outcome['status'] === 'failed') {
            pg_whatsapp_offer_key_disable(db_value("SELECT key_code_id FROM whatsapp_outbox WHERE id = '" . $id . "'"));
        }

        if ($outcome['status'] === 'uncertain') {
            log_activity(lang(array(
                'string' => 'Meta may have taken a WhatsApp message to +{var:1}; it is not sent again by itself: {var:2}',
                'vars'   => array($row['recipient'], $outcome['last_error']),
            )), 'UNKNOWN');
        } elseif (($outcome['status'] === 'failed') && !$expired) {
            log_activity(lang(array(
                'string' => 'A WhatsApp message to +{var:1} was not sent: {var:2}',
                'vars'   => array($row['recipient'], $outcome['last_error']),
            )), 'UNKNOWN');
        }
    }

    // Kept as the SMS queue keeps its rows: sent ones a week, so the
    // webhook's delivery and read states still find them; the others a month.
    db("DELETE FROM whatsapp_outbox WHERE (status = 'sent') AND (sent_at < " . (time() - 604800) . ")");
    db("DELETE FROM whatsapp_outbox WHERE (status IN ('failed', 'uncertain')) AND (created_at < " . (time() - 2592000) . ")");

    return $result;
}


/* ---------------------------------------------------------------------------
   The account's templates (whatsapp_templates)
   --------------------------------------------------------------------------- */

// How often the general job reads the templates from Meta again.
define('PG_WHATSAPP_TEMPLATES_EVERY', 21600);

/**
 * Whether the 2026.11.0 upgrade has created whatsapp_templates.
 *
 * @return bool
 */
function pg_whatsapp_templates_ready()
{
    return pg_schema_has('whatsapp_templates');
}

/**
 * The connect_apps key the last refresh is noted under. A row of its own,
 * not the account's options: the account is written whole when its card is
 * saved, and a refresh running then would write the old account back.
 *
 * @return string
 */
function pg_whatsapp_templates_sync_key()
{
    return 'whatsapp_templates';
}

/**
 * When the templates were last read and what went wrong then.
 *
 * @return array checked (0 for never), ok, error
 */
function pg_whatsapp_templates_sync()
{
    $sync = array('checked' => 0, 'ok' => false, 'error' => '');

    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app(pg_whatsapp_templates_sync_key()) : null;
    $options = (is_array($app) && isset($app['options']) && is_array($app['options'])) ? $app['options'] : array();

    $sync['checked'] = isset($options['checked']) ? (int) $options['checked'] : 0;
    $sync['ok'] = !empty($options['ok']);
    $sync['error'] = (isset($options['error']) && is_scalar($options['error'])) ? (string) $options['error'] : '';

    return $sync;
}

/**
 * Notes a refresh.
 *
 * @param bool   $ok
 * @param string $error
 */
function pg_whatsapp_templates_sync_note($ok, $error)
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return;
    }

    $options = e(json_encode(array('checked' => time(), 'ok' => (bool) $ok, 'error' => pg_mail_clip((string) $error, 500))));

    db("INSERT INTO connect_apps
        SET
            provider = '" . e(pg_whatsapp_templates_sync_key()) . "',
            options = '" . $options . "',
            enabled = 1,
            updated_timestamp = UNIX_TIMESTAMP()
        ON DUPLICATE KEY UPDATE
            options = VALUES(options),
            updated_timestamp = VALUES(updated_timestamp)");
}

/**
 * Whether the general job should read the templates again; no database.
 *
 * @param int $checked the last refresh, 0 for never
 * @param int $now
 * @return bool
 */
function pg_whatsapp_templates_due($checked, $now)
{
    return ((int) $checked <= 0) || (((int) $now - (int) $checked) >= PG_WHATSAPP_TEMPLATES_EVERY);
}

/**
 * What a refresh writes; no database.
 *
 * A template Meta lists is matched by Meta's id, else by its name and
 * language (a draft sent for approval, or a row whose id was lost), and is
 * added when neither finds it. A stored template Meta no longer lists is
 * removed; a draft, which has no id of Meta's, never is.
 *
 * @param array $stored  rows: id, meta_id (null for a draft), name, language
 * @param array $fetched pg_whatsapp_cloud_templates_parse() templates
 * @return array insert (templates), update (row id => template), delete
 *               (row ids)
 */
function pg_whatsapp_templates_plan($stored, $fetched)
{
    $by_meta = array();
    $by_name = array();

    foreach ((array) $stored as $row) {
        if (($row['meta_id'] !== null) && ((string) $row['meta_id'] !== '')) {
            $by_meta[(string) $row['meta_id']] = (int) $row['id'];
        }

        $by_name[$row['name'] . '|' . $row['language']] = (int) $row['id'];
    }

    $plan = array('insert' => array(), 'update' => array(), 'delete' => array());
    $seen = array();

    foreach ((array) $fetched as $template) {
        $key = $template['name'] . '|' . $template['language'];

        if (isset($by_meta[$template['meta_id']])) {
            $id = $by_meta[$template['meta_id']];
        } elseif (isset($by_name[$key])) {
            $id = $by_name[$key];
        } else {
            $plan['insert'][] = $template;
            continue;
        }

        if (isset($plan['update'][$id])) {
            continue;
        }

        $plan['update'][$id] = $template;
        $seen[$id] = true;
    }

    foreach ((array) $stored as $row) {
        if (($row['meta_id'] !== null) && ((string) $row['meta_id'] !== '') && !isset($seen[(int) $row['id']])) {
            $plan['delete'][] = (int) $row['id'];
        }
    }

    return $plan;
}

/**
 * Reads the account's templates from Meta and keeps them. Nothing is removed
 * unless every page was read, so a list cut short never deletes templates.
 *
 * @return array ok, added, changed, removed, error
 */
function pg_whatsapp_templates_refresh()
{
    $result = array('ok' => false, 'added' => 0, 'changed' => 0, 'removed' => 0, 'error' => '');

    if (!pg_whatsapp_templates_ready()) {
        $result['error'] = lang('The WhatsApp templates are not available yet; run the upgrade.');
        return $result;
    }

    if (function_exists('pg_demo') && pg_demo()) {
        $result['error'] = lang('A demonstration site does not read WhatsApp templates.');
        return $result;
    }

    $app = pg_whatsapp_app();

    if (!$app['enabled'] || !pg_whatsapp_app_complete($app)) {
        $result['error'] = lang('WhatsApp is not set up.');
        return $result;
    }

    pg_whatsapp_driver();

    $read = pg_whatsapp_cloud_templates($app);

    if (!$read['ok']) {
        $result['error'] = $read['error'];
        pg_whatsapp_templates_sync_note(false, $read['error']);
        return $result;
    }

    $stored = (array) db_items("SELECT id, meta_id, name, language FROM whatsapp_templates");
    $plan = pg_whatsapp_templates_plan($stored, $read['templates']);

    // Values bound to placeholders: db_q() reads every ? of its SQL as one,
    // and a template's text ("Is your order ready?") holds them.
    $columns = "meta_id = ?, name = ?, language = ?, category = ?, status = ?, reason = ?, parameter_format = ?,
        components = ?, quality = ?, synced_timestamp = UNIX_TIMESTAMP()";
    $values = function ($template) {
        return array(
            $template['meta_id'], $template['name'], $template['language'], $template['category'], $template['status'],
            $template['reason'], $template['parameter_format'],
            json_encode($template['components'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $template['quality'],
        );
    };

    // Removed first, so an added template never collides with a name and
    // language a removed row still holds.
    if ($plan['delete']) {
        db_q("DELETE FROM whatsapp_templates WHERE id IN (" . implode(', ', array_map('intval', $plan['delete'])) . ")");
        $result['removed'] = count($plan['delete']);
    }

    foreach ($plan['update'] as $id => $template) {
        if (db_q("UPDATE whatsapp_templates SET " . $columns . " WHERE id = '" . (int) $id . "'", $values($template)) !== false) {
            $result['changed']++;
        }
    }

    foreach ($plan['insert'] as $template) {
        if (db_q("INSERT INTO whatsapp_templates SET " . $columns . ", samples = '', offer = '', created_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP()", $values($template)) !== false) {
            $result['added']++;
        }
    }

    $result['ok'] = true;
    pg_whatsapp_templates_sync_note(true, '');

    return $result;
}

/**
 * The general job's refresh: every six hours while WhatsApp is set up, and
 * at once after the account changed (its card saved since the last read).
 */
function pg_whatsapp_templates_job()
{
    if (!pg_whatsapp_templates_ready() || !pg_whatsapp_ready()) {
        return;
    }

    $sync = pg_whatsapp_templates_sync();
    $saved = (int) db_value("SELECT updated_timestamp FROM connect_apps WHERE provider = '" . e(pg_whatsapp_app_key()) . "'");

    if (!pg_whatsapp_templates_due($sync['checked'], time()) && ($saved <= $sync['checked'])) {
        return;
    }

    $result = pg_whatsapp_templates_refresh();

    if (!$result['ok'] && ($result['error'] !== '')) {
        log_activity(lang(array(
            'string' => 'The WhatsApp templates could not be read from Meta: {var:1}',
            'vars'   => array($result['error']),
        )), 'UNKNOWN');
    }
}

/**
 * One template by name and language, as a sender needs it.
 *
 * @param string $name
 * @param string $language
 * @return array|null id, meta_id, name, language, category, status,
 *                    parameter_format, components (decoded), offer (JSON)
 */
function pg_whatsapp_template_find($name, $language)
{
    if (!pg_whatsapp_templates_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT id, meta_id, name, language, category, status, parameter_format, components, offer
        FROM whatsapp_templates
        WHERE (name = '" . e((string) $name) . "') AND (language = '" . e((string) $language) . "')");

    if (!is_array($row)) {
        return null;
    }

    $components = json_decode((string) $row['components'], true);
    $row['components'] = is_array($components) ? $components : array();

    return $row;
}

/**
 * The account's templates for a list, by name and language.
 *
 * @return array rows: id, meta_id, name, language, category, status, reason,
 *               quality, synced_timestamp
 */
function pg_whatsapp_templates_list()
{
    if (!pg_whatsapp_templates_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT id, meta_id, name, language, category, status, reason, quality, synced_timestamp
        FROM whatsapp_templates
        ORDER BY name, language");
}

/**
 * A template's state as a list says it.
 *
 * @param string $status Meta's status, or DRAFT
 * @return string
 */
function pg_whatsapp_template_status_label($status)
{
    $labels = array(
        'DRAFT'            => lang('Draft'),
        'PENDING'          => lang('In review'),
        'APPROVED'         => lang('Approved'),
        'REJECTED'         => lang('Rejected'),
        'PAUSED'           => lang('Paused'),
        'DISABLED'         => lang('Disabled'),
        'FLAGGED'          => lang('Flagged'),
        'IN_APPEAL'        => lang('In appeal'),
        'PENDING_DELETION' => lang('Being deleted'),
        'ARCHIVED'         => lang('Archived'),
        'LIMIT_EXCEEDED'   => lang('Over the limit'),
    );

    return isset($labels[$status]) ? $labels[$status] : (string) $status;
}

/**
 * A template's category as a list says it.
 *
 * @param string $category MARKETING, UTILITY or AUTHENTICATION
 * @return string
 */
function pg_whatsapp_template_category_label($category)
{
    $labels = array(
        'MARKETING'      => lang('Marketing'),
        'UTILITY'        => lang('Utility'),
        'AUTHENTICATION' => lang('Authentication'),
    );

    return isset($labels[$category]) ? $labels[$category] : (string) $category;
}
