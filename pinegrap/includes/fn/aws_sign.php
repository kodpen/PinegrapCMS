<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * AWS Signature Version 4: signs a request to an AWS API (Amazon SES) or an
 * S3-compatible store with an access key, without an SDK.
 *
 * The steps of the specification, each its own pure function so the tests
 * check them against the AWS SigV4 test suite (tests/fixtures/aws_sigv4/):
 * the canonical request (method, path, sorted query, sorted lower-case
 * headers, the payload's SHA-256), the string to sign (algorithm, time,
 * scope, the canonical request's hash), the signing key (HMAC chain from
 * the secret over date, region, service) and the signature. A request is
 * signed by headers (pg_aws_authorization()) or by its query string, a
 * link that works until it expires (pg_aws_presign()).
 *
 * Paths and query values are given decoded and encoded here (RFC 3986,
 * each byte of UTF-8). Dot segments are removed unless 'normalize' is off:
 * S3 keys are taken as they are.
 *
 * https://docs.aws.amazon.com/IAM/latest/UserGuide/reference_sigv-create-signed-request.html
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
 * A decoded path as the canonical request names it.
 *
 * @param string $path
 * @param bool   $normalize remove '.' and '..' segments and repeated slashes
 * @return string
 */
function pg_aws_canonical_path($path, $normalize = true)
{
    $path = (string) $path;
    $path = ($path === '') ? '/' : $path;
    $segments = explode('/', $path);

    if ($normalize) {
        $kept = array();

        foreach ($segments as $index => $segment) {
            if (($segment === '.') || (($segment === '') && ($index > 0) && ($index < count($segments) - 1))) {
                continue;
            }

            if ($segment === '..') {
                if (count($kept) > 1) {
                    array_pop($kept);
                }

                continue;
            }

            $kept[] = $segment;
        }

        // A path that ended in a dot segment still names a directory.
        $last = end($segments);

        if ((($last === '.') || ($last === '..')) && (end($kept) !== '')) {
            $kept[] = '';
        }

        $segments = $kept;
    }

    $encoded = implode('/', array_map('rawurlencode', $segments));

    return (substr($encoded, 0, 1) === '/') ? $encoded : '/' . $encoded;
}

/**
 * A query string as the canonical request names it: every name and value
 * decoded then encoded again, sorted by name, then by value.
 *
 * @param string $query without the '?'
 * @return string
 */
function pg_aws_canonical_query($query)
{
    $pairs = array();

    foreach (explode('&', (string) $query) as $piece) {
        if ($piece === '') {
            continue;
        }

        $parts = explode('=', $piece, 2);
        $pairs[] = array(rawurlencode(rawurldecode($parts[0])), rawurlencode(rawurldecode(isset($parts[1]) ? $parts[1] : '')));
    }

    usort($pairs, function ($a, $b) {
        $by_name = strcmp($a[0], $b[0]);

        return ($by_name !== 0) ? $by_name : strcmp($a[1], $b[1]);
    });

    $out = array();

    foreach ($pairs as $pair) {
        $out[] = $pair[0] . '=' . $pair[1];
    }

    return implode('&', $out);
}

/**
 * The canonical headers and the signed header list.
 *
 * @param array $headers list of array(name, value); a name may repeat
 * @return array array(block with a line per header, 'a;b;c')
 */
function pg_aws_canonical_headers($headers)
{
    $merged = array();

    foreach ($headers as $header) {
        $name = strtolower(trim((string) $header[0]));
        $value = trim(preg_replace('/\s+/', ' ', (string) $header[1]));
        $merged[$name] = isset($merged[$name]) ? $merged[$name] . ',' . $value : $value;
    }

    ksort($merged, SORT_STRING);

    $block = '';

    foreach ($merged as $name => $value) {
        $block .= $name . ':' . $value . "\n";
    }

    return array($block, implode(';', array_keys($merged)));
}

/**
 * The canonical request.
 *
 * @param string $method
 * @param string $path         decoded
 * @param string $query        as sent, without the '?'
 * @param array  $headers      list of array(name, value)
 * @param string $payload_hash hex SHA-256 of the body, or UNSIGNED-PAYLOAD
 * @param bool   $normalize
 * @return string
 */
function pg_aws_canonical_request($method, $path, $query, $headers, $payload_hash, $normalize = true)
{
    list($block, $signed) = pg_aws_canonical_headers($headers);

    return strtoupper((string) $method) . "\n"
        . pg_aws_canonical_path($path, $normalize) . "\n"
        . pg_aws_canonical_query($query) . "\n"
        . $block . "\n"
        . $signed . "\n"
        . $payload_hash;
}

/**
 * The credential scope: date/region/service/aws4_request.
 *
 * @param string $amz_date 20150830T123600Z
 * @param string $region
 * @param string $service
 * @return string
 */
function pg_aws_scope($amz_date, $region, $service)
{
    return substr((string) $amz_date, 0, 8) . '/' . $region . '/' . $service . '/aws4_request';
}

/**
 * The string to sign.
 *
 * @param string $amz_date
 * @param string $scope pg_aws_scope()
 * @param string $canonical_request
 * @return string
 */
function pg_aws_string_to_sign($amz_date, $scope, $canonical_request)
{
    return "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . hash('sha256', $canonical_request);
}

/**
 * The signing key: HMAC-SHA256 of date, region, service and aws4_request in
 * turn, starting from "AWS4" and the secret.
 *
 * @param string $secret
 * @param string $date 20150830
 * @param string $region
 * @param string $service
 * @return string raw bytes
 */
function pg_aws_signing_key($secret, $date, $region, $service)
{
    $key = 'AWS4' . $secret;

    foreach (array($date, $region, $service, 'aws4_request') as $part) {
        $key = hash_hmac('sha256', $part, $key, true);
    }

    return $key;
}

/**
 * The parts of a URL the signature reads.
 *
 * @param string $url
 * @return array host (with a port that is not the default), path (decoded),
 *               query
 */
function pg_aws_url_parts($url)
{
    $parts = parse_url((string) $url);
    $parts = is_array($parts) ? $parts : array();
    $host = isset($parts['host']) ? strtolower($parts['host']) : '';

    if (isset($parts['port']) && !in_array((int) $parts['port'], array(80, 443), true)) {
        $host .= ':' . (int) $parts['port'];
    }

    return array(
        'host'  => $host,
        'path'  => isset($parts['path']) ? rawurldecode($parts['path']) : '/',
        'query' => isset($parts['query']) ? $parts['query'] : '',
    );
}

/**
 * Signs a request with headers.
 *
 * @param array  $key      id, secret, optional token (a session token)
 * @param string $region
 * @param string $service
 * @param string $method
 * @param string $url
 * @param array  $headers  list of array(name, value) sent with the request;
 *                         Host is added from the URL when missing
 * @param string $body
 * @param string $amz_date 20150830T123600Z, now when null
 * @param array  $options  sign_body (default true: X-Amz-Content-Sha256 is
 *                         sent and signed), normalize (default true)
 * @return array the header lines to add ("Name: value"): X-Amz-Date,
 *               X-Amz-Content-Sha256, X-Amz-Security-Token, Authorization
 */
function pg_aws_authorization($key, $region, $service, $method, $url, $headers, $body, $amz_date = null, $options = array())
{
    $amz_date = ($amz_date !== null) ? (string) $amz_date : gmdate('Ymd\THis\Z');
    $url_parts = pg_aws_url_parts($url);
    $payload_hash = hash('sha256', (string) $body);
    $added = array(array('X-Amz-Date', $amz_date));

    if (!isset($options['sign_body']) || $options['sign_body']) {
        $added[] = array('X-Amz-Content-Sha256', $payload_hash);
    }

    if (!empty($key['token'])) {
        $added[] = array('X-Amz-Security-Token', (string) $key['token']);
    }

    $has_host = false;

    foreach ($headers as $header) {
        $has_host = $has_host || (strtolower($header[0]) === 'host');
    }

    $signed = array_merge($has_host ? array() : array(array('Host', $url_parts['host'])), $headers, $added);
    $canonical = pg_aws_canonical_request($method, $url_parts['path'], $url_parts['query'], $signed, $payload_hash, !isset($options['normalize']) || $options['normalize']);
    $scope = pg_aws_scope($amz_date, $region, $service);
    $signature = hash_hmac('sha256', pg_aws_string_to_sign($amz_date, $scope, $canonical), pg_aws_signing_key($key['secret'], substr($amz_date, 0, 8), $region, $service));
    list(, $signed_names) = pg_aws_canonical_headers($signed);

    $lines = array();

    foreach ($added as $header) {
        $lines[] = $header[0] . ': ' . $header[1];
    }

    $lines[] = 'Authorization: AWS4-HMAC-SHA256 Credential=' . $key['id'] . '/' . $scope . ', SignedHeaders=' . $signed_names . ', Signature=' . $signature;

    return $lines;
}

/**
 * A link that makes a request on its own until it expires (query string
 * signing).
 *
 * @param array  $key          id, secret, optional token
 * @param string $region
 * @param string $service
 * @param string $method
 * @param string $url
 * @param int    $expires      seconds, 1 to 604800
 * @param array  $headers      list of array(name, value) the request will
 *                             carry; Host is added from the URL
 * @param string $payload_hash UNSIGNED-PAYLOAD (S3) or the body's SHA-256
 * @param string $amz_date     now when null
 * @param bool   $normalize
 * @return string the URL with the signature in its query
 */
function pg_aws_presign($key, $region, $service, $method, $url, $expires, $headers = array(), $payload_hash = 'UNSIGNED-PAYLOAD', $amz_date = null, $normalize = true)
{
    $amz_date = ($amz_date !== null) ? (string) $amz_date : gmdate('Ymd\THis\Z');
    $url_parts = pg_aws_url_parts($url);
    $scope = pg_aws_scope($amz_date, $region, $service);
    $signed = array_merge(array(array('Host', $url_parts['host'])), $headers);
    list(, $signed_names) = pg_aws_canonical_headers($signed);

    $query = array(
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => $key['id'] . '/' . $scope,
        'X-Amz-Date'          => $amz_date,
        'X-Amz-Expires'       => (string) max(1, min(604800, (int) $expires)),
        'X-Amz-SignedHeaders' => $signed_names,
    );

    if (!empty($key['token'])) {
        $query['X-Amz-Security-Token'] = (string) $key['token'];
    }

    $own = '';

    foreach ($query as $name => $value) {
        $own .= '&' . $name . '=' . rawurlencode($value);
    }

    $full_query = ltrim($url_parts['query'] . $own, '&');
    $canonical = pg_aws_canonical_request($method, $url_parts['path'], $full_query, $signed, $payload_hash, $normalize);
    $signature = hash_hmac('sha256', pg_aws_string_to_sign($amz_date, $scope, $canonical), pg_aws_signing_key($key['secret'], substr($amz_date, 0, 8), $region, $service));

    $base = strtok((string) $url, '?');

    return $base . '?' . $full_query . '&X-Amz-Signature=' . $signature;
}
