<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * EU VAT id checks: the shape of a VAT id per member state, the check
 * against the European Commission's VIES service, a day's cache of its
 * answers and the reverse-charge decision for an order. Everything but
 * pg_vat_check() and the cache helpers is pure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The VIES REST check: one POST per id, a JSON body of countryCode and vatNumber.
define('PG_VAT_VIES_URL', 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number');

// A VIES answer (valid or invalid) is kept this long; an unavailable answer is never kept.
define('PG_VAT_CACHE_TTL', 86400);

/**
 * The VIES member state prefixes with the pattern of the number after the
 * prefix. Greece is EL in VIES (GR in ISO 3166); XI is Northern Ireland,
 * which VIES checks for goods. Pure.
 *
 * @return array<string,string> prefix => regular expression without delimiters
 */
function pg_vat_id_patterns()
{
    return array(
        'AT' => 'U[0-9]{8}',
        'BE' => '[01][0-9]{9}',
        'BG' => '[0-9]{9,10}',
        'CY' => '[0-9]{8}[A-Z]',
        'CZ' => '[0-9]{8,10}',
        'DE' => '[0-9]{9}',
        'DK' => '[0-9]{8}',
        'EE' => '[0-9]{9}',
        'EL' => '[0-9]{9}',
        'ES' => '[A-Z0-9][0-9]{7}[A-Z0-9]',
        'FI' => '[0-9]{8}',
        'FR' => '[A-HJ-NP-Z0-9]{2}[0-9]{9}',
        'HR' => '[0-9]{11}',
        'HU' => '[0-9]{8}',
        'IE' => '([0-9]{7}[A-W][A-IW]?|[0-9][A-Z+*][0-9]{5}[A-W])',
        'IT' => '[0-9]{11}',
        'LT' => '([0-9]{9}|[0-9]{12})',
        'LU' => '[0-9]{8}',
        'LV' => '[0-9]{11}',
        'MT' => '[0-9]{8}',
        'NL' => '[0-9]{9}B[0-9]{2}',
        'PL' => '[0-9]{10}',
        'PT' => '[0-9]{9}',
        'RO' => '[1-9][0-9]{1,9}',
        'SE' => '[0-9]{10}01',
        'SI' => '[0-9]{8}',
        'SK' => '[0-9]{10}',
        'XI' => '([0-9]{9}|[0-9]{12}|GD[0-4][0-9]{2}|HA[5-9][0-9]{2})',
    );
}

/**
 * The VIES prefix of an ISO 3166-1 alpha-2 country code, '' for a country
 * VIES does not check. GR becomes EL; GB gives '' (only Northern Ireland's
 * XI ids are checked, and a billing country does not tell it). Pure.
 *
 * @param string $country
 * @return string
 */
function pg_vat_vies_prefix($country)
{
    $country = strtoupper(trim((string) $country));

    // EL and XI are VIES prefixes, not ISO codes.
    if (in_array($country, array('EL', 'XI', 'GB'), true)) {
        return '';
    }

    if ($country === 'GR') {
        $country = 'EL';
    }

    $patterns = pg_vat_id_patterns();

    return isset($patterns[$country]) ? $country : '';
}

/**
 * Whether an ISO country code is an EU member state. Pure.
 *
 * @param string $country
 * @return bool
 */
function pg_vat_eu_member($country)
{
    return pg_vat_vies_prefix($country) !== '';
}

/**
 * Cleans a typed VAT id: spaces, dots, dashes, slashes and underscores go,
 * letters are upper-cased, the member state prefix is read from the id or,
 * when the id has none, taken from $country, and the number is checked
 * against the member state's pattern. Pure.
 *
 * error: '' when the id is well formed; 'empty', 'characters' (a character
 * no VAT id has), 'country' (no member state prefix and none from $country),
 * 'format' (the number does not fit the member state's pattern).
 *
 * @param string $id
 * @param string $country ISO code of the billing country, used when the id has no prefix
 * @return array{id:string,country:string,number:string,error:string}
 */
function pg_vat_id_clean($id, $country = '')
{
    $answer = array('id' => '', 'country' => '', 'number' => '', 'error' => '');
    $raw = strtoupper(preg_replace('/[\s.\-\/_]+/u', '', (string) $id));

    if ($raw === '') {
        $answer['error'] = 'empty';
        return $answer;
    }

    if (!preg_match('/^[A-Z0-9+*]+$/', $raw)) {
        $answer['error'] = 'characters';
        return $answer;
    }

    $patterns = pg_vat_id_patterns();
    $prefix = substr($raw, 0, 2);

    // An id typed with the ISO code of Greece is the same id.
    if ($prefix === 'GR') {
        $prefix = 'EL';
    }

    if (isset($patterns[$prefix]) && preg_match('/^[A-Z]{2}/', $raw)) {
        $number = (string) substr($raw, 2);
    } else {
        $prefix = pg_vat_vies_prefix($country);
        $number = $raw;

        if ($prefix === '') {
            $answer['error'] = 'country';
            return $answer;
        }
    }

    $answer['country'] = $prefix;
    $answer['number'] = $number;
    $answer['id'] = $prefix . $number;

    if (!preg_match('/^' . $patterns[$prefix] . '$/', $number)) {
        $answer['error'] = 'format';
    }

    return $answer;
}

/**
 * The request pg_vat_check() sends for a cleaned id, so its shape is
 * testable. The requester's own id, when given and well formed, makes VIES
 * answer a consultation number, the proof an auditor asks for. Pure.
 *
 * @param array  $clean     an answer of pg_vat_id_clean() without error
 * @param string $requester the site's own VAT id, '' for none
 * @return array{url:string,body:string,headers:string[]}
 */
function pg_vat_check_request(array $clean, $requester = '')
{
    $body = array(
        'countryCode' => (string) ($clean['country'] ?? ''),
        'vatNumber'   => (string) ($clean['number'] ?? ''),
    );

    $own = pg_vat_id_clean($requester);

    if (($own['error'] === '') && ($own['country'] !== 'XI')) {
        $body['requesterMemberStateCode'] = $own['country'];
        $body['requesterNumber'] = $own['number'];
    }

    return array(
        'url'     => PG_VAT_VIES_URL,
        'body'    => json_encode($body),
        'headers' => array('Content-Type: application/json', 'Accept: application/json'),
    );
}

/**
 * Maps a VIES answer, in the shape pg_curl_run() returns, to a check
 * result. Pure.
 *
 * status: 'valid', 'invalid' (VIES says the member state has no such id),
 * 'unavailable' (no answer, an error status, a member state or VIES itself
 * down, too many requests: the id is neither confirmed nor refused).
 * error: the VIES error code or 'http_<status>' / 'network' / 'answer' when
 * status is 'unavailable', else ''.
 *
 * @param array $response ok, status, body, errno
 * @param int   $now      unix time written as checked_at
 * @return array{status:string,name:string,address:string,request_id:string,checked_at:int,error:string}
 */
function pg_vat_check_map(array $response, $now)
{
    $result = array(
        'status'     => 'unavailable',
        'name'       => '',
        'address'    => '',
        'request_id' => '',
        'checked_at' => (int) $now,
        'error'      => '',
    );

    $data = json_decode((string) ($response['body'] ?? ''), true);

    // VIES names a refused call in the body (errorWrappers, or userError
    // other than VALID / INVALID), with a 4xx or 5xx status or a 200.
    $code = '';

    if (is_array($data)) {
        if (!empty($data['errorWrappers']) && is_array($data['errorWrappers'])) {
            $first = reset($data['errorWrappers']);
            $code = is_array($first) ? (string) ($first['error'] ?? '') : '';

            if ($code === '') {
                $code = 'answer';
            }
        } elseif (isset($data['userError']) && !in_array($data['userError'], array('VALID', 'INVALID'), true)) {
            $code = (string) $data['userError'];
        }
    }

    if ($code !== '') {
        $result['error'] = $code;
        return $result;
    }

    if (empty($response['ok'])) {
        $status = (int) ($response['status'] ?? 0);
        $result['error'] = ($status > 0) ? 'http_' . $status : 'network';
        return $result;
    }

    if (!is_array($data) || !array_key_exists('valid', $data)) {
        $result['error'] = 'answer';
        return $result;
    }

    if ($data['valid'] !== true) {
        $result['status'] = 'invalid';
        return $result;
    }

    $result['status'] = 'valid';

    // VIES answers "---" where a member state does not disclose the name or address.
    foreach (array('name', 'address') as $key) {
        $value = trim(preg_replace('/\s+/u', ' ', (string) ($data[$key] ?? '')));
        $result[$key] = ($value === '---') ? '' : $value;
    }

    $result['request_id'] = (string) ($data['requestIdentifier'] ?? '');

    return $result;
}

/**
 * The reverse-charge decision for an order: whether a checked VAT id sets
 * the order's tax-exempt flag. Only a VIES-confirmed id of an EU member
 * state that is the billing country, sold to from a site in another member
 * state, is exempt. Pure.
 *
 * reason: 'exempt', or why not: 'no_check', 'not_eu' (the billing country
 * or the site's is outside the EU), 'same_country' (a domestic sale is
 * taxed), 'other_country' (the id belongs to a member state other than the
 * billing country), 'unavailable' (VIES did not answer: the order is taxed
 * and carries a note), 'invalid'.
 *
 * @param array  $check           an answer of pg_vat_check(), array() for none
 * @param string $billing_country ISO code
 * @param string $site_country    ISO code of the seller
 * @return array{exempt:bool,reason:string}
 */
function pg_vat_exempt_decision(array $check, $billing_country, $site_country)
{
    $billing = pg_vat_vies_prefix($billing_country);
    $site = pg_vat_vies_prefix($site_country);

    if (empty($check['status'])) {
        return array('exempt' => false, 'reason' => 'no_check');
    }

    if (($billing === '') || ($site === '')) {
        return array('exempt' => false, 'reason' => 'not_eu');
    }

    if ($billing === $site) {
        return array('exempt' => false, 'reason' => 'same_country');
    }

    if ($check['status'] === 'malformed') {
        return array('exempt' => false, 'reason' => 'invalid');
    }

    if ((string) ($check['country'] ?? '') !== $billing) {
        return array('exempt' => false, 'reason' => 'other_country');
    }

    if ($check['status'] === 'valid') {
        return array('exempt' => true, 'reason' => 'exempt');
    }

    if ($check['status'] === 'unavailable') {
        return array('exempt' => false, 'reason' => 'unavailable');
    }

    return array('exempt' => false, 'reason' => 'invalid');
}

/**
 * The cache file of a cleaned id.
 *
 * @param string $id
 * @return string
 */
function pg_vat_cache_file($id)
{
    return PG_FUNCTIONS_DIR . '/data/cache/vat/' . sha1('vies|' . (string) $id) . '.json';
}

/**
 * A kept answer for a cleaned id younger than a day, else null. A stale file
 * is removed.
 *
 * @param string $id
 * @param int    $now
 * @return array|null
 */
function pg_vat_cache_get($id, $now)
{
    $file = pg_vat_cache_file($id);

    if (!is_file($file)) {
        return null;
    }

    $data = json_decode((string) @file_get_contents($file), true);

    if (
        !is_array($data)
        || (($data['id'] ?? '') !== (string) $id)
        || ((int) $now - (int) ($data['checked_at'] ?? 0) >= PG_VAT_CACHE_TTL)
    ) {
        @unlink($file);
        return null;
    }

    return $data;
}

/**
 * Keeps a valid or invalid answer for a day; an unavailable one is not kept,
 * so the next try asks VIES again. Written to a temporary file and renamed.
 *
 * @param array $result an answer of pg_vat_check()
 * @return bool
 */
function pg_vat_cache_put(array $result)
{
    if (!in_array($result['status'] ?? '', array('valid', 'invalid'), true) || (($result['id'] ?? '') === '')) {
        return false;
    }

    $file = pg_vat_cache_file($result['id']);
    $directory = dirname($file);

    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $raw = json_encode($result);
    $temporary = $file . '.' . str_replace('.', '', uniqid('', true)) . '.tmp';

    if (@file_put_contents($temporary, $raw) !== strlen($raw)) {
        @unlink($temporary);
        return false;
    }

    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

/**
 * Checks a VAT id against VIES. A malformed id is answered without a call
 * (status 'malformed', error the pg_vat_id_clean() code); a valid or invalid
 * answer is kept a day in data/cache/vat/, so a customer going back and
 * forth through checkout asks once.
 *
 * Options: 'country' (ISO code of the billing country, for an id typed
 * without a prefix), 'requester' (the site's own VAT id), 'fresh' (skip the
 * cache), 'now', 'http' (a callable taking the pg_curl_run() arguments, for
 * tests).
 *
 * @param string $id
 * @param array  $options
 * @return array{id:string,country:string,number:string,status:string,name:string,address:string,request_id:string,checked_at:int,error:string,cached:bool}
 */
function pg_vat_check($id, array $options = array())
{
    $now = isset($options['now']) ? (int) $options['now'] : time();
    $clean = pg_vat_id_clean($id, (string) ($options['country'] ?? ''));

    $base = array(
        'id'      => $clean['id'],
        'country' => $clean['country'],
        'number'  => $clean['number'],
        'cached'  => false,
    );

    if ($clean['error'] !== '') {
        return $base + array(
            'status'     => 'malformed',
            'name'       => '',
            'address'    => '',
            'request_id' => '',
            'checked_at' => $now,
            'error'      => $clean['error'],
        );
    }

    if (empty($options['fresh'])) {
        $kept = pg_vat_cache_get($clean['id'], $now);

        if ($kept !== null) {
            $kept['cached'] = true;
            return $kept;
        }
    }

    $request = pg_vat_check_request($clean, (string) ($options['requester'] ?? ''));
    $curl_options = array(
        'body'            => $request['body'],
        'headers'         => $request['headers'],
        'timeout'         => 10,
        'connect_timeout' => 5,
        'cap'             => 65536,
    );

    if (isset($options['http']) && is_callable($options['http'])) {
        $response = call_user_func($options['http'], 'POST', $request['url'], $curl_options);
    } else {
        $response = pg_curl_run('POST', $request['url'], $curl_options);
    }

    $result = $base + pg_vat_check_map(is_array($response) ? $response : array(), $now);

    pg_vat_cache_put($result);

    return $result;
}
