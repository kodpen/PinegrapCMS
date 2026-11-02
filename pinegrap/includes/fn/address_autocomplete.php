<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Address autocomplete: suggestions for an address being typed, from a
 * provider account of the site, through the site's own endpoint
 * (address_suggest.php). No provider script and no key reaches the browser,
 * so the cookie notice has nothing to hold.
 *
 * A provider is a driver under includes/geo/autocomplete/<key>.php, listed in
 * pg_addr_providers(), with pg_addr_<key>_suggest() and, when its
 * suggestions carry no address yet (Google Places), pg_addr_<key>_fill().
 * Every driver answers one address shape (pg_addr_empty()); the boxes of a
 * form are filled from it by pg_addr_boxes(), which follows the
 * labels-by-country rule: a Turkish address writes its district (İlçe) into
 * the city box.
 *
 * The account is kept in connect_apps under 'addr_<key>', like the SMS
 * accounts: the key sealed with the site's key and never drawn back into the
 * form, the Photon host among the options, the card's switch as enabled. One
 * provider is on at a time. A provider that fails or is not set up gives no
 * suggestions; what the visitor typed always stays.
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
 * How many suggestions one answer has at most.
 */
define('PG_ADDR_SUGGESTIONS', 5);

/**
 * Requests one address (subject) may make per window: typing asks once per
 * pause, a pick once more.
 */
define('PG_ADDR_RATE_LIMIT', 60);
define('PG_ADDR_RATE_WINDOW', 60);

/**
 * The public Photon instance.
 */
define('PG_ADDR_PHOTON_HOST', 'https://photon.komoot.io');

/**
 * The providers, in the order the settings offer them.
 *
 * @return array key => label, file (under includes/geo/autocomplete/),
 *               needs_key, key_label, help (the card's line, HTML)
 */
function pg_addr_providers()
{
    return array(
        'google_places' => array(
            'label'     => 'Google Places',
            'file'      => 'google_places.php',
            'needs_key' => true,
            'key_label' => lang('API Key'),
            'help'      => lang(array(
                'string' => 'An API key of a {var:1} project with the Places API (New) enabled. Restrict the key to that API; it is used by this server only, so it needs no website restriction.',
                'vars'   => array('<a href="https://console.cloud.google.com/google/maps-apis/" target="_blank" rel="noopener">Google Cloud</a>'))),
        ),
        'mapbox' => array(
            'label'     => 'Mapbox',
            'file'      => 'mapbox.php',
            'needs_key' => true,
            'key_label' => lang('Access Token'),
            'help'      => lang(array(
                'string' => 'An access token of your {var:1} account. A secret token with no URL restriction is best: it is used by this server only.',
                'vars'   => array('<a href="https://account.mapbox.com/access-tokens/" target="_blank" rel="noopener">Mapbox</a>'))),
        ),
        'photon' => array(
            'label'     => 'Photon (OpenStreetMap)',
            'file'      => 'photon.php',
            'needs_key' => false,
            'key_label' => '',
            'help'      => lang(array(
                'string' => 'No account is needed. The public instance {var:1} is for light use; a busy store runs its own Photon server and enters its address here.',
                'vars'   => array('<a href="' . PG_ADDR_PHOTON_HOST . '" target="_blank" rel="noopener">photon.komoot.io</a>'))),
        ),
    );
}

/**
 * @param string $provider
 * @return array|null
 */
function pg_addr_provider_definition($provider)
{
    $providers = pg_addr_providers();

    return (is_string($provider) && isset($providers[$provider])) ? $providers[$provider] : null;
}

/**
 * The connect_apps key a provider's account is kept under.
 *
 * @param string $provider
 * @return string
 */
function pg_addr_app_key($provider)
{
    return 'addr_' . $provider;
}

/**
 * A provider's account on this site.
 *
 * @param string $provider
 * @return array key ('' when none or unreadable), key_stored, host, enabled
 */
function pg_addr_app($provider)
{
    $app = function_exists('pg_connect_app') ? pg_connect_app(pg_addr_app_key($provider)) : null;

    if (!is_array($app)) {
        return array('key' => '', 'key_stored' => false, 'host' => '', 'enabled' => false);
    }

    return array(
        'key'        => (string) $app['client_secret'],
        'key_stored' => (bool) $app['secret_stored'],
        'host'       => (isset($app['options']['host']) && is_string($app['options']['host'])) ? $app['options']['host'] : '',
        'enabled'    => (bool) $app['enabled'],
    );
}

/**
 * Whether an account has what a request needs.
 *
 * @param string $provider
 * @param array  $app pg_addr_app()
 * @return bool
 */
function pg_addr_app_complete($provider, $app)
{
    $definition = pg_addr_provider_definition($provider);

    return ($definition !== null) && (!$definition['needs_key'] || ($app['key'] !== ''));
}

/**
 * The provider suggestions come from: the first switched on with a complete
 * account.
 *
 * @return string '' when autocomplete is off
 */
function pg_addr_provider()
{
    foreach (array_keys(pg_addr_providers()) as $provider) {
        $app = pg_addr_app($provider);

        if ($app['enabled'] && pg_addr_app_complete($provider, $app)) {
            return $provider;
        }
    }

    return '';
}

/**
 * Whether the forms are to offer suggestions.
 *
 * @return bool
 */
function pg_addr_ready()
{
    return pg_addr_provider() !== '';
}

/**
 * Loads a provider's driver.
 *
 * @param string $provider
 * @return bool whether its suggest function is there
 */
function pg_addr_driver($provider)
{
    $definition = pg_addr_provider_definition($provider);

    if ($definition === null) {
        return false;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/geo/autocomplete/' . $definition['file'];

    if (!function_exists('pg_addr_' . $provider . '_suggest') && is_file($file)) {
        require_once($file);
    }

    return function_exists('pg_addr_' . $provider . '_suggest');
}

/**
 * The empty address every driver fills.
 *
 * @return array street, street_2, city, district, state, state_code,
 *               postcode, country (ISO code)
 */
function pg_addr_empty()
{
    return array(
        'street'     => '',
        'street_2'   => '',
        'city'       => '',
        'district'   => '',
        'state'      => '',
        'state_code' => '',
        'postcode'   => '',
        'country'    => '',
    );
}

/**
 * The first non-empty value.
 *
 * @param array $values
 * @return string
 */
function pg_addr_first($values)
{
    foreach ($values as $value) {
        if ((string) $value !== '') {
            return (string) $value;
        }
    }

    return '';
}

/**
 * The street line with its house number where that country writes it:
 * before the street in the English-speaking countries and France, after it
 * elsewhere ("Moda Caddesi 12", "Hauptstraße 5").
 *
 * @param string $street
 * @param string $number
 * @param string $country ISO code
 * @return string
 */
function pg_addr_street_line($street, $number, $country)
{
    $street = trim((string) $street);
    $number = trim((string) $number);

    if (($street === '') || ($number === '')) {
        return $street;
    }

    $number_first = array('US', 'CA', 'GB', 'IE', 'AU', 'NZ', 'FR', 'LU', 'IN', 'ZA', 'SG', 'MY', 'PH');

    return in_array(strtoupper((string) $country), $number_first, true) ? $number . ' ' . $street : $street . ' ' . $number;
}

/**
 * One line naming an address, for a suggestion list.
 *
 * @param array $address pg_addr_empty() shape
 * @return string
 */
function pg_addr_label($address)
{
    $parts = array();

    foreach (array('street', 'district', 'city', 'state', 'postcode', 'country') as $key) {
        $value = trim((string) $address[$key]);

        if (($value !== '') && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

/**
 * The boxes of an address form an address fills, by the labels-by-country
 * rule (commerce/address-labels-by-country.md): a Turkish address is asked
 * as İl in the state box and İlçe in the city box, so its district goes into
 * the city box; elsewhere the city does. The state pick list is matched by
 * the form's script, by state_code or by name.
 *
 * @param array $address pg_addr_empty() shape
 * @return array address_1, address_2, city, state, state_code, zip_code,
 *               country
 */
function pg_addr_boxes($address)
{
    $address = array_merge(pg_addr_empty(), is_array($address) ? $address : array());
    $country = strtoupper((string) $address['country']);

    return array(
        'address_1'  => (string) $address['street'],
        'address_2'  => (string) $address['street_2'],
        'city'       => ($country === 'TR') ? pg_addr_first(array($address['district'], $address['city'])) : (string) $address['city'],
        'state'      => (string) $address['state'],
        'state_code' => (string) $address['state_code'],
        'zip_code'   => (string) $address['postcode'],
        'country'    => $country,
    );
}

/**
 * What was typed, as a provider is asked it: one line of valid UTF-8, at
 * most 200 characters.
 *
 * @param mixed $text
 * @return string '' when it is too short to ask about (under 3 characters)
 */
function pg_addr_text($text)
{
    if (!is_string($text) || !mb_check_encoding($text, 'UTF-8')) {
        return '';
    }

    $text = trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $text));
    $text = mb_substr($text, 0, 200, 'UTF-8');

    return (mb_strlen($text, 'UTF-8') < 3) ? '' : $text;
}

/**
 * A country filter as posted: a two-letter code, '' for none.
 *
 * @param mixed $country
 * @return string upper case
 */
function pg_addr_country($country)
{
    return (is_string($country) && preg_match('/^[A-Za-z]{2}$/D', trim($country))) ? strtoupper(trim($country)) : '';
}

/**
 * A language as posted: a two-letter code, English when it is not one.
 *
 * @param mixed $lang
 * @return string lower case
 */
function pg_addr_lang($lang)
{
    return (is_string($lang) && preg_match('/^([A-Za-z]{2})([_-][A-Za-z]{2})?$/D', trim($lang), $match)) ? strtolower($match[1]) : 'en';
}

/**
 * Calls a provider and decodes its JSON answer. Short timeouts: a visitor
 * is typing and waits on it.
 *
 * @param string $method
 * @param string $url
 * @param array  $options pg_curl_run() options (headers, body)
 * @return array ok, error, refused (the provider refused the key), status,
 *               data
 */
function pg_addr_http($method, $url, $options)
{
    $answer = pg_curl_run($method, $url, $options + array('timeout' => 5, 'connect_timeout' => 3, 'cap' => 524288));

    return pg_addr_http_answer($answer);
}

/**
 * A pg_curl_run() answer as pg_addr_http() returns it.
 *
 * @param array $answer
 * @return array ok, error, refused, status, data
 */
function pg_addr_http_answer($answer)
{
    $status = (int) $answer['status'];
    $data = json_decode((string) $answer['body'], true);

    if ($answer['ok'] && is_array($data)) {
        return array('ok' => true, 'error' => '', 'refused' => false, 'status' => $status, 'data' => $data);
    }

    $error = '';

    // Google: {"error": {"message": …}}; Mapbox: {"message": …}.
    if (is_array($data)) {
        if (isset($data['error']['message']) && is_string($data['error']['message'])) {
            $error = $data['error']['message'];
        } elseif (isset($data['message']) && is_string($data['message'])) {
            $error = $data['message'];
        }
    }

    if ($error === '') {
        $error = ($status > 0) ? 'HTTP ' . $status : (((string) $answer['error'] !== '') ? (string) $answer['error'] : 'No answer.');
    }

    return array(
        'ok'      => false,
        'error'   => mb_substr($error, 0, 300, 'UTF-8'),
        'refused' => in_array($status, array(401, 403), true),
        'status'  => $status,
        'data'    => null,
    );
}

/**
 * The visitor's suggestion store in the session: the provider's session
 * token and the suggestions given, by id. Kept small: a new list replaces
 * the old one.
 *
 * @return array token, provider, items (id => address or null)
 */
function pg_addr_session()
{
    $store = (isset($_SESSION['software']['address_suggest']) && is_array($_SESSION['software']['address_suggest']))
        ? $_SESSION['software']['address_suggest']
        : array();

    return array(
        'token'    => (isset($store['token']) && is_string($store['token'])) ? $store['token'] : '',
        'provider' => (isset($store['provider']) && is_string($store['provider'])) ? $store['provider'] : '',
        'items'    => (isset($store['items']) && is_array($store['items'])) ? $store['items'] : array(),
    );
}

/**
 * A provider session token (a version 4 UUID, as Google asks for).
 *
 * @return string
 */
function pg_addr_session_token()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * Gives each suggestion the id the browser sends back and the store the
 * session keeps: the place id where the provider fills later, a random one
 * where the address is already there.
 *
 * @param array $items a driver's items
 * @return array items (id, label) for the browser, store (id => address or
 *               null)
 */
function pg_addr_items_split($items)
{
    $public = array();
    $store = array();

    foreach (array_slice($items, 0, PG_ADDR_SUGGESTIONS) as $item) {
        $id = ($item['id'] !== '') ? (string) $item['id'] : 'a' . bin2hex(random_bytes(8));

        $public[] = array('id' => $id, 'label' => (string) $item['label']);
        $store[$id] = is_array($item['address']) ? $item['address'] : null;
    }

    return array('items' => $public, 'store' => $store);
}

/**
 * Suggestions for an address being typed, from the site's provider. The
 * suggestions are kept in the session for pg_addr_fill().
 *
 * @param string $text    raw
 * @param string $country raw
 * @param string $lang    raw
 * @return array ok, error, items (id, label)
 */
function pg_addr_suggest($text, $country, $lang)
{
    $provider = pg_addr_provider();
    $text = pg_addr_text($text);

    if (($provider === '') || ($text === '') || !pg_addr_driver($provider)) {
        return array('ok' => ($provider !== ''), 'error' => '', 'items' => array());
    }

    $session = pg_addr_session();

    if (($session['token'] === '') || ($session['provider'] !== $provider)) {
        $session['token'] = pg_addr_session_token();
    }

    $answer = call_user_func('pg_addr_' . $provider . '_suggest', $text, pg_addr_country($country), pg_addr_lang($lang), pg_addr_app($provider), $session['token']);

    if (!$answer['ok']) {
        return array('ok' => false, 'error' => $answer['error'], 'items' => array());
    }

    $split = pg_addr_items_split($answer['items']);

    $_SESSION['software']['address_suggest'] = array(
        'token'    => $session['token'],
        'provider' => $provider,
        'items'    => $split['store'],
    );

    return array('ok' => true, 'error' => '', 'items' => $split['items']);
}

/**
 * The boxes a picked suggestion fills. Only a suggestion this session was
 * given can be picked, so nobody can spend the site's provider calls on
 * places of their own choosing. A pick ends the provider session.
 *
 * @param string $id
 * @param string $lang raw
 * @return array ok, error, boxes (pg_addr_boxes()) or null
 */
function pg_addr_fill($id, $lang)
{
    $session = pg_addr_session();
    $id = is_string($id) ? $id : '';

    if (($id === '') || !array_key_exists($id, $session['items'])) {
        return array('ok' => false, 'error' => 'Unknown suggestion.', 'boxes' => null);
    }

    $address = $session['items'][$id];

    if (!is_array($address)) {
        $provider = $session['provider'];

        if (($provider !== pg_addr_provider()) || !pg_addr_driver($provider) || !function_exists('pg_addr_' . $provider . '_fill')) {
            return array('ok' => false, 'error' => 'Unknown suggestion.', 'boxes' => null);
        }

        $answer = call_user_func('pg_addr_' . $provider . '_fill', $id, pg_addr_lang($lang), pg_addr_app($provider), $session['token']);

        if (!$answer['ok']) {
            return array('ok' => false, 'error' => $answer['error'], 'boxes' => null);
        }

        $address = $answer['address'];
    }

    unset($_SESSION['software']['address_suggest']);

    return array('ok' => true, 'error' => '', 'boxes' => pg_addr_boxes($address));
}

/**
 * Whether one address has asked too often in the window. Counted per
 * address with the firewall's counters, which work with the firewall off.
 *
 * @return bool
 */
function pg_addr_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip))) {
        return false;
    }

    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'addr_suggest', PG_ADDR_RATE_LIMIT, PG_ADDR_RATE_WINDOW);

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 100) === 1)) {
        waf_rate_sweep();
    }

    if (!$exceeded) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-address-suggest', 'rate', 5, 'address_suggest.php', PG_ADDR_RATE_LIMIT . '/' . (int) round(PG_ADDR_RATE_WINDOW / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-address-suggest'));
    }

    return true;
}


/* ---------------------------------------------------------------------------
   Settings › Ecommerce › Address Autocomplete
   --------------------------------------------------------------------------- */

/**
 * Why no account can be kept now, '' when one can.
 *
 * @return string
 */
function pg_addr_settings_refusal()
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return lang('Address autocomplete needs the database upgrade of this version.');
    }

    if (!pg_connect_can_seal()) {
        return lang('Address autocomplete needs the site\'s encryption key, which is not set.');
    }

    return '';
}

/**
 * The provider the card shows: the one on, otherwise the first with a
 * stored account, otherwise the first.
 *
 * @return string
 */
function pg_addr_settings_provider()
{
    $active = pg_addr_provider();

    if ($active !== '') {
        return $active;
    }

    foreach (array_keys(pg_addr_providers()) as $provider) {
        $app = pg_addr_app($provider);

        if ($app['enabled'] || $app['key_stored'] || ($app['host'] !== '')) {
            return $provider;
        }
    }

    $providers = array_keys(pg_addr_providers());

    return $providers[0];
}

/**
 * The card's fields: the switch, the provider, its key (never drawn back)
 * or host, and the test box.
 *
 * @return string HTML
 */
function pg_addr_settings_fields()
{
    $shown = pg_addr_settings_provider();
    $shown_app = pg_addr_app($shown);
    $enabled = $shown_app['enabled'];
    $warning = pg_addr_settings_refusal();

    if (($warning === '') && $enabled && !pg_addr_app_complete($shown, $shown_app)) {
        $warning = $shown_app['key_stored']
            ? lang('Address autocomplete is switched on but gives no suggestions: the stored key cannot be read with this site\'s encryption key. Enter it again.')
            : lang('Address autocomplete is switched on but gives no suggestions until a key is entered.');
    }

    $options = '';

    foreach (pg_addr_providers() as $key => $definition) {
        $options .= '<option value="' . h($key) . '"' . (($key === $shown) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
    }

    $html = '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="addr_enabled" id="addr_enabled" value="1"' . ($enabled ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#addr_row"/></div>
                                                <label class="" for="addr_enabled">' . lang('Suggest addresses as they are typed') . '<br/><span class="form-text">' . lang('The street box of checkout, the address book and the panel\'s address forms lists matching addresses; a pick fills the other boxes. The site asks the provider itself, so no key or provider script reaches the visitor, and what the visitor typed is never replaced unless they pick.') . '</span></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="addr_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">';

    if ($warning !== '') {
        $html .= '
                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . h($warning) . '</div></div>';
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="addr_provider" class="form-label">' . lang('Provider') . '</label>
                                                            <select name="addr_provider" id="addr_provider" class="form-control">' . $options . '</select>
                                                        </div>';

    foreach (pg_addr_providers() as $key => $definition) {
        $app = ($key === $shown) ? $shown_app : pg_addr_app($key);

        $html .= '
                                                        <div class="col-12" data-addr-provider="' . h($key) . '"' . (($key === $shown) ? '' : ' hidden') . '>
                                                            <div class="row gy-5">
                                                                <div class="col-12 form-text">' . $definition['help'] . '</div>';

        if ($definition['needs_key']) {
            $placeholder = ($app['key'] !== '') ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"' : '';

            $html .= '
                                                                <div class="pg-f-md">
                                                                    <label for="addr_' . h($key) . '_key" class="form-label">' . h($definition['key_label']) . '</label>
                                                                    <input type="password" name="addr_' . h($key) . '_key" id="addr_' . h($key) . '_key" class="form-control" value="" maxlength="500" autocomplete="new-password"' . $placeholder . '/>
                                                                </div>';
        } else {
            $html .= '
                                                                <div class="pg-f-md">
                                                                    <label for="addr_' . h($key) . '_host" class="form-label">' . lang('Server Address') . '</label>
                                                                    <input type="url" name="addr_' . h($key) . '_host" id="addr_' . h($key) . '_host" class="form-control" value="' . h($app['host']) . '" maxlength="255" autocomplete="off" placeholder="' . h(PG_ADDR_PHOTON_HOST) . '"/>
                                                                </div>';
        }

        $html .= '
                                                            </div>
                                                        </div>';
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="addr_test_text" class="form-label">' . lang('Test Address') . '</label>
                                                            <input type="text" name="addr_test_text" id="addr_test_text" class="form-control" value="" maxlength="200" autocomplete="off"/>
                                                            <div class="form-text">' . lang('Part of an address, as a visitor would start typing it. The provider may charge the request.') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="submit" name="addr_test" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' . lang('Save and show suggestions') . '</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    return $html;
}

/**
 * The card's script: shows the fields of the provider the select names.
 *
 * @return string
 */
function pg_addr_settings_script()
{
    return '
    <script>
        (function () {
            var select = document.getElementById("addr_provider");

            if (!select) {
                return;
            }

            function sync() {
                Array.prototype.forEach.call(document.querySelectorAll("[data-addr-provider]"), function (pane) {
                    pane.hidden = pane.getAttribute("data-addr-provider") !== select.value;
                });
            }

            select.addEventListener("change", sync);
            sync();
        })();
    </script>';
}

/**
 * One posted value as a trimmed string.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_addr_post($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
}

/**
 * What a post of the card asks for, from the post and the stored account
 * alone: no database, no provider.
 *
 * @param array  $post
 * @param array  $before  provider key => pg_addr_app(), every provider
 * @param string $active  pg_addr_provider() before the save
 * @param string $refusal pg_addr_settings_refusal()
 * @return array|null null when the post has no card; else provider, enabled,
 *                    key (as typed, '' keeps the stored one), host, action
 *                    ('none' or 'save'), test (text to test with, '' for
 *                    none), marks (field => sentence)
 */
function pg_addr_settings_decide($post, $before, $active, $refusal)
{
    if (!isset($post['addr_provider'])) {
        return null;
    }

    $provider = pg_addr_post($post, 'addr_provider');
    $definition = pg_addr_provider_definition($provider);

    if (($definition === null) || !isset($before[$provider])) {
        return null;
    }

    $app = $before[$provider];

    $decision = array(
        'provider' => $provider,
        'enabled'  => (pg_addr_post($post, 'addr_enabled') === '1'),
        'key'      => $definition['needs_key'] ? pg_addr_post($post, 'addr_' . $provider . '_key') : '',
        'host'     => $definition['needs_key'] ? '' : rtrim(pg_addr_post($post, 'addr_' . $provider . '_host'), '/'),
        'action'   => 'none',
        'test'     => (pg_addr_post($post, 'addr_test') === '1') ? pg_addr_text(pg_addr_post($post, 'addr_test_text')) : '',
        'marks'    => array(),
    );

    if (($decision['host'] !== '') && (pg_addr_photon_host_valid($decision['host']) === false)) {
        $decision['marks']['addr_' . $provider . '_host'] = lang('Enter the server\'s address starting with https://.');
    }

    if ($decision['enabled'] && $definition['needs_key'] && ($decision['key'] === '') && ($app['key'] === '')) {
        $decision['marks']['addr_' . $provider . '_key'] = lang('Enter the key, or switch address autocomplete off.');
    }

    if ((pg_addr_post($post, 'addr_test') === '1') && ($decision['test'] === '')) {
        $decision['marks']['addr_test_text'] = lang('Type at least three characters of an address.');
    }

    $changed = ($decision['key'] !== '')
        || ($decision['host'] !== $app['host'])
        || ($decision['enabled'] !== $app['enabled'])
        || ($decision['enabled'] && ($active !== $provider));

    if ($changed) {
        $decision['action'] = 'save';

        if ($refusal !== '') {
            $decision['marks']['addr_enabled'] = $refusal;
        }
    }

    // A test asks the provider as saved, so it runs only with it on.
    if (($decision['test'] !== '') && !$decision['enabled']) {
        $decision['marks']['addr_enabled'] = lang('Switch address autocomplete on to test it.');
    }

    return $decision;
}

/**
 * Whether a typed Photon host is an https address.
 *
 * @param string $host
 * @return bool
 */
function pg_addr_photon_host_valid($host)
{
    return (bool) preg_match('#^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~/-]*)?$#D', (string) $host);
}

/**
 * Decides the card's post and marks what it refuses. Runs before the
 * Ecommerce screen writes anything.
 *
 * @param array    $post
 * @param liveform $liveform
 * @return array|null pg_addr_settings_decide()
 */
function pg_addr_settings_check($post, $liveform)
{
    if (!isset($post['addr_provider'])) {
        return null;
    }

    $before = array();

    foreach (array_keys(pg_addr_providers()) as $provider) {
        $before[$provider] = pg_addr_app($provider);
    }

    $decision = pg_addr_settings_decide($post, $before, pg_addr_provider(), pg_addr_settings_refusal());

    if ($decision !== null) {
        foreach ($decision['marks'] as $field => $sentence) {
            $liveform->mark_error($field, h($sentence));
        }
    }

    return $decision;
}

/**
 * Writes what pg_addr_settings_check() decided: the account, the others
 * switched off when this one is on, then the test.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_addr_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision) || $decision['marks']) {
        return;
    }

    if ($decision['action'] === 'save') {
        $options = ($decision['host'] !== '') ? array('host' => $decision['host']) : array();

        if (!pg_connect_app_save(pg_addr_app_key($decision['provider']), '', $decision['key'], $options, $decision['enabled'], $user_id)) {
            $liveform->add_warning(h(pg_addr_settings_refusal()));
            return;
        }

        if ($decision['enabled']) {
            foreach (array_keys(pg_addr_providers()) as $provider) {
                if ($provider !== $decision['provider']) {
                    db("UPDATE connect_apps SET enabled = '0' WHERE provider = '" . e(pg_addr_app_key($provider)) . "'");
                }
            }
        }
    }

    if ($decision['test'] === '') {
        return;
    }

    $result = pg_addr_suggest($decision['test'], '', defined('SOFTWARE_LANGUAGE') ? (string) SOFTWARE_LANGUAGE : 'en');

    if (!$result['ok']) {
        $liveform->add_warning(lang(array('string' => 'The provider did not answer the test: {var:1}', 'vars' => array(h($result['error'] !== '' ? $result['error'] : lang('not set up'))))));
    } elseif (!$result['items']) {
        $liveform->add_notice(lang('The provider answered the test with no suggestions. Try more of the address.'));
    } else {
        $labels = array();

        foreach ($result['items'] as $item) {
            $labels[] = h($item['label']);
        }

        $liveform->add_notice(lang(array('string' => 'Suggestions for the test address: {var:1}', 'vars' => array(implode(' · ', $labels)))));
    }

    unset($_SESSION['software']['address_suggest']);
}
