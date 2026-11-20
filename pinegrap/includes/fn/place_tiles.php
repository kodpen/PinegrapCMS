<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The map of the "Map of places" block: the "Maps" switch of Site Settings,
 * Features, the tile address, its credit line and its key, what a page with
 * the block hands pg_map.js, the map's row in the cookie notice and the line
 * the Firewall settings offer when the site's own security policy would
 * refuse the tiles.
 *
 * Leaflet (assets/lib/leaflet/, unmodified) draws the map in the visitor's
 * browser, raster tiles only. Nothing here sends a security header: the
 * built-in policy already lets pictures from any https host through, so the
 * headers are the same with the switch on and off.
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
 * config_text rows: the switch ('1' on, removed when off) and the tiles
 * (JSON: url, credit, key sealed with the site's key).
 */
define('PG_PLACE_TILES_SWITCH', 'place_map_on');
define('PG_PLACE_TILES_SETTING', 'place_map_tiles');

/**
 * OpenStreetMap's own tiles and the credit their usage policy asks for.
 */
define('PG_PLACE_TILES_DEFAULT_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
define('PG_PLACE_TILES_DEFAULT_CREDIT', '© OpenStreetMap contributors');
define('PG_PLACE_TILES_DEFAULT_CREDIT_LINK', 'https://www.openstreetmap.org/copyright');

define('PG_PLACE_TILES_URL_MAX', 500);
define('PG_PLACE_TILES_CREDIT_MAX', 200);
define('PG_PLACE_TILES_KEY_MAX', 200);

if (!function_exists('pg_config_text')) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
}

/**
 * Whether the "Maps" switch is on. Off on every site until it is switched on.
 *
 * @return bool
 */
function pg_place_tiles_switch_on()
{
    return pg_schema_has('config_text') && (pg_config_text(PG_PLACE_TILES_SWITCH) === '1');
}

/**
 * @param bool $on
 * @return bool
 */
function pg_place_tiles_switch_save($on)
{
    return (bool) pg_config_text_save(PG_PLACE_TILES_SWITCH, $on ? '1' : '');
}

/**
 * Whether a tile address can be used: https, the three places {z}, {x} and
 * {y}, no space, quote or angle bracket, a host. {s} and {key} may appear.
 * Pure.
 *
 * @param string $url
 * @return bool
 */
function pg_place_tiles_url_valid($url)
{
    $url = (string) $url;

    if (($url === '') || (strlen($url) > PG_PLACE_TILES_URL_MAX) || (stripos($url, 'https://') !== 0)) {
        return false;
    }

    if (preg_match('/[\s"\'<>\\\\`]/', $url)) {
        return false;
    }

    foreach (array('{z}', '{x}', '{y}') as $part) {
        if (strpos($url, $part) === false) {
            return false;
        }
    }

    return pg_place_tiles_host($url) !== '';
}

/**
 * The host of a tile address, lower case; {s} (Leaflet's subdomain letter)
 * stands as a wildcard: "{s}.tile.example.org" gives "*.tile.example.org".
 * '' when there is none. Pure.
 *
 * @param string $url
 * @return string
 */
function pg_place_tiles_host($url)
{
    if (!preg_match('~^https://([^/?#:]+)~i', (string) $url, $match)) {
        return '';
    }

    $host = strtolower(str_replace('{s}', '*', $match[1]));

    return preg_match('/^(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) ? $host : '';
}

/**
 * The stored tiles with the defaults filled in. The key is never handed
 * back to a screen: only whether one is kept.
 *
 * @return array url, credit, key_stored (bool)
 */
function pg_place_tiles_settings()
{
    $stored = pg_schema_has('config_text') ? json_decode((string) pg_config_text(PG_PLACE_TILES_SETTING), true) : null;
    $stored = is_array($stored) ? $stored : array();

    $url = isset($stored['url']) ? (string) $stored['url'] : '';
    $credit = isset($stored['credit']) ? trim((string) $stored['credit']) : '';

    return array(
        'url'        => pg_place_tiles_url_valid($url) ? $url : PG_PLACE_TILES_DEFAULT_URL,
        'credit'     => ($credit !== '') ? $credit : PG_PLACE_TILES_DEFAULT_CREDIT,
        'key_stored' => isset($stored['key']) && ((string) $stored['key'] !== ''),
    );
}

/**
 * The key, opened with the site's key; '' when none is kept or it cannot be
 * read.
 *
 * @return string
 */
function pg_place_tiles_key()
{
    $stored = pg_schema_has('config_text') ? json_decode((string) pg_config_text(PG_PLACE_TILES_SETTING), true) : null;

    if (!is_array($stored) || empty($stored['key']) || !function_exists('pg_connect_unseal')) {
        return '';
    }

    $open = pg_connect_unseal((string) $stored['key']);

    return isset($open['key']) ? (string) $open['key'] : '';
}

/**
 * What a save of the card does with the three boxes, without writing it.
 * An empty address or credit means the default; an empty key box keeps the
 * kept key, the tick removes it. Pure.
 *
 * @param string $url
 * @param string $credit
 * @param string $key        typed key, '' to keep the kept one
 * @param bool   $key_remove
 * @return array url, credit (stored, '' for the default), key: 'keep', 'remove' or the new key; error: '' or 'url'
 */
function pg_place_tiles_to_store($url, $credit, $key, $key_remove)
{
    $url = trim((string) $url);
    $credit = trim(preg_replace('/\s+/u', ' ', (string) $credit));
    $key = trim((string) $key);

    if (($url !== '') && !pg_place_tiles_url_valid($url)) {
        return array('url' => '', 'credit' => '', 'key' => 'keep', 'error' => 'url');
    }

    if (function_exists('mb_substr')) {
        $credit = mb_substr($credit, 0, PG_PLACE_TILES_CREDIT_MAX);
    }

    return array(
        'url'    => ($url === PG_PLACE_TILES_DEFAULT_URL) ? '' : $url,
        'credit' => ($credit === PG_PLACE_TILES_DEFAULT_CREDIT) ? '' : $credit,
        'key'    => $key_remove ? 'remove' : (($key !== '') ? substr($key, 0, PG_PLACE_TILES_KEY_MAX) : 'keep'),
        'error'  => '',
    );
}

/**
 * Saves the card's tiles. A key is kept only sealed with the site's key.
 *
 * @param string $url
 * @param string $credit
 * @param string $key
 * @param bool   $key_remove
 * @return string '' when saved, else the reason it was not, for the screen
 */
function pg_place_tiles_save($url, $credit, $key, $key_remove)
{
    $plan = pg_place_tiles_to_store($url, $credit, $key, $key_remove);

    if ($plan['error'] === 'url') {
        return lang('The tile address was not saved: it must start with https:// and hold {z}, {x} and {y}.');
    }

    $before = json_decode((string) pg_config_text(PG_PLACE_TILES_SETTING), true);
    $sealed = (is_array($before) && isset($before['key'])) ? (string) $before['key'] : '';
    $refusal = '';

    if ($plan['key'] === 'remove') {
        $sealed = '';
    } elseif ($plan['key'] !== 'keep') {
        if (function_exists('pg_connect_can_seal') && pg_connect_can_seal()) {
            $sealed = pg_connect_seal(array('key' => $plan['key']));
        } else {
            $refusal = lang('This site has no encryption key, so it cannot keep the key of a map provider.');
        }
    }

    $row = array_filter(array('url' => $plan['url'], 'credit' => $plan['credit'], 'key' => $sealed), 'strlen');
    pg_config_text_save(PG_PLACE_TILES_SETTING, $row ? json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '');

    return $refusal;
}

/**
 * The tile address a page uses: {key} replaced by the key. Pure.
 *
 * @param string $url
 * @param string $key
 * @return string
 */
function pg_place_tiles_address($url, $key)
{
    return str_replace('{key}', rawurlencode((string) $key), (string) $url);
}

/**
 * The credit as HTML for the map's corner, escaped; OpenStreetMap's own
 * credit links to its copyright page. Pure.
 *
 * @param string $credit
 * @return string
 */
function pg_place_tiles_credit_html($credit)
{
    $credit = (string) $credit;

    if ($credit === PG_PLACE_TILES_DEFAULT_CREDIT) {
        return '<a href="' . PG_PLACE_TILES_DEFAULT_CREDIT_LINK . '" target="_blank" rel="noopener">' . h($credit) . '</a>';
    }

    return h($credit);
}

/**
 * What pg_map.js is given about the map. The same for every visitor, so the
 * page can be cached: whether to draw the map, whether to wait for the
 * visitor's yes to marketing in the cookie notice (and the button's text),
 * the tiles and the library's files. With the switch off only {on: false}.
 *
 * @param bool  $on      the switch
 * @param bool  $consent the cookie notice is on
 * @param array $tiles   url (with the key in), credit
 * @param string $lib    the library folder's address
 * @return array
 */
function pg_place_tiles_page_config($on, $consent, $tiles, $lib)
{
    if (!$on) {
        return array('on' => false);
    }

    return array(
        'on'      => true,
        'consent' => (bool) $consent,
        'tiles'   => (string) $tiles['url'],
        'credit'  => pg_place_tiles_credit_html($tiles['credit']),
        'script'  => $lib . '/leaflet.js',
        'style'   => $lib . '/leaflet.css',
    );
}

/**
 * The page's map configuration from the settings.
 *
 * @return array
 */
function pg_place_tiles_page()
{
    $on = pg_place_tiles_switch_on();

    if (!$on) {
        return pg_place_tiles_page_config(false, false, array(), '');
    }

    $settings = pg_place_tiles_settings();
    $lib = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/leaflet';
    $version = @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/leaflet/leaflet.js');
    $config = pg_place_tiles_page_config(
        true,
        function_exists('pg_consent_enabled') && pg_consent_enabled(),
        array('url' => pg_place_tiles_address($settings['url'], pg_place_tiles_key()), 'credit' => $settings['credit']),
        $lib);
    $config['script'] .= '?v=' . (int) $version;
    $config['style'] .= '?v=' . (int) $version;

    return $config;
}

/**
 * The name the cookie notice lists the map under, as a service of the
 * marketing class: OpenStreetMap for its own tiles, else the tile host.
 * '' when the page holds no block or the switch is off, so no row. Pure.
 *
 * @param bool   $on_page the page holds the block
 * @param bool   $on      the switch
 * @param string $url     the tile address
 * @return string
 */
function pg_place_tiles_consent_name($on_page, $on, $url)
{
    if (!$on_page || !$on) {
        return '';
    }

    return ($url === PG_PLACE_TILES_DEFAULT_URL) ? 'OpenStreetMap' : pg_place_tiles_host($url);
}

/**
 * Whether a security policy lets the tile host's pictures through: its
 * img-src, else its default-src, holds https:, *, the host or a wildcard
 * over it. A policy naming neither lets everything through. Pure.
 *
 * @param string $policy
 * @param string $host   from pg_place_tiles_host()
 * @return bool
 */
function pg_place_tiles_csp_allows($policy, $host)
{
    $directives = array();

    foreach (explode(';', (string) $policy) as $part) {
        $words = preg_split('/\s+/', strtolower(trim($part)), -1, PREG_SPLIT_NO_EMPTY);

        if ($words && !isset($directives[$words[0]])) {
            $directives[$words[0]] = array_slice($words, 1);
        }
    }

    $sources = isset($directives['img-src']) ? $directives['img-src'] : (isset($directives['default-src']) ? $directives['default-src'] : null);

    if ($sources === null) {
        return true;
    }

    $host = strtolower((string) $host);

    foreach ($sources as $source) {
        if (in_array($source, array('*', 'https:'), true)) {
            return true;
        }

        $source = preg_replace('~^https://~', '', rtrim($source, '/'));

        if ($source === $host) {
            return true;
        }

        // *.example.org covers a.example.org, and the {s} host
        // *.tile.example.org it is written over.
        if ((strpos($source, '*.') === 0) && (substr($host, -strlen($source) + 1) === substr($source, 1))) {
            return true;
        }
    }

    return false;
}

/**
 * The line an operator copies into their own policy. Pure.
 *
 * @param string $host
 * @return string
 */
function pg_place_tiles_csp_text($host)
{
    return 'img-src https://' . $host;
}

/**
 * The Firewall settings' warning while the switch is on and the site's own
 * policy would refuse the tiles; '' otherwise.
 *
 * @param string $policy the site's own policy, '' for the built-in one
 * @return string HTML
 */
function pg_place_tiles_firewall_note($policy)
{
    if ((trim((string) $policy) === '') || !pg_place_tiles_switch_on()) {
        return '';
    }

    $host = pg_place_tiles_host(pg_place_tiles_settings()['url']);

    if (($host === '') || pg_place_tiles_csp_allows($policy, $host)) {
        return '';
    }

    return lang(array(
        'string' => 'Maps are on, and this policy does not let the map\'s pictures through. Add this to it, or the map stays empty: {var:1}',
        'vars'   => array('<code class="user-select-all">' . h(pg_place_tiles_csp_text($host)) . '</code>')));
}
