<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shipping carriers' REST APIs, signed in with OAuth 2.0.
 *
 * UPS retired its XML access key, FedEx its SOAP web services and USPS its
 * Web Tools user ID; all three now take an access token fetched with a
 * client_credentials grant from an application the site owner creates in the
 * carrier's developer portal.
 *
 * The application's client ID and secret are kept in connect_apps under
 * 'shipping_<carrier>' (the secret sealed with the site's key and never
 * drawn back into a form), with the environment - production or the
 * carrier's test system - among its options. No provider file exists for
 * these keys, so the Connected accounts screen does not list them: they are
 * edited on the Shipping card of the e-commerce settings. The access token
 * is kept among the service tokens (pg_connect_service_get()) until shortly
 * before it expires, so a checkout does not fetch one per rate request.
 *
 * Request builders and response readers are plain functions of their
 * arguments, so the tests check them without a carrier account; shipping.php
 * gathers the site's data and calls them.
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
 * What differs between the carriers: where the token comes from, how the
 * client credentials are sent for it, and where the operator registers the
 * application.
 *
 * @return array carrier => label, hosts (production, test), token_path,
 *               token_auth ('basic' or 'form'), portal, products
 */
function pg_carrier_api_definitions()
{
    return array(
        'ups' => array(
            'label'      => 'UPS',
            'hosts'      => array(
                'production' => 'https://onlinetools.ups.com',
                // Customer Integration Environment.
                'test'       => 'https://wwwcie.ups.com',
            ),
            'token_path' => '/security/v1/oauth/token',
            'token_auth' => 'basic',
            'portal'     => 'https://developer.ups.com/',
            'products'   => 'Rating',
        ),
        'fedex' => array(
            'label'      => 'FedEx',
            'hosts'      => array(
                'production' => 'https://apis.fedex.com',
                'test'       => 'https://apis-sandbox.fedex.com',
            ),
            'token_path' => '/oauth/token',
            'token_auth' => 'form',
            'portal'     => 'https://developer.fedex.com/',
            'products'   => 'Rates and Transit Times',
        ),
        'usps' => array(
            'label'      => 'USPS',
            'hosts'      => array(
                'production' => 'https://apis.usps.com',
                // Testing Environment for Mailers.
                'test'       => 'https://apis-tem.usps.com',
            ),
            'token_path' => '/oauth2/v3/token',
            'token_auth' => 'form',
            'portal'     => 'https://developers.usps.com/',
            'products'   => 'Domestic Prices, Service Standards, Addresses',
        ),
    );
}

/**
 * What the carrier's portal calls the two halves of the credentials, so the
 * operator finds them under the same names.
 *
 * @param string $carrier
 * @return array id, secret
 */
function pg_carrier_api_labels($carrier)
{
    if ($carrier === 'fedex') {
        return array('id' => lang('API Key'), 'secret' => lang('Secret Key'));
    }

    if ($carrier === 'usps') {
        return array('id' => lang('Consumer Key'), 'secret' => lang('Consumer Secret'));
    }

    return array('id' => lang('Client ID'), 'secret' => lang('Client Secret'));
}

/**
 * @param string $carrier
 * @return array|null
 */
function pg_carrier_api_definition($carrier)
{
    $definitions = pg_carrier_api_definitions();

    return isset($definitions[$carrier]) ? $definitions[$carrier] : null;
}

/**
 * The connect_apps key a carrier's application is kept under. Prefixed, so a
 * provider file added later for a carrier's own sign-in does not share it.
 *
 * @param string $carrier
 * @return string
 */
function pg_carrier_api_key($carrier)
{
    return 'shipping_' . $carrier;
}

/**
 * The carrier's application on this site.
 *
 * @param string $carrier
 * @return array client_id, client_secret, secret_stored, environment
 */
function pg_carrier_api_app($carrier)
{
    $app = pg_connect_app(pg_carrier_api_key($carrier));

    if (!is_array($app)) {
        return array(
            'client_id'     => '',
            'client_secret' => '',
            'secret_stored' => false,
            'environment'   => 'production',
        );
    }

    return array(
        'client_id'     => $app['client_id'],
        'client_secret' => $app['client_secret'],
        'secret_stored' => $app['secret_stored'],
        'environment'   => (isset($app['options']['environment']) && ($app['options']['environment'] === 'test')) ? 'test' : 'production',
    );
}

/**
 * Whether a token can be asked for: both halves of the credentials are there
 * and the secret could be read with this site's key.
 *
 * @param string $carrier
 * @return bool
 */
function pg_carrier_api_ready($carrier)
{
    $app = pg_carrier_api_app($carrier);

    return ($app['client_id'] !== '') && ($app['client_secret'] !== '');
}

/**
 * Saves the carrier's application. An empty secret keeps the stored one, so a
 * save of the settings screen, which never draws the secret back, does not
 * clear it. The cached token goes with any change: it was issued to the
 * credentials of before.
 *
 * @param string $carrier
 * @param string $client_id
 * @param string $client_secret
 * @param string $environment 'production' or 'test'
 * @param int    $user_id
 * @return bool false when the credentials cannot be kept here (no encryption
 *              key, or the database upgrade has not run)
 */
function pg_carrier_api_save($carrier, $client_id, $client_secret, $environment, $user_id)
{
    if (!pg_connect_schema_ready() || (pg_carrier_api_definition($carrier) === null)) {
        return false;
    }

    $before = pg_carrier_api_app($carrier);

    $saved = pg_connect_app_save(
        pg_carrier_api_key($carrier),
        $client_id,
        $client_secret,
        array('environment' => ($environment === 'test') ? 'test' : 'production'),
        true,
        $user_id);

    if ($saved && (pg_carrier_api_app($carrier) !== $before)) {
        pg_connect_service_delete(pg_carrier_api_key($carrier));
    }

    return $saved;
}

/**
 * The address a carrier's API is reached at in the chosen environment.
 *
 * @param array  $definition
 * @param string $environment
 * @return string
 */
function pg_carrier_api_host($definition, $environment)
{
    return ($environment === 'test') ? $definition['hosts']['test'] : $definition['hosts']['production'];
}

/**
 * The token request for a carrier's application.
 *
 * @param array $definition
 * @param array $app pg_carrier_api_app()
 * @return array url, headers, body
 */
function pg_carrier_api_token_request($definition, $app)
{
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    );

    $fields = array('grant_type' => 'client_credentials');

    if ($definition['token_auth'] === 'basic') {
        $headers[] = 'Authorization: Basic ' . base64_encode($app['client_id'] . ':' . $app['client_secret']);
    } else {
        $fields['client_id'] = $app['client_id'];
        $fields['client_secret'] = $app['client_secret'];
    }

    return array(
        'url'     => pg_carrier_api_host($definition, $app['environment']) . $definition['token_path'],
        'headers' => $headers,
        'body'    => http_build_query($fields, '', '&'),
    );
}

/**
 * Reads a token response.
 *
 * @param array|null $data the decoded body
 * @param int        $now
 * @return array|null token, expires; null when the body holds no token
 */
function pg_carrier_api_token_parse($data, $now)
{
    if (!is_array($data) || empty($data['access_token']) || !is_string($data['access_token'])) {
        return null;
    }

    // UPS sends the lifetime as a string; an absent or odd one is taken as
    // short rather than long.
    $lifetime = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;

    return array(
        'token'   => $data['access_token'],
        'expires' => $now + max(60, $lifetime),
    );
}

/**
 * Which credentials a cached token was fetched with, so one kept from
 * before a change of application or environment is not offered.
 *
 * @param array $app
 * @return string
 */
function pg_carrier_api_fingerprint($app)
{
    return hash('sha256', $app['environment'] . '|' . $app['client_id'] . '|' . $app['client_secret']);
}

/**
 * A cached token that still has two minutes to run, or ''.
 *
 * @param array  $cache       what pg_connect_service_get() returned
 * @param string $fingerprint
 * @param int    $now
 * @return string
 */
function pg_carrier_api_cached_token($cache, $fingerprint, $now)
{
    if (!is_array($cache) || empty($cache['token']) || !isset($cache['fingerprint'], $cache['expires'])) {
        return '';
    }

    if (!hash_equals((string) $cache['fingerprint'], $fingerprint) || ((int) $cache['expires'] <= $now + 120)) {
        return '';
    }

    return (string) $cache['token'];
}

/**
 * An access token for the carrier's API.
 *
 * @param string $carrier
 * @param bool   $force   fetch a new one even if a cached one looks valid
 *                        (the API has just refused it)
 * @return array token ('' on failure), error
 */
function pg_carrier_api_token($carrier, $force = false)
{
    $definition = pg_carrier_api_definition($carrier);

    if ($definition === null) {
        return array('token' => '', 'error' => 'Unknown carrier.');
    }

    $app = pg_carrier_api_app($carrier);

    if (($app['client_id'] === '') || ($app['client_secret'] === '')) {
        return array('token' => '', 'error' => lang(array('string' => 'No {var:1} client ID and client secret are set.', 'vars' => array($definition['label']))));
    }

    $key = pg_carrier_api_key($carrier);
    $fingerprint = pg_carrier_api_fingerprint($app);

    if (!$force) {
        $token = pg_carrier_api_cached_token(pg_connect_service_get($key), $fingerprint, time());

        if ($token !== '') {
            return array('token' => $token, 'error' => '');
        }
    }

    // Two checkouts at once would both fetch a token, and the carrier may
    // count the second as a reason to revoke the first.
    $lock = 'pinegrap_carrier_token_' . $carrier;
    $have_lock = (db_value("SELECT GET_LOCK('" . e($lock) . "', 10)") == '1');

    if ($have_lock && !$force) {
        $token = pg_carrier_api_cached_token(pg_connect_service_get($key), $fingerprint, time());

        if ($token !== '') {
            db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
            return array('token' => $token, 'error' => '');
        }
    }

    $request = pg_carrier_api_token_request($definition, $app);
    $response = pg_carrier_api_http('POST', $request['url'], $request['headers'], $request['body']);
    $parsed = ($response['error'] === '') ? pg_carrier_api_token_parse($response['data'], time()) : null;

    if ($parsed !== null) {
        // Without an encryption key nothing can be kept; this request still
        // has its token.
        pg_connect_service_put($key, $definition['label'], array(
            'token'       => $parsed['token'],
            'expires'     => $parsed['expires'],
            'fingerprint' => $fingerprint,
        ));
    }

    if ($have_lock) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
    }

    if ($parsed === null) {
        $error = ($response['error'] !== '') ? $response['error'] : pg_carrier_api_error_text($response['data'], $response['status']);

        return array('token' => '', 'error' => $error);
    }

    return array('token' => $parsed['token'], 'error' => '');
}

/**
 * One HTTP request to a carrier.
 *
 * Certificate checks follow pg_curl_tls(), so a host without a usable CA
 * store can reach the carriers the way it reaches the update server.
 *
 * @param string $method
 * @param string $url
 * @param array  $headers "Name: value" lines
 * @param string $body
 * @return array status, body, data (decoded JSON or null), error (a network
 *               problem; '' when the carrier answered, whatever it said)
 */
function pg_carrier_api_http($method, $url, $headers, $body = '')
{
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => '', 'data' => null, 'error' => 'cURL is not installed.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    // FedEx may compress its answers; curl inflates whatever it offered.
    curl_setopt($ch, CURLOPT_ENCODING, '');
    pg_curl_tls($ch);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($errno) {
        return array(
            'status' => 0,
            'body'   => '',
            'data'   => null,
            'error'  => 'cURL error ' . $errno . ': ' . $error . '.' . pg_curl_tls_hint($errno),
        );
    }

    $response = (string) $response;

    // A gzip body sent without saying so.
    if ((strncmp($response, "\x1f\x8b", 2) === 0) && function_exists('gzdecode')) {
        $inflated = @gzdecode($response);

        if ($inflated !== false) {
            $response = $inflated;
        }
    }

    $data = json_decode($response, true);

    return array(
        'status' => $status,
        'body'   => $response,
        'data'   => is_array($data) ? $data : null,
        'error'  => '',
    );
}

/**
 * The headers of a call to a carrier's API. A request without a body says
 * nothing about a content type: some gateways refuse a GET that names one.
 *
 * @param string $token
 * @param bool   $has_body
 * @param array  $headers extra "Name: value" lines
 * @return array
 */
function pg_carrier_api_request_headers($token, $has_body, $headers = array())
{
    $lines = array('Accept: application/json');

    if ($has_body) {
        $lines[] = 'Content-Type: application/json';
    }

    $lines[] = 'Authorization: Bearer ' . $token;

    return array_merge($lines, $headers);
}

/**
 * A JSON call to a carrier's API with its access token. A token the API
 * refuses is fetched again once: the carrier may revoke one before the time
 * it gave.
 *
 * @param string     $carrier
 * @param string     $method
 * @param string     $path    from the host, e.g. /api/rating/v2409/Shop,
 *                            with its query string for a GET
 * @param array|null $payload sent as JSON; null sends no body
 * @param array      $headers extra "Name: value" lines
 * @return array status, body, data, error ('' only for a 2xx answer)
 */
function pg_carrier_api_call($carrier, $method, $path, $payload, $headers = array())
{
    $definition = pg_carrier_api_definition($carrier);
    $app = pg_carrier_api_app($carrier);
    $json = ($payload === null) ? '' : json_encode($payload);

    $result = array('status' => 0, 'body' => '', 'data' => null, 'error' => '');

    foreach (array(false, true) as $force) {
        $token = pg_carrier_api_token($carrier, $force);

        if ($token['token'] === '') {
            $result['error'] = $token['error'];
            return $result;
        }

        $result = pg_carrier_api_http(
            $method,
            pg_carrier_api_host($definition, $app['environment']) . $path,
            pg_carrier_api_request_headers($token['token'], ($json !== ''), $headers),
            $json);

        if ($result['status'] !== 401) {
            break;
        }
    }

    if (($result['error'] === '') && (($result['status'] < 200) || ($result['status'] > 299))) {
        $result['error'] = pg_carrier_api_error_text($result['data'], $result['status']);
    }

    return $result;
}

/**
 * The carrier's own words for a refused request, as one line.
 *
 * UPS:   {"response": {"errors": [{"code", "message"}]}}
 * FedEx: {"errors": [{"code", "message"}]}
 * USPS:  {"error": {"code", "message", "errors": [{"code", "title", "detail"}]}}
 * OAuth: {"error": "invalid_client", "error_description": "..."}
 *
 * @param array|null $data
 * @param int        $status
 * @return string
 */
function pg_carrier_api_error_text($data, $status)
{
    $errors = array();

    if (is_array($data)) {
        if (isset($data['response']['errors']) && is_array($data['response']['errors'])) {
            $errors = $data['response']['errors'];
        } elseif (isset($data['errors']) && is_array($data['errors'])) {
            $errors = $data['errors'];
        } elseif (isset($data['error']) && is_array($data['error'])) {
            $errors = array($data['error']);

            // USPS names what it refused under the error, e.g. the field.
            if (isset($data['error']['errors']) && is_array($data['error']['errors'])) {
                foreach ($data['error']['errors'] as $detail) {
                    if (!is_array($detail)) {
                        continue;
                    }

                    $errors[] = array(
                        'code'    => isset($detail['code']) ? $detail['code'] : '',
                        'message' => isset($detail['detail']) ? $detail['detail'] : (isset($detail['title']) ? $detail['title'] : ''),
                    );
                }
            }
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $errors = array(array(
                'code'    => $data['error'],
                'message' => isset($data['error_description']) ? $data['error_description'] : '',
            ));
        }
    }

    $lines = array();

    foreach ($errors as $error) {
        if (!is_array($error)) {
            continue;
        }

        $code = isset($error['code']) && is_scalar($error['code']) ? trim((string) $error['code']) : '';
        $message = isset($error['message']) && is_scalar($error['message']) ? trim((string) $error['message']) : '';

        $line = trim($code . (($code !== '') && ($message !== '') ? ': ' : '') . $message);

        if ($line !== '') {
            $lines[] = $line;
        }
    }

    if (!$lines) {
        return 'HTTP ' . (int) $status;
    }

    return implode('; ', array_unique($lines));
}

/**
 * The application fields of a carrier on the Shipping settings card: client
 * ID, client secret (never drawn back), environment and a connection test,
 * with the reason when they cannot be kept yet.
 *
 * @param string $carrier
 * @param bool   $legacy_set the carrier's retired credentials are still on
 *                           file, so the operator is told why rates stopped
 * @return string
 */
function pg_carrier_api_settings_fields($carrier, $legacy_set)
{
    $definition = pg_carrier_api_definition($carrier);
    $app = pg_carrier_api_app($carrier);
    $labels = pg_carrier_api_labels($carrier);
    $name = $carrier . '_';
    $html = '';

    if (!pg_connect_schema_ready()) {
        $html .= '
            <div class="col-12"><div class="alert theme-warning mb-0">' . lang('The database has not been upgraded yet. Run the software update first.') . '</div></div>';
    } elseif (!pg_connect_can_seal()) {
        $html .= '
            <div class="col-12"><div class="alert theme-warning mb-0">' . lang('This site has no encryption key, so it cannot keep the client secret of a carrier.') . '</div></div>';
    } elseif ($legacy_set && !pg_carrier_api_ready($carrier)) {
        $html .= '
            <div class="col-12"><div class="alert theme-warning mb-0">' . lang(array(
                'string' => '{var:1} retired the sign-in with the old credentials. Real-time {var:1} information stays off until the new credentials are entered here. The old values are kept but no longer used.',
                'vars'   => array(h($definition['label'])))) . '</div></div>';
    }

    $html .= '
            <div class="col-12 form-text mt-3">' . lang(array(
                'string' => 'Create an application at {var:1} with these APIs: {var:2}. Then enter its {var:3} and {var:4} here.',
                'vars'   => array(
                    '<a href="' . h($definition['portal']) . '" target="_blank" rel="noopener">' . h(preg_replace('#^https://|/$#', '', $definition['portal'])) . '</a>',
                    h($definition['products']),
                    h($labels['id']),
                    h($labels['secret'])))) . '</div>
            <div class="pg-f-md">
                <label for="' . h($name) . 'client_id" class="form-label">' . h($labels['id']) . '</label>
                <input type="text" name="' . h($name) . 'client_id" id="' . h($name) . 'client_id" class="form-control" value="' . h($app['client_id']) . '" maxlength="255" autocomplete="off"/>
            </div>
            <div class="pg-f-md">
                <label for="' . h($name) . 'client_secret" class="form-label">' . h($labels['secret']) . '</label>
                <input type="password" name="' . h($name) . 'client_secret" id="' . h($name) . 'client_secret" class="form-control" value="" maxlength="255" autocomplete="new-password"' . ($app['secret_stored'] ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"' : '') . '/>
            </div>
            <div class="pg-f-md">
                <label for="' . h($name) . 'environment" class="form-label">' . lang('Environment') . '</label>
                <select name="' . h($name) . 'environment" id="' . h($name) . 'environment" class="form-control">
                    <option value="production"' . (($app['environment'] === 'production') ? ' selected="selected"' : '') . '>' . lang('Production') . '</option>
                    <option value="test"' . (($app['environment'] === 'test') ? ' selected="selected"' : '') . '>' . lang('Test environment') . '</option>
                </select>
            </div>';

    return $html;
}

/**
 * The test button of a carrier's application fields.
 *
 * @param string $carrier
 * @return string
 */
function pg_carrier_api_test_button($carrier)
{
    return '
            <div class="col-12">
                <button type="submit" name="carrier_api_test" value="' . h($carrier) . '" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . lang('Save and test the connection') . '</button>
            </div>';
}

/**
 * Saves the application fields of the carriers whose fields were on the
 * posted form, and runs the connection test that was asked for.
 *
 * @param array    $post     $_POST
 * @param int      $user_id
 * @param liveform $liveform
 */
function pg_carrier_api_settings_save($post, $user_id, $liveform)
{
    foreach (array_keys(pg_carrier_api_definitions()) as $carrier) {
        // A pane without this carrier's fields says nothing about them.
        if (!isset($post[$carrier . '_client_id'])) {
            continue;
        }

        $client_id = trim((string) $post[$carrier . '_client_id']);
        $client_secret = isset($post[$carrier . '_client_secret']) ? trim((string) $post[$carrier . '_client_secret']) : '';
        $environment = (isset($post[$carrier . '_environment']) && ($post[$carrier . '_environment'] === 'test')) ? 'test' : 'production';

        $before = pg_carrier_api_app($carrier);

        // Nothing to keep and nothing kept: no row for a carrier never set up.
        if (($client_id === '') && ($client_secret === '') && ($before['client_id'] === '') && !$before['secret_stored']) {
            continue;
        }

        if (!pg_carrier_api_save($carrier, $client_id, $client_secret, $environment, $user_id)) {
            $liveform->add_notice(lang(array(
                'string' => 'The {var:1} credentials were not saved: {var:2}',
                'vars'   => array(
                    pg_carrier_api_definition($carrier)['label'],
                    !pg_connect_schema_ready()
                        ? lang('The database has not been upgraded yet. Run the software update first.')
                        : lang('This site has no encryption key, so it cannot keep the client secret of a carrier.')))));
        }
    }

    $test = isset($post['carrier_api_test']) ? (string) $post['carrier_api_test'] : '';
    $definition = pg_carrier_api_definition($test);

    if ($definition === null) {
        return;
    }

    $token = pg_carrier_api_token($test, true);

    if ($token['token'] !== '') {
        $liveform->add_notice(lang(array('string' => '{var:1} accepted the credentials.', 'vars' => array($definition['label']))));
    } else {
        $liveform->add_notice(lang(array('string' => '{var:1} refused the connection: {var:2}', 'vars' => array($definition['label'], h($token['error'])))));
    }
}


/* ---------------------------------------------------------------------------
   UPS
   --------------------------------------------------------------------------- */

/**
 * The service codes UPS's Rating API answers with, for the domestic services
 * the shipping methods can be set to.
 *
 * @return array code => service
 */
function pg_ups_services()
{
    return array(
        '01' => 'ups_next_day_air',
        '14' => 'ups_next_day_air_early',
        '13' => 'ups_next_day_air_saver',
        '02' => 'ups_2nd_day_air',
        '59' => 'ups_2nd_day_air_am',
        '12' => 'ups_3_day_select',
        '03' => 'ups_ground',
    );
}

/**
 * A measure as UPS takes it: a string of six characters at most, rounded to
 * two decimals when it is longer.
 *
 * @param mixed $value
 * @return string
 */
function pg_ups_measure($value)
{
    // Drops trailing zeros ("12.000" becomes 12).
    $value = (float) $value + 0;

    if (strlen((string) $value) > 6) {
        $value = round($value, 2);
    }

    return (string) $value;
}

/**
 * The Rating API body for one package, asking for every service (Shop) so the
 * other services' rates are cached from the same answer.
 *
 * One package per request: with several, UPS gives a negotiated total for the
 * shipment only, and the cache keeps a rate per package.
 *
 * @param array  $shipper name, address_1, city, state (two letters), zip_code
 * @param array  $ship_to state, zip_code
 * @param array  $package length, width, height (inches), weight (pounds)
 * @param string $account UPS account number; asks for negotiated rates
 * @return array
 */
function pg_ups_rate_request($shipper, $ship_to, $package, $account)
{
    $shipper_address = array();

    if ($shipper['address_1'] !== '') {
        $shipper_address['AddressLine'] = array(mb_substr($shipper['address_1'], 0, 35));
    }

    if ($shipper['city'] !== '') {
        $shipper_address['City'] = mb_substr($shipper['city'], 0, 30);
    }

    if ($shipper['state'] !== '') {
        $shipper_address['StateProvinceCode'] = $shipper['state'];
    }

    $shipper_address['PostalCode'] = $shipper['zip_code'];
    $shipper_address['CountryCode'] = 'US';

    $shipper_block = array();

    if ($shipper['name'] !== '') {
        $shipper_block['Name'] = mb_substr($shipper['name'], 0, 35);
    }

    if ($account !== '') {
        $shipper_block['ShipperNumber'] = $account;
    }

    $shipper_block['Address'] = $shipper_address;

    $ship_to_address = array('PostalCode' => $ship_to['zip_code']);

    if ($ship_to['state'] !== '') {
        $ship_to_address['StateProvinceCode'] = $ship_to['state'];
    }

    $ship_to_address['CountryCode'] = 'US';

    $package_block = array(
        // Customer-supplied package.
        'PackagingType' => array('Code' => '02'),
    );

    // An item without dimensions is rated by weight alone; UPS refuses a
    // dimension of zero.
    if (($package['length'] > 0) && ($package['width'] > 0) && ($package['height'] > 0)) {
        $package_block['Dimensions'] = array(
            'UnitOfMeasurement' => array('Code' => 'IN'),
            'Length'            => pg_ups_measure($package['length']),
            'Width'             => pg_ups_measure($package['width']),
            'Height'            => pg_ups_measure($package['height']),
        );
    }

    $package_block['PackageWeight'] = array(
        'UnitOfMeasurement' => array('Code' => 'LBS'),
        'Weight'            => pg_ups_measure($package['weight']),
    );

    $shipment = array(
        'Shipper' => $shipper_block,
        'ShipTo'  => array('Address' => $ship_to_address),
    );

    if ($account !== '') {
        $shipment['ShipmentRatingOptions'] = array('NegotiatedRatesIndicator' => 'Y');
    }

    $shipment['Package'] = array($package_block);

    return array(
        'RateRequest' => array(
            'Request'  => array(
                'RequestOption'        => 'Shop',
                'TransactionReference' => array('CustomerContext' => 'Pinegrap rate'),
            ),
            'Shipment' => $shipment,
        ),
    );
}

/**
 * The rates in a Rating API answer.
 *
 * @param array|null $data
 * @param bool       $negotiated an account number was sent, so a negotiated
 *                               rate is preferred where UPS gives one
 * @return array service => rate in cents, for the services UPS rated that the
 *               shipping methods can be set to
 */
function pg_ups_rate_parse($data, $negotiated)
{
    $rates = array();

    if (!is_array($data) || !isset($data['RateResponse']['RatedShipment']) || !is_array($data['RateResponse']['RatedShipment'])) {
        return $rates;
    }

    $shipments = $data['RateResponse']['RatedShipment'];

    // Versions before v2409 sent a lone service as an object.
    if (isset($shipments['Service'])) {
        $shipments = array($shipments);
    }

    $services = pg_ups_services();

    foreach ($shipments as $shipment) {
        if (!is_array($shipment) || !isset($shipment['Service']['Code'])) {
            continue;
        }

        $code = (string) $shipment['Service']['Code'];

        if (!isset($services[$code])) {
            continue;
        }

        $value = '';

        if ($negotiated && isset($shipment['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'])) {
            $value = (string) $shipment['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'];
        }

        if (($value === '') && isset($shipment['TotalCharges']['MonetaryValue'])) {
            $value = (string) $shipment['TotalCharges']['MonetaryValue'];
        }

        if (($value === '') || !is_numeric($value)) {
            continue;
        }

        $rates[$services[$code]] = (int) round(((float) $value) * 100);
    }

    return $rates;
}


/* ---------------------------------------------------------------------------
   FedEx
   --------------------------------------------------------------------------- */

/**
 * The service types FedEx's Rate API answers with, for the domestic services
 * the shipping methods can be set to. SmartPost is not among them: FedEx gives
 * no transit time for it that depends on the address.
 *
 * @return array type => service
 */
function pg_fedex_services()
{
    return array(
        'FIRST_OVERNIGHT'     => 'fedex_first_overnight',
        'PRIORITY_OVERNIGHT'  => 'fedex_priority_overnight',
        'STANDARD_OVERNIGHT'  => 'fedex_standard_overnight',
        'FEDEX_2_DAY_AM'      => 'fedex_2_day_am',
        'FEDEX_2_DAY'         => 'fedex_2_day',
        'FEDEX_EXPRESS_SAVER' => 'fedex_express_saver',
        'FEDEX_GROUND'        => 'fedex_ground',
    );
}

/**
 * A transit time as FedEx words it ("FOUR_DAYS") in days.
 *
 * @param string $words
 * @return int 0 when it is not one FedEx uses
 */
function pg_fedex_transit_days($words)
{
    $numbers = array(
        'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN',
        'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN',
        'EIGHTEEN', 'NINETEEN', 'TWENTY',
    );

    $words = strtoupper(trim((string) $words));

    foreach ($numbers as $index => $number) {
        if (($words === $number . '_DAY') || ($words === $number . '_DAYS')) {
            return $index + 1;
        }
    }

    return 0;
}

/**
 * The Rate API body asking every service for its transit time, for one
 * package of the given weight shipped on the given day.
 *
 * @param array  $shipper zip_code
 * @param array  $recipient zip_code, country (two letters)
 * @param string $ship_date Y-m-d
 * @param float  $weight pounds
 * @param string $account FedEx account number
 * @return array
 */
function pg_fedex_rate_request($shipper, $recipient, $ship_date, $weight, $account)
{
    $country = strtoupper((string) $recipient['country']);

    if ($country === 'USA') {
        $country = 'US';
    }

    return array(
        'accountNumber'                => array('value' => (string) $account),
        'rateRequestControlParameters' => array('returnTransitTimes' => true),
        'requestedShipment'            => array(
            'shipper'                   => array('address' => array(
                'postalCode'  => (string) $shipper['zip_code'],
                'countryCode' => 'US',
            )),
            'recipient'                 => array('address' => array(
                'postalCode'  => (string) $recipient['zip_code'],
                'countryCode' => $country,
            )),
            'shipDateStamp'             => (string) $ship_date,
            'pickupType'                => 'DROPOFF_AT_FEDEX_LOCATION',
            'rateRequestType'           => array('ACCOUNT'),
            'requestedPackageLineItems' => array(
                array('weight' => array(
                    'units' => 'LB',
                    // A product without a weight would be refused; the weight
                    // hardly moves the transit time.
                    'value' => ($weight > 0) ? round((float) $weight, 2) : 1,
                )),
            ),
        ),
    );
}

/**
 * The delivery dates and transit times in a Rate API answer.
 *
 * @param array|null $data
 * @return array service => date (Y-m-d, '' when FedEx gave none),
 *               transit_days (0 when FedEx gave none)
 */
function pg_fedex_delivery_parse($data)
{
    $result = array();

    if (!is_array($data) || !isset($data['output']['rateReplyDetails']) || !is_array($data['output']['rateReplyDetails'])) {
        return $result;
    }

    $services = pg_fedex_services();

    foreach ($data['output']['rateReplyDetails'] as $detail) {
        if (!is_array($detail) || !isset($detail['serviceType']) || !isset($services[(string) $detail['serviceType']])) {
            continue;
        }

        $date = '';

        foreach (array(
            isset($detail['commit']['dateDetail']['dayFormat']) ? $detail['commit']['dateDetail']['dayFormat'] : '',
            isset($detail['operationalDetail']['deliveryDate']) ? $detail['operationalDetail']['deliveryDate'] : '',
            isset($detail['operationalDetail']['commitDate']) ? $detail['operationalDetail']['commitDate'] : '',
        ) as $candidate) {
            if (is_string($candidate) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $candidate, $match)) {
                $date = $match[1];
                break;
            }
        }

        $transit = 0;

        if (isset($detail['commit']['transitDays']['minimumTransitTime'])) {
            $transit = pg_fedex_transit_days($detail['commit']['transitDays']['minimumTransitTime']);
        }

        if (!$transit && isset($detail['operationalDetail']['transitTime'])) {
            $transit = pg_fedex_transit_days($detail['operationalDetail']['transitTime']);
        }

        $result[$services[(string) $detail['serviceType']]] = array(
            'date'         => $date,
            'transit_days' => $transit,
        );
    }

    return $result;
}


/* ---------------------------------------------------------------------------
   USPS
   --------------------------------------------------------------------------- */

/**
 * The mail classes USPS's Domestic Prices and Service Standards APIs name,
 * for the services the shipping methods can be set to. Retail Ground, which
 * the usps_ground service was named after, became USPS Ground Advantage.
 *
 * @return array mail class => service
 */
function pg_usps_services()
{
    return array(
        'PRIORITY_MAIL_EXPRESS' => 'usps_express',
        'PRIORITY_MAIL'         => 'usps_priority',
        'USPS_GROUND_ADVANTAGE' => 'usps_ground',
    );
}

/**
 * @param string $service e.g. usps_priority
 * @return string the mail class, '' for a service USPS is not asked about
 */
function pg_usps_mail_class($service)
{
    $mail_class = array_search((string) $service, pg_usps_services(), true);

    return ($mail_class === false) ? '' : $mail_class;
}

/**
 * A ZIP code as USPS's APIs take it: the five digits, without the +4.
 *
 * @param string $zip_code
 * @return string
 */
function pg_usps_zip($zip_code)
{
    return mb_substr(trim((string) $zip_code), 0, 5);
}

/**
 * Whether USPS sorts the package by machine: within 6-34 x 3-17 x 0.25-17
 * inches and 6 ounces to 35 pounds. Ground Advantage is priced higher for
 * the others.
 *
 * @param array $package length, width, height (inches), weight (pounds)
 * @return bool
 */
function pg_usps_machinable($package)
{
    return ($package['length'] >= 6) && ($package['length'] <= 34)
        && ($package['width'] >= 3) && ($package['width'] <= 17)
        && ($package['height'] >= 0.25) && ($package['height'] <= 17)
        && ($package['weight'] >= 0.375) && ($package['weight'] <= 35);
}

/**
 * The Domestic Prices body for one package at retail prices, asking for every
 * given mail class so the other services' rates are cached from the same
 * answer.
 *
 * @param string $origin_zip
 * @param string $destination_zip
 * @param array  $package      length, width, height (inches), weight (pounds)
 * @param array  $mail_classes
 * @param string $mailing_date Y-m-d; prices change on set dates
 * @return array
 */
function pg_usps_rate_request($origin_zip, $destination_zip, $package, $mail_classes, $mailing_date)
{
    return array(
        'originZIPCode'      => pg_usps_zip($origin_zip),
        'destinationZIPCode' => pg_usps_zip($destination_zip),
        'weight'             => round((float) $package['weight'], 2),
        'length'             => round((float) $package['length'], 2),
        'width'              => round((float) $package['width'], 2),
        'height'             => round((float) $package['height'], 2),
        'mailClasses'        => array_values($mail_classes),
        'priceType'          => 'RETAIL',
        'mailingDate'        => (string) $mailing_date,
        'itemValue'          => 0,
    );
}

/**
 * How well a price option fits a parcel in the shop's own box.
 *
 * Flat Rate and Cubic prices need USPS's boxes or a commercial account, and
 * destination entry prices (DDU, DSCF, SCF, NDC) a drop at a USPS facility,
 * so none of them is what the customer is charged for.
 *
 * @param array $rate one entry of rateOptions[].rates
 * @return int 2 single-piece or dimensional price, 1 a price that names no
 *             rate indicator and does not read as one of those, 0 none
 */
function pg_usps_rate_fit($rate)
{
    $facility = isset($rate['destinationEntryFacilityType']) ? strtoupper(trim((string) $rate['destinationEntryFacilityType'])) : '';

    if (($facility !== '') && ($facility !== 'NONE')) {
        return 0;
    }

    $text = '';

    foreach (array('description', 'productName') as $field) {
        if (isset($rate[$field]) && is_scalar($rate[$field])) {
            $text .= ' ' . strtoupper((string) $rate[$field]);
        }
    }

    if ((strpos($text, 'FLAT RATE') !== false) || (strpos($text, 'CUBIC') !== false) || preg_match('/\b(DDU|DSCF|SCF|NDC)\b/', $text)) {
        return 0;
    }

    $indicator = isset($rate['rateIndicator']) ? strtoupper(trim((string) $rate['rateIndicator'])) : '';

    if ($indicator === '') {
        return 1;
    }

    // Single-piece, and the dimensional prices of large rectangular and
    // nonrectangular parcels.
    return in_array($indicator, array('SP', 'PA', 'DR', 'DN'), true) ? 2 : 0;
}

/**
 * The rates in a Domestic Prices answer.
 *
 * USPS answers with an option per price that could apply. Per service the
 * best-fitting options are kept, then those of the package's processing
 * category, and of what remains the highest price is taken, so a checkout
 * is not charged less than the post office will ask.
 *
 * @param array|null $data
 * @param bool       $machinable pg_usps_machinable()
 * @return array service => rate in cents
 */
function pg_usps_rate_parse($data, $machinable)
{
    $rates = array();

    if (!is_array($data) || !isset($data['rateOptions']) || !is_array($data['rateOptions'])) {
        return $rates;
    }

    $services = pg_usps_services();
    $candidates = array();

    foreach ($data['rateOptions'] as $option) {
        if (!is_array($option) || !isset($option['rates'][0]) || !is_array($option['rates'][0])) {
            continue;
        }

        $rate = $option['rates'][0];
        $mail_class = isset($rate['mailClass']) ? (string) $rate['mailClass'] : '';

        if (!isset($services[$mail_class])) {
            continue;
        }

        $fit = pg_usps_rate_fit($rate);

        if (!$fit) {
            continue;
        }

        $price = 0.0;

        foreach (array(
            isset($option['totalPrice']) ? $option['totalPrice'] : null,
            isset($option['totalBasePrice']) ? $option['totalBasePrice'] : null,
            isset($rate['price']) ? $rate['price'] : null,
        ) as $value) {
            if (is_numeric($value) && ((float) $value > 0)) {
                $price = (float) $value;
                break;
            }
        }

        if ($price <= 0) {
            continue;
        }

        $candidates[$services[$mail_class]][] = array(
            'fit'      => $fit,
            'category' => isset($rate['processingCategory']) ? strtoupper((string) $rate['processingCategory']) : '',
            'cents'    => (int) round($price * 100),
        );
    }

    $categories = $machinable ? array('MACHINABLE') : array('NONSTANDARD', 'NON_MACHINABLE', 'NONMACHINABLE');

    foreach ($candidates as $service => $options) {
        $best_fit = max(array_column($options, 'fit'));

        $options = array_filter($options, function ($option) use ($best_fit) {
            return $option['fit'] === $best_fit;
        });

        $of_category = array_filter($options, function ($option) use ($categories) {
            return in_array($option['category'], $categories, true);
        });

        if ($of_category) {
            $options = $of_category;
        }

        $rates[$service] = max(array_column($options, 'cents'));
    }

    return $rates;
}

/**
 * The Service Standards request for one mail class: when a parcel accepted on
 * the given day is delivered to a street address.
 *
 * @param string $origin_zip
 * @param string $destination_zip
 * @param string $ship_date  Y-m-d
 * @param string $mail_class
 * @return string path and query
 */
function pg_usps_delivery_path($origin_zip, $destination_zip, $ship_date, $mail_class)
{
    return '/service-standards/v3/estimates?' . http_build_query(array(
        'originZIPCode'      => pg_usps_zip($origin_zip),
        'destinationZIPCode' => pg_usps_zip($destination_zip),
        'acceptanceDate'     => (string) $ship_date,
        'mailClass'          => (string) $mail_class,
        'destinationType'    => 'STREET',
    ), '', '&', PHP_QUERY_RFC3986);
}

/**
 * The delivery date in a Service Standards answer.
 *
 * @param array|null $data       a list of estimates, or a lone estimate
 * @param string     $mail_class the class asked for
 * @return string Y-m-d, '' when USPS gave none
 */
function pg_usps_delivery_parse($data, $mail_class)
{
    if (!is_array($data)) {
        return '';
    }

    $estimates = isset($data[0]) ? $data : array($data);

    foreach ($estimates as $estimate) {
        if (!is_array($estimate)) {
            continue;
        }

        if (isset($estimate['mailClass']) && ((string) $estimate['mailClass'] !== (string) $mail_class)) {
            continue;
        }

        foreach (array(
            isset($estimate['delivery']['scheduledDeliveryDateTime']) ? $estimate['delivery']['scheduledDeliveryDateTime'] : '',
            isset($estimate['scheduledDeliveryDateTime']) ? $estimate['scheduledDeliveryDateTime'] : '',
        ) as $candidate) {
            if (is_string($candidate) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $candidate, $match)) {
                return $match[1];
            }
        }
    }

    return '';
}

/**
 * The Addresses request for one address.
 *
 * @param array $address address_1, address_2, city, state (two letters),
 *                       zip_code
 * @return string path and query
 */
function pg_usps_address_path($address)
{
    $query = array('streetAddress' => trim((string) $address['address_1']));

    if (trim((string) $address['address_2']) !== '') {
        $query['secondaryAddress'] = trim((string) $address['address_2']);
    }

    if (trim((string) $address['city']) !== '') {
        $query['city'] = trim((string) $address['city']);
    }

    $query['state'] = strtoupper(trim((string) $address['state']));

    if (pg_usps_zip($address['zip_code']) !== '') {
        $query['ZIPCode'] = pg_usps_zip($address['zip_code']);
    }

    return '/addresses/v3/address?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * What an Addresses answer says about the address.
 *
 * @param int        $status
 * @param array|null $data
 * @return array result: 'verified'; 'secondary' (found, but an apartment,
 *               suite or box number is missing or unknown); 'not_found';
 *               'city', 'state' or 'zip_code' (that field is wrong); 'error'
 *               (USPS gave no usable answer),
 *               address: address_1, address_2, city, state, zip_code as USPS
 *               standardized them, for 'verified' and 'secondary' only
 */
function pg_usps_address_parse($status, $data)
{
    $status = (int) $status;

    if (($status >= 200) && ($status <= 299)) {
        // A blank answer must not blank the customer's address.
        if (!is_array($data) || empty($data['address']['streetAddress'])) {
            return array('result' => 'error', 'address' => null);
        }

        $found = $data['address'];
        $zip_code = isset($found['ZIPCode']) ? (string) $found['ZIPCode'] : '';

        if (!empty($found['ZIPPlus4'])) {
            $zip_code .= '-' . $found['ZIPPlus4'];
        }

        $address = array(
            'address_1' => (string) $found['streetAddress'],
            'address_2' => isset($found['secondaryAddress']) ? (string) $found['secondaryAddress'] : '',
            'city'      => isset($found['city']) ? (string) $found['city'] : '',
            'state'     => isset($found['state']) ? (string) $found['state'] : '',
            'zip_code'  => $zip_code,
        );

        $dpv = isset($data['additionalInfo']['DPVConfirmation']) ? strtoupper(trim((string) $data['additionalInfo']['DPVConfirmation'])) : '';

        // 32: the building was found, the unit was not. 22: several
        // addresses matched.
        $codes = array();

        if (isset($data['corrections']) && is_array($data['corrections'])) {
            foreach ($data['corrections'] as $correction) {
                if (is_array($correction) && isset($correction['code'])) {
                    $codes[] = (string) $correction['code'];
                }
            }
        }

        if (($dpv === 'D') || ($dpv === 'S') || in_array('32', $codes, true)) {
            return array('result' => 'secondary', 'address' => $address);
        }

        if (($dpv === 'N') || in_array('22', $codes, true)) {
            return array('result' => 'not_found', 'address' => null);
        }

        return array('result' => 'verified', 'address' => $address);
    }

    // A network problem or a refused token: nothing is known about the
    // address itself.
    if (($status === 0) || ($status === 401) || ($status === 403) || ($status >= 500)) {
        return array('result' => 'error', 'address' => null);
    }

    $text = strtolower(pg_carrier_api_error_text($data, $status));

    if ((strpos($text, 'address not found') !== false) || (strpos($text, 'multiple') !== false)) {
        return array('result' => 'not_found', 'address' => null);
    }

    if (strpos($text, 'invalid city') !== false) {
        return array('result' => 'city', 'address' => null);
    }

    if (strpos($text, 'invalid state') !== false) {
        return array('result' => 'state', 'address' => null);
    }

    if (strpos($text, 'invalid zip') !== false) {
        return array('result' => 'zip_code', 'address' => null);
    }

    if ($status === 404) {
        return array('result' => 'not_found', 'address' => null);
    }

    return array('result' => 'error', 'address' => null);
}
