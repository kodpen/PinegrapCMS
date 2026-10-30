<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * CAPTCHA providers: Cloudflare Turnstile and hCaptcha beside the built-in
 * maths question.
 *
 * The built-in question (get_captcha_fields() in includes/fn/auth.php) is
 * what a form gets when no provider is switched on; it needs no account and
 * no outside service. A provider is chosen on the Security settings' Spam
 * Protection card; its keys are kept in connect_apps under 'captcha_<key>',
 * like the SMS accounts: the site key as client_id, the secret key sealed
 * with the site's key and never drawn back into a form, and the chosen
 * provider as enabled. Keys that are incomplete, or that this site cannot
 * read, fall back to the built-in question, never to no CAPTCHA at all.
 *
 * A provider's answer is checked on the server (siteverify) when the form is
 * sent. The check fails closed: a provider that cannot be reached, or that
 * refuses the site's keys, refuses the submission with a "try again" message
 * and leaves one line in the activity log per ten minutes. Staff
 * (pg_user_has_panel_access()) never see a CAPTCHA; signed-in members do.
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
 * The CAPTCHA providers, in the order the settings offer them.
 *
 * @return array key => label, portal, script (the widget's script),
 *               class (the element the script draws the widget in),
 *               field (the posted token), verify (the siteverify address),
 *               csp (directive => sources the widget needs)
 */
function pg_captcha_providers()
{
    return array(
        'turnstile' => array(
            'label'  => 'Cloudflare Turnstile',
            'portal' => 'https://dash.cloudflare.com/?to=/:account/turnstile',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'class'  => 'cf-turnstile',
            'field'  => 'cf-turnstile-response',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'csp'    => array(
                'script-src' => array('https://challenges.cloudflare.com'),
                'frame-src'  => array('https://challenges.cloudflare.com'),
            ),
        ),
        'hcaptcha' => array(
            'label'  => 'hCaptcha',
            'portal' => 'https://dashboard.hcaptcha.com/',
            'script' => 'https://js.hcaptcha.com/1/api.js',
            'class'  => 'h-captcha',
            'field'  => 'h-captcha-response',
            'verify' => 'https://api.hcaptcha.com/siteverify',
            'csp'    => array(
                'script-src'  => array('https://hcaptcha.com', 'https://*.hcaptcha.com'),
                'frame-src'   => array('https://hcaptcha.com', 'https://*.hcaptcha.com'),
                'style-src'   => array('https://hcaptcha.com', 'https://*.hcaptcha.com'),
                'connect-src' => array('https://hcaptcha.com', 'https://*.hcaptcha.com'),
            ),
        ),
    );
}

/**
 * @param string $provider
 * @return array|null
 */
function pg_captcha_provider_definition($provider)
{
    $providers = pg_captcha_providers();

    return (is_string($provider) && isset($providers[$provider])) ? $providers[$provider] : null;
}

/**
 * The connect_apps key a provider's keys are kept under.
 *
 * @param string $provider
 * @return string
 */
function pg_captcha_app_key($provider)
{
    return 'captcha_' . $provider;
}

/**
 * A provider's keys on this site.
 *
 * @param string $provider
 * @return array site_key, secret ('' when none or when the site's key cannot
 *               read it), secret_stored, enabled
 */
function pg_captcha_app($provider)
{
    $app = function_exists('pg_connect_app') ? pg_connect_app(pg_captcha_app_key($provider)) : null;

    if (!is_array($app)) {
        return array(
            'site_key'      => '',
            'secret'        => '',
            'secret_stored' => false,
            'enabled'       => false,
        );
    }

    return array(
        'site_key'      => (string) $app['client_id'],
        'secret'        => (string) $app['client_secret'],
        'secret_stored' => (bool) $app['secret_stored'],
        'enabled'       => (bool) $app['enabled'],
    );
}

/**
 * The provider forms use, with its keys: the first one switched on whose
 * site key and secret are both readable. Read once per request with one
 * query, so the security policy header and the forms share it.
 *
 * @return array|null provider, site_key, secret; null for the built-in
 *                    question
 */
function pg_captcha_active()
{
    static $active = false;

    if ($active !== false) {
        return $active;
    }

    $active = null;

    // Without cURL no provider can be asked, so forms keep the built-in
    // question instead of refusing every submission.
    if (!function_exists('curl_init') || !function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready() || !pg_connect_can_seal()) {
        return $active;
    }

    $keys = array();

    foreach (array_keys(pg_captcha_providers()) as $provider) {
        $keys[] = "'" . e(pg_captcha_app_key($provider)) . "'";
    }

    $rows = db_items(
        "SELECT provider, client_id, secret
        FROM connect_apps
        WHERE provider IN (" . implode(', ', $keys) . ") AND enabled = 1");

    $found = array();

    foreach ($rows as $row) {
        $found[(string) $row['provider']] = $row;
    }

    foreach (array_keys(pg_captcha_providers()) as $provider) {
        $key = pg_captcha_app_key($provider);

        if (!isset($found[$key])) {
            continue;
        }

        $secret = pg_connect_unseal($found[$key]['secret']);
        $secret = isset($secret['client_secret']) ? (string) $secret['client_secret'] : '';
        $site_key = (string) $found[$key]['client_id'];

        if (($site_key !== '') && ($secret !== '')) {
            $active = array('provider' => $provider, 'site_key' => $site_key, 'secret' => $secret);
            break;
        }
    }

    return $active;
}

/**
 * @return string provider key, '' for the built-in question
 */
function pg_captcha_provider()
{
    $active = pg_captcha_active();

    return is_array($active) ? $active['provider'] : '';
}

/**
 * The element a provider's script draws its widget in, and the script itself
 * when $with_script.
 *
 * @param string $provider
 * @param string $site_key
 * @param bool   $with_script
 * @return string '' for an unknown provider
 */
function pg_captcha_widget_html($provider, $site_key, $with_script)
{
    $definition = pg_captcha_provider_definition($provider);

    if ($definition === null) {
        return '';
    }

    $html = '<div class="' . h($definition['class']) . ' software_captcha_widget" data-sitekey="' . h($site_key) . '"></div>';

    if ($with_script) {
        $html .= '<script src="' . h($definition['script']) . '" async defer></script>';
    }

    return $html;
}

/**
 * The active provider's widget. The script is printed with the first widget
 * of the request only: it draws every widget on the page, and loading it a
 * second time is refused by both providers.
 *
 * @param array $active pg_captcha_active()
 * @return string
 */
function pg_captcha_widget($active)
{
    static $script_printed = false;

    $with_script = !$script_printed;
    $script_printed = true;

    return pg_captcha_widget_html($active['provider'], $active['site_key'], $with_script);
}

/**
 * The token a provider's widget posted with the form.
 *
 * @param string $provider
 * @param array  $post
 * @return string '' when missing, not a string or implausibly long
 */
function pg_captcha_token($provider, $post)
{
    $definition = pg_captcha_provider_definition($provider);

    if (($definition === null) || !isset($post[$definition['field']]) || !is_string($post[$definition['field']])) {
        return '';
    }

    $token = trim($post[$definition['field']]);

    return (strlen($token) <= 4096) ? $token : '';
}

/**
 * The error codes of a siteverify answer.
 *
 * @param mixed $body the answer's body
 * @return array strings
 */
function pg_captcha_error_codes($body)
{
    $answer = is_string($body) ? json_decode($body, true) : null;

    if (!is_array($answer) || !isset($answer['error-codes']) || !is_array($answer['error-codes'])) {
        return array();
    }

    $codes = array();

    foreach ($answer['error-codes'] as $code) {
        if (is_string($code) && preg_match('/^[a-z0-9\-_]{1,64}$/i', $code)) {
            $codes[] = strtolower($code);
        }
    }

    return $codes;
}

/**
 * What a siteverify answer means for the submission.
 *
 * @param int   $status the HTTP status, 0 when no answer came
 * @param mixed $body   the answer's body (false when no answer came)
 * @return string 'pass'; 'retry' (the visitor's token was refused: solve the
 *                check again); 'misconfigured' (the site's keys were
 *                refused); 'unavailable' (no readable answer, an outage)
 */
function pg_captcha_verify_outcome($status, $body)
{
    $answer = is_string($body) ? json_decode($body, true) : null;

    if (!is_array($answer) || !isset($answer['success'])) {
        return 'unavailable';
    }

    if (((int) $status === 200) && ($answer['success'] === true)) {
        return 'pass';
    }

    $codes = pg_captcha_error_codes($body);

    $misconfigured = array('missing-input-secret', 'invalid-input-secret', 'sitekey-secret-mismatch', 'invalid-sitekey');
    // not-using-dummy-passcode: hCaptcha's test keys were sent a real token.
    $retry = array('missing-input-response', 'invalid-input-response', 'timeout-or-duplicate', 'expired-input-response', 'already-seen-response', 'not-using-dummy-passcode');

    if (array_intersect($codes, $misconfigured)) {
        return 'misconfigured';
    }

    if (array_intersect($codes, $retry)) {
        return 'retry';
    }

    // An error the provider names but this list does not (internal-error,
    // bad-request), or a failed answer: the provider could not judge.
    if ($codes || ((int) $status !== 200) || ($answer['success'] !== false)) {
        return 'unavailable';
    }

    return 'retry';
}

/**
 * Asks a provider whether a token is good.
 *
 * @param string $provider
 * @param string $site_key
 * @param string $secret
 * @param string $token
 * @param string $ip the visitor's address, '' when not to be sent
 * @return array outcome (pg_captcha_verify_outcome()), detail (for the log)
 */
function pg_captcha_verify_request($provider, $site_key, $secret, $token, $ip)
{
    $definition = pg_captcha_provider_definition($provider);

    if (($definition === null) || !function_exists('curl_init')) {
        return array('outcome' => 'unavailable', 'detail' => 'cURL');
    }

    $fields = array('secret' => $secret, 'response' => $token);

    if ($ip !== '') {
        $fields['remoteip'] = $ip;
    }

    if ($provider === 'hcaptcha') {
        $fields['sitekey'] = $site_key;
    }

    $answer = pg_curl_run('POST', $definition['verify'], array(
        'body'            => http_build_query($fields, '', '&'),
        'connect_timeout' => 5,
        'timeout'         => 10,
    ));
    $body = $answer['body'];
    $status = $answer['status'];
    $error = $answer['error'];

    $outcome = pg_captcha_verify_outcome($status, $body);

    if ($error !== '') {
        $detail = $error;
    } elseif ($codes = pg_captcha_error_codes($body)) {
        $detail = implode(', ', $codes);
    } else {
        $detail = 'HTTP ' . $status;
    }

    return array('outcome' => $outcome, 'detail' => $detail);
}

/**
 * Checks a submission against the active provider, marking the error the
 * visitor sees. Fails closed: when the provider cannot judge, the submission
 * is refused and the visitor asked to try again.
 *
 * A form that already has an error is sent back anyway and draws a fresh
 * widget, so the provider is not asked: a script posting made-up tokens with
 * a broken form cannot make this server call out on every post.
 *
 * @param object $liveform
 * @param array  $active pg_captcha_active()
 * @return string pg_captcha_verify_outcome(), or 'skipped' when the form
 *                already had an error
 */
function pg_captcha_check($liveform, $active)
{
    if ($liveform->check_form_errors()) {
        return 'skipped';
    }

    $token = pg_captcha_token($active['provider'], $_POST);

    if ($token === '') {
        $liveform->mark_error('captcha_submitted_answer', lang('Please complete the spam check again.'));
        return 'retry';
    }

    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : '';

    $result = pg_captcha_verify_request($active['provider'], $active['site_key'], $active['secret'], $token, $ip);

    if ($result['outcome'] === 'retry') {
        $liveform->mark_error('captcha_submitted_answer', lang('Please complete the spam check again.'));
    } elseif ($result['outcome'] !== 'pass') {
        $liveform->mark_error('captcha_submitted_answer', lang('The spam check could not be completed. Please try again in a moment.'));
        pg_captcha_log_failure($active['provider'], $result['outcome'], $result['detail']);
    }

    return $result['outcome'];
}

/**
 * One activity log line for a provider that could not judge, at most one per
 * provider and outcome every ten minutes, so an outage does not fill the log.
 *
 * @param string $provider
 * @param string $outcome 'misconfigured' or 'unavailable'
 * @param string $detail
 */
function pg_captcha_log_failure($provider, $outcome, $detail)
{
    // waf_rate_record() answers 0 when it cannot count; that is logged too.
    if (function_exists('waf_rate_record') && (waf_rate_record('captcha_' . $provider, 'cap_' . $outcome, 600) > 1)) {
        return;
    }

    $definition = pg_captcha_provider_definition($provider);
    $label = ($definition !== null) ? $definition['label'] : $provider;

    if ($outcome === 'misconfigured') {
        $message = lang(array(
            'string' => '{var:1} refused this site\'s CAPTCHA keys ({var:2}), so form submissions are refused until the keys are corrected in the Security settings',
            'vars'   => array($label, $detail)));
    } else {
        $message = lang(array(
            'string' => '{var:1} could not be reached to check a CAPTCHA ({var:2}), so the form submission was refused',
            'vars'   => array($label, $detail)));
    }

    log_activity($message, 'UNKNOWN');
}

/**
 * The sources a provider's widget needs in the security policy.
 *
 * @param string $provider '' for the built-in question
 * @return array directive => list of sources
 */
function pg_captcha_csp_sources($provider)
{
    $definition = pg_captcha_provider_definition($provider);

    return ($definition !== null) ? $definition['csp'] : array();
}

/**
 * The sources as one line an operator can copy into their own policy.
 *
 * @param string $provider
 * @return string
 */
function pg_captcha_csp_text($provider)
{
    $parts = array();

    foreach (pg_captcha_csp_sources($provider) as $directive => $sources) {
        $parts[] = $directive . ' ' . implode(' ', $sources);
    }

    return implode('; ', $parts);
}


/* ---------------------------------------------------------------------------
   Settings (Security, Spam Protection card)
   --------------------------------------------------------------------------- */

/**
 * Why no provider keys can be kept on this site now, '' when they can.
 *
 * @return string
 */
function pg_captcha_settings_refusal()
{
    if (function_exists('pg_demo') && pg_demo()) {
        return lang('This is a demonstration site: no CAPTCHA provider can be set up here.');
    }

    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return lang('The database has not been upgraded yet. Run the software update first.');
    }

    if (!pg_connect_can_seal()) {
        return lang('This site has no encryption key, so it cannot keep the secret key of a CAPTCHA provider.');
    }

    if (!function_exists('curl_init')) {
        return lang('This server has no cURL extension, so it cannot check a CAPTCHA with the provider and forms show the built-in question.');
    }

    return '';
}

/**
 * The provider the card shows as chosen: the one switched on, even with
 * incomplete keys, so the card can say what is missing.
 *
 * @param array $apps provider => pg_captcha_app()
 * @return string
 */
function pg_captcha_settings_selected($apps)
{
    foreach ($apps as $provider => $app) {
        if ($app['enabled']) {
            return $provider;
        }
    }

    return '';
}

/**
 * The Spam Protection card's provider fields: the choice, each provider's
 * keys (the secret never drawn back) and what the card has to warn about.
 *
 * @return string
 */
function pg_captcha_settings_fields()
{
    $apps = array();

    foreach (array_keys(pg_captcha_providers()) as $provider) {
        $apps[$provider] = pg_captcha_app($provider);
    }

    $selected = pg_captcha_settings_selected($apps);

    $options = '<option value="">' . h(lang('Built-in question')) . '</option>';

    foreach (pg_captcha_providers() as $key => $definition) {
        $options .= '<option value="' . h($key) . '"' . (($key === $selected) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
    }

    $html = '
                                        <div class="col-12">
                                            <label for="captcha_provider" class="form-label">' . lang('CAPTCHA Type') . '</label>
                                            <select name="captcha_provider" id="captcha_provider" class="form-control pg-f-md">' . $options . '</select>
                                            <div class="form-text">' . lang('The built-in question needs no account. Turnstile and hCaptcha draw their own check and are verified with the provider when the form is sent; when the provider cannot be reached or refuses the keys, the form is not accepted and the visitor is asked to try again. Staff never see a CAPTCHA.') . '</div>
                                        </div>';

    $warning = '';

    if ($selected !== '') {
        $label = pg_captcha_providers()[$selected]['label'];

        if (pg_captcha_provider() !== $selected) {
            $warning = !function_exists('curl_init')
                ? lang('This server has no cURL extension, so it cannot check a CAPTCHA with the provider and forms show the built-in question.')
                : lang(array(
                'string' => 'The {var:1} keys are incomplete or cannot be read with this site\'s encryption key, so forms show the built-in question. Enter them again.',
                    'vars'   => array(h($label))));
        } elseif (function_exists('waf_setting') && (trim((string) waf_setting('security_csp_policy', '')) !== '')) {
            $warning = lang(array(
                'string' => 'This site sends its own Content-Security-Policy (Firewall settings). Add these sources to it, or {var:1} cannot load: {var:2}',
                'vars'   => array(h($label), h(pg_captcha_csp_text($selected)))));
        }
    }

    if ($warning !== '') {
        $html .= '
                                        <div class="col-12"><div class="alert theme-warning mb-0">' . $warning . '</div></div>';
    }

    foreach (pg_captcha_providers() as $key => $definition) {
        $html .= pg_captcha_settings_provider_fields($key, $definition, $apps[$key]);
    }

    return $html;
}

/**
 * One provider's key fields.
 *
 * @param string $provider
 * @param array  $definition
 * @param array  $app pg_captcha_app()
 * @return string
 */
function pg_captcha_settings_provider_fields($provider, $definition, $app)
{
    $name = 'captcha_' . $provider . '_';

    // "Stored" only for a secret this site can still read.
    $placeholder = ($app['secret_stored'] && ($app['secret'] !== ''))
        ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"'
        : '';

    return '
                                        <div class="col-12">
                                            <div class="form-label fw-bold mb-1">' . h($definition['label']) . '</div>
                                            <div class="form-text mt-0">' . lang(array(
                                                'string' => 'Enter the site key and secret key of a {var:1} widget made for this site\'s domain. Saving checks them with the provider. To remove the keys from this site, choose another type and empty the site key.',
                                                'vars'   => array('<a href="' . h($definition['portal']) . '" target="_blank" rel="noopener">' . h($definition['label']) . '</a>'))) . '</div>
                                            <div class="row gy-5 mt-0">
                                                <div class="pg-f-md">
                                                    <label for="' . h($name) . 'site_key" class="form-label">' . lang('Site Key') . '</label>
                                                    <input type="text" name="' . h($name) . 'site_key" id="' . h($name) . 'site_key" class="form-control" value="' . h($app['site_key']) . '" maxlength="255" autocomplete="off"/>
                                                </div>
                                                <div class="pg-f-md">
                                                    <label for="' . h($name) . 'secret" class="form-label">' . lang('Secret Key') . '</label>
                                                    <input type="password" name="' . h($name) . 'secret" id="' . h($name) . 'secret" class="form-control" value="" maxlength="255" autocomplete="new-password"' . $placeholder . '/>
                                                </div>
                                            </div>
                                        </div>';
}

/**
 * A trimmed string from a post.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_captcha_post($post, $name)
{
    return (isset($post[$name]) && is_string($post[$name])) ? trim($post[$name]) : '';
}

/**
 * What a post of the Spam Protection card asks for, decided from the post and
 * the stored keys alone: no database, no provider.
 *
 * Only a change is written, so keys this site can no longer read do not stop
 * the rest of the Security settings from being saved.
 *
 * @param array  $post
 * @param array  $before  provider => pg_captcha_app()
 * @param string $refusal pg_captcha_settings_refusal()
 * @return array|null provider (chosen, '' for the built-in question), apps
 *                    (provider => action 'none', 'save', 'disable' or
 *                    'forget', site_key, secret as typed, enabled), ask (the
 *                    provider whose keys are to be checked, ''), secret
 *                    (the typed or stored secret of that provider), marks
 *                    (field => sentence), notices; null when the post has no
 *                    such card
 */
function pg_captcha_settings_decide($post, $before, $refusal)
{
    if (!isset($post['captcha_provider'])) {
        return null;
    }

    $chosen = pg_captcha_post($post, 'captcha_provider');

    if (pg_captcha_provider_definition($chosen) === null) {
        $chosen = '';
    }

    $decision = array(
        'provider' => $chosen,
        'apps'     => array(),
        'ask'      => '',
        'secret'   => '',
        'marks'    => array(),
        'notices'  => array(),
    );

    foreach (pg_captcha_providers() as $provider => $definition) {
        $name = 'captcha_' . $provider . '_';
        $was = isset($before[$provider]) ? $before[$provider] : array('site_key' => '', 'secret' => '', 'secret_stored' => false, 'enabled' => false);
        $enabled = ($provider === $chosen);

        $app = array(
            'action'   => 'none',
            'site_key' => pg_captcha_post($post, $name . 'site_key'),
            'secret'   => pg_captcha_post($post, $name . 'secret'),
            'enabled'  => $enabled,
        );

        // A pane that does not draw this provider's fields says nothing
        // about its keys.
        if (!isset($post[$name . 'site_key'])) {
            $decision['apps'][$provider] = $app;
            continue;
        }

        $stored = ($was['site_key'] !== '') || $was['secret_stored'];
        $unchanged = ($app['site_key'] === $was['site_key']) && ($app['secret'] === '');

        if (!$enabled && ($app['site_key'] === '') && ($app['secret'] === '')) {
            $app['action'] = $stored ? 'forget' : 'none';
        } elseif ($unchanged && ($enabled === $was['enabled'])) {
            $app['action'] = 'none';
        } elseif ($unchanged && !$enabled) {
            // Switching off writes the switch alone, which needs no sealing.
            $app['action'] = 'disable';
        } elseif ($refusal !== '') {
            if ($enabled) {
                $decision['marks']['captcha_provider'] = $refusal;
            } else {
                $app['action'] = $was['enabled'] ? 'disable' : 'none';
                $decision['notices'][] = lang(array('string' => 'The {var:1} keys were not saved: {var:2}', 'vars' => array(h($definition['label']), h($refusal))));
            }
        } else {
            $app['action'] = 'save';
            $marked = false;

            if (($app['site_key'] !== '') && !preg_match('/^[A-Za-z0-9_.\-]{1,255}$/', $app['site_key'])) {
                $decision['marks'][$name . 'site_key'] = lang('A site key has only letters, digits, dots, hyphens and underscores.');
                $marked = true;
            }

            if (($app['secret'] !== '') && !preg_match('/^[\x21-\x7E]{1,255}$/', $app['secret'])) {
                $decision['marks'][$name . 'secret'] = lang('A secret key has no spaces or special characters.');
                $marked = true;
            }

            if ($enabled) {
                $secret = ($app['secret'] !== '') ? $app['secret'] : $was['secret'];

                if ($app['site_key'] === '') {
                    $decision['marks'][$name . 'site_key'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Site Key'))));
                    $marked = true;
                }

                if ($secret === '') {
                    $decision['marks'][$name . 'secret'] = $was['secret_stored']
                        ? lang('The stored secret key cannot be read with this site\'s encryption key. Enter it again.')
                        : lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Secret Key'))));
                    $marked = true;
                }

                if (!$marked) {
                    $decision['ask'] = $provider;
                    $decision['secret'] = $secret;
                }
            }
        }

        $decision['apps'][$provider] = $app;
    }

    return $decision;
}

/**
 * Checks the Spam Protection card before the Security settings are written,
 * and asks the provider about changed keys. A refusal is marked on its field,
 * so nothing of the category is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array|null what pg_captcha_settings_save() writes
 */
function pg_captcha_settings_check($post, $liveform)
{
    if (!isset($post['captcha_provider'])) {
        return null;
    }

    $before = array();

    foreach (array_keys(pg_captcha_providers()) as $provider) {
        $before[$provider] = pg_captcha_app($provider);
    }

    $decision = pg_captcha_settings_decide($post, $before, pg_captcha_settings_refusal());

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    if (($decision['ask'] === '') || $liveform->check_form_errors()) {
        return $decision;
    }

    $provider = $decision['ask'];

    // A made-up token: a provider that accepts the keys answers that the
    // token is not valid, one that does not names the secret or site key.
    $result = pg_captcha_verify_request($provider, $decision['apps'][$provider]['site_key'], $decision['secret'], 'pinegrap-settings-check', '');

    $decision = pg_captcha_settings_answer($decision, $result);

    foreach ($decision['marks'] as $field => $sentence) {
        if (!$liveform->check_field_error($field)) {
            $liveform->mark_error($field, $sentence);
        }
    }

    return $decision;
}

/**
 * What the provider's answer to the settings check changes in a decision.
 * Keys the provider refuses are marked on the secret field, so nothing is
 * written. A provider this server cannot reach is not switched on: its keys
 * are kept and forms stay on the built-in question, because a switched-on
 * provider that cannot be asked refuses every submission.
 *
 * @param array $decision pg_captcha_settings_decide() with 'ask' set
 * @param array $result   pg_captcha_verify_request()
 * @return array the decision
 */
function pg_captcha_settings_answer($decision, $result)
{
    $provider = $decision['ask'];
    $definition = pg_captcha_provider_definition($provider);

    if (($definition === null) || !isset($decision['apps'][$provider])) {
        return $decision;
    }

    if ($result['outcome'] === 'misconfigured') {
        $decision['marks']['captcha_' . $provider . '_secret'] = lang(array(
            'string' => '{var:1} did not accept these keys ({var:2}).',
            'vars'   => array(h($definition['label']), h($result['detail']))));
    } elseif ($result['outcome'] === 'unavailable') {
        $decision['apps'][$provider]['enabled'] = false;
        $decision['provider'] = '';
        $decision['notices'][] = lang(array(
            'string' => '{var:1} could not be reached from this server ({var:2}), so it was not switched on and forms show the built-in question. Its keys were saved; choose it again to retry.',
            'vars'   => array(h($definition['label']), h($result['detail']))));
    }

    return $decision;
}

/**
 * Writes what pg_captcha_settings_check() decided. Runs after the Security
 * settings were written, and only when nothing was refused.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_captcha_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision)) {
        return;
    }

    foreach ($decision['apps'] as $provider => $app) {
        $key = pg_captcha_app_key($provider);

        if ($app['action'] === 'forget') {
            db("DELETE FROM connect_apps WHERE provider = '" . e($key) . "'");
        } elseif ($app['action'] === 'disable') {
            db("UPDATE connect_apps
                SET
                    enabled = 0,
                    updated_user_id = '" . (int) $user_id . "',
                    updated_timestamp = UNIX_TIMESTAMP()
                WHERE provider = '" . e($key) . "'");
        } elseif ($app['action'] === 'save') {
            pg_connect_app_save(
                $key,
                $app['site_key'],
                ($app['secret'] !== '') ? $app['secret'] : null,
                array(),
                $app['enabled'],
                $user_id);
        }
    }

    // One provider at a time: any other one switched on is switched off.
    if ($decision['provider'] !== '') {
        foreach (array_keys(pg_captcha_providers()) as $provider) {
            if ($provider !== $decision['provider']) {
                db("UPDATE connect_apps SET enabled = 0 WHERE provider = '" . e(pg_captcha_app_key($provider)) . "' AND enabled = 1");
            }
        }
    }

    foreach ($decision['notices'] as $notice) {
        $liveform->add_notice($notice);
    }
}
