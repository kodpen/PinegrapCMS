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
 * body's HMAC with the app secret.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

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

    return pg_whatsapp_settings_html(pg_whatsapp_app(), array(
        'refusal'     => pg_whatsapp_settings_refusal(),
        'webhook_url' => pg_whatsapp_webhook_url(),
        'test_to'     => $number,
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
 *                       none)
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
 * signed event posts. A signed post is answered 200, so Meta does not send it
 * again; its events are not read yet.
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

    pg_whatsapp_webhook_answer(200, json_encode(array('ok' => true)));
}
