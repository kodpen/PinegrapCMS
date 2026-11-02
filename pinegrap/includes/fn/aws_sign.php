<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * AWS Signature Version 4: the canonical request, the string to sign, the
 * signing key, the Authorization header and the presigned (query) form.
 * Pure functions: no network, no clock (the caller passes the time), no
 * configuration. Shared by every service that speaks SigV4: the
 * S3-compatible object store (includes/fn/object_storage.php) and any later
 * AWS API.
 *
 * Paths are encoded once, segment by segment, and never normalised (no "."
 * or ".." folding, no slash merging): what S3 expects, and what the
 * "unnormalized" vectors of the AWS signing test suite check
 * (tests/fixtures/aws_sigv4, tests/object_storage_sign_test.php).
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
 * The SHA-256 of an empty body, the payload hash of a request without one.
 *
 * @return string
 */
function pg_aws_empty_hash()
{
    return 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
}


/**
 * RFC 3986 encoding as SigV4 wants it: every byte but A-Z a-z 0-9 - . _ ~
 * as %XX in upper-case hex.
 *
 * @param string $value raw (not yet encoded) text
 * @param bool $keep_slash true for a path, whose slashes stay
 * @return string
 */
function pg_aws_uri_encode($value, $keep_slash)
{
    $encoded = rawurlencode((string) $value);

    // PHP before 7.3 encoded the tilde, which RFC 3986 leaves alone.
    $encoded = str_replace('%7E', '~', $encoded);

    return $keep_slash ? str_replace('%2F', '/', $encoded) : $encoded;
}


/**
 * The canonical path: the raw path encoded once, slashes kept, never
 * normalised. An empty path is "/".
 *
 * @param string $path raw path, starting with "/"
 * @return string
 */
function pg_aws_canonical_path($path)
{
    $path = (string) $path;

    if ($path === '') {
        return '/';
    }

    return pg_aws_uri_encode($path, true);
}


/**
 * The canonical query string: every name and value encoded, sorted by
 * encoded name and then by encoded value, joined with "&". A name without a
 * value keeps its "=".
 *
 * @param array $pairs list of array(name, value) raw pairs, or name => value
 * @return string
 */
function pg_aws_canonical_query(array $pairs)
{
    $encoded = array();

    foreach (pg_aws_pairs($pairs) as $pair) {
        $encoded[] = array(pg_aws_uri_encode($pair[0], false), pg_aws_uri_encode($pair[1], false));
    }

    usort($encoded, function ($a, $b) {
        $by_name = strcmp($a[0], $b[0]);
        return ($by_name !== 0) ? $by_name : strcmp($a[1], $b[1]);
    });

    $parts = array();

    foreach ($encoded as $pair) {
        $parts[] = $pair[0] . '=' . $pair[1];
    }

    return implode('&', $parts);
}


/**
 * Name/value pairs from either a list of array(name, value) or a map
 * name => value (a value that is a list repeats the name).
 *
 * @param array $pairs
 * @return array list of array(string, string)
 */
function pg_aws_pairs(array $pairs)
{
    $out = array();

    foreach ($pairs as $name => $value) {
        if (is_int($name) && is_array($value) && (count($value) === 2) && isset($value[0])) {
            $out[] = array((string) $value[0], (string) $value[1]);
        } elseif (is_array($value)) {
            foreach ($value as $one) {
                $out[] = array((string) $name, (string) $one);
            }
        } else {
            $out[] = array((string) $name, (string) $value);
        }
    }

    return $out;
}


/**
 * The canonical header block and the signed header list. Names are
 * lower-cased and sorted; a value is trimmed and its runs of spaces made one;
 * a name given more than once keeps its values in order, joined with ",".
 *
 * @param array $headers list of array(name, value), or name => value
 * @return array array('lines' => "name:value\n"..., 'signed' => 'a;b;c')
 */
function pg_aws_canonical_headers(array $headers)
{
    $canonical = array();

    foreach (pg_aws_pairs($headers) as $pair) {
        $name = strtolower(trim($pair[0]));
        $value = trim(preg_replace('/\s+/', ' ', $pair[1]));

        $canonical[$name] = isset($canonical[$name]) ? $canonical[$name] . ',' . $value : $value;
    }

    ksort($canonical, SORT_STRING);

    $lines = '';

    foreach ($canonical as $name => $value) {
        $lines .= $name . ':' . $value . "\n";
    }

    return array('lines' => $lines, 'signed' => implode(';', array_keys($canonical)));
}


/**
 * The SigV4 canonical request.
 *
 * @param string $method
 * @param string $path raw path ("/bucket/key name")
 * @param array $query raw query pairs (pg_aws_pairs() forms)
 * @param array $headers headers to sign, host included
 * @param string $payload_hash hex SHA-256 of the body, or UNSIGNED-PAYLOAD
 * @return array array('request' => string, 'signed_headers' => string)
 */
function pg_aws_canonical_request($method, $path, array $query, array $headers, $payload_hash)
{
    $canonical = pg_aws_canonical_headers($headers);

    return array(
        'request' => strtoupper((string) $method) . "\n"
            . pg_aws_canonical_path($path) . "\n"
            . pg_aws_canonical_query($query) . "\n"
            . $canonical['lines'] . "\n"
            . $canonical['signed'] . "\n"
            . (string) $payload_hash,
        'signed_headers' => $canonical['signed'],
    );
}


/**
 * The credential scope: date/region/service/aws4_request.
 *
 * @param int $time Unix time of the request
 * @param string $region
 * @param string $service
 * @return string
 */
function pg_aws_scope($time, $region, $service)
{
    return gmdate('Ymd', (int) $time) . '/' . $region . '/' . $service . '/aws4_request';
}


/**
 * The time as SigV4 writes it (20150830T123600Z).
 *
 * @param int $time
 * @return string
 */
function pg_aws_amz_date($time)
{
    return gmdate('Ymd\THis\Z', (int) $time);
}


/**
 * The SigV4 string to sign of a canonical request.
 *
 * @param int $time
 * @param string $scope pg_aws_scope()
 * @param string $canonical_request the 'request' of pg_aws_canonical_request()
 * @return string
 */
function pg_aws_string_to_sign($time, $scope, $canonical_request)
{
    return "AWS4-HMAC-SHA256\n" . pg_aws_amz_date($time) . "\n" . $scope . "\n" . hash('sha256', (string) $canonical_request);
}


/**
 * The SigV4 signing key: the HMAC chain over date, region and service.
 *
 * @param string $secret the secret access key
 * @param int $time
 * @param string $region
 * @param string $service
 * @return string raw binary key
 */
function pg_aws_signing_key($secret, $time, $region, $service)
{
    $key = hash_hmac('sha256', gmdate('Ymd', (int) $time), 'AWS4' . $secret, true);
    $key = hash_hmac('sha256', (string) $region, $key, true);
    $key = hash_hmac('sha256', (string) $service, $key, true);

    return hash_hmac('sha256', 'aws4_request', $key, true);
}


/**
 * The signature of a string to sign.
 *
 * @param string $secret
 * @param int $time
 * @param string $region
 * @param string $service
 * @param string $string_to_sign
 * @return string lower-case hex
 */
function pg_aws_signature($secret, $time, $region, $service, $string_to_sign)
{
    return hash_hmac('sha256', (string) $string_to_sign, pg_aws_signing_key($secret, $time, $region, $service));
}


/**
 * The Authorization header value of a request signed in its headers.
 *
 * @param array $request method, path, query (pairs), headers (to sign, host
 *                       included; x-amz-date is added here), payload_hash
 * @param array $credentials key, secret
 * @param string $region
 * @param string $service
 * @param int $time
 * @return array array('authorization', 'amz_date', 'canonical_request',
 *               'string_to_sign', 'signature')
 */
function pg_aws_authorization(array $request, array $credentials, $region, $service, $time)
{
    $headers = pg_aws_pairs(isset($request['headers']) ? $request['headers'] : array());
    $headers[] = array('x-amz-date', pg_aws_amz_date($time));

    $canonical = pg_aws_canonical_request(
        $request['method'],
        isset($request['path']) ? $request['path'] : '/',
        isset($request['query']) ? $request['query'] : array(),
        $headers,
        isset($request['payload_hash']) ? $request['payload_hash'] : pg_aws_empty_hash()
    );

    $scope = pg_aws_scope($time, $region, $service);
    $string_to_sign = pg_aws_string_to_sign($time, $scope, $canonical['request']);
    $signature = pg_aws_signature($credentials['secret'], $time, $region, $service, $string_to_sign);

    return array(
        'authorization'     => 'AWS4-HMAC-SHA256 Credential=' . $credentials['key'] . '/' . $scope
            . ', SignedHeaders=' . $canonical['signed_headers']
            . ', Signature=' . $signature,
        'amz_date'          => pg_aws_amz_date($time),
        'canonical_request' => $canonical['request'],
        'string_to_sign'    => $string_to_sign,
        'signature'         => $signature,
    );
}


/**
 * A presigned request: the query string that carries the signature, so the
 * address alone grants the request until it expires.
 *
 * @param array $request method, path, query (pairs, e.g. response-content-type),
 *                       headers (to sign, host included), payload_hash
 *                       (S3: UNSIGNED-PAYLOAD)
 * @param array $credentials key, secret
 * @param string $region
 * @param string $service
 * @param int $time
 * @param int $expires seconds, 1 to 604800
 * @return array array('query' => the full query string with
 *               X-Amz-Signature last, 'canonical_request', 'string_to_sign',
 *               'signature')
 */
function pg_aws_presign(array $request, array $credentials, $region, $service, $time, $expires)
{
    $headers = isset($request['headers']) ? $request['headers'] : array();
    $signed = pg_aws_canonical_headers($headers);

    $query = pg_aws_pairs(isset($request['query']) ? $request['query'] : array());
    $query[] = array('X-Amz-Algorithm', 'AWS4-HMAC-SHA256');
    $query[] = array('X-Amz-Credential', $credentials['key'] . '/' . pg_aws_scope($time, $region, $service));
    $query[] = array('X-Amz-Date', pg_aws_amz_date($time));
    $query[] = array('X-Amz-Expires', (string) min(604800, max(1, (int) $expires)));
    $query[] = array('X-Amz-SignedHeaders', $signed['signed']);

    $canonical = pg_aws_canonical_request(
        $request['method'],
        isset($request['path']) ? $request['path'] : '/',
        $query,
        $headers,
        isset($request['payload_hash']) ? $request['payload_hash'] : 'UNSIGNED-PAYLOAD'
    );

    $string_to_sign = pg_aws_string_to_sign($time, pg_aws_scope($time, $region, $service), $canonical['request']);
    $signature = pg_aws_signature($credentials['secret'], $time, $region, $service, $string_to_sign);

    return array(
        'query'             => pg_aws_canonical_query($query) . '&X-Amz-Signature=' . $signature,
        'canonical_request' => $canonical['request'],
        'string_to_sign'    => $string_to_sign,
        'signature'         => $signature,
    );
}
