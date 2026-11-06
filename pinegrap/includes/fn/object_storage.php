<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The S3-compatible object store client: put, get, head, delete and list one
 * bucket, multipart upload for large files, presigned addresses, and the
 * settings of Site Settings > Updates and maintenance > Storage.
 *
 * Any service that speaks the S3 API works by its endpoint address; nothing
 * here names a vendor. Requests are signed with SigV4
 * (includes/fn/aws_sign.php, paths not normalised, as S3 wants) and sent
 * through pg_curl_run(): https, the
 * site's proxy and pg_curl_tls() on every call. An endpoint on plain http
 * (a store on the same host or LAN) is allowed for that request only.
 *
 * The settings are one connect_apps row, 'object_storage': the access key
 * id as client_id, the secret access key sealed with the site's encryption
 * key, endpoint, region, bucket, key prefix and path style as options. No
 * schema change, and the secret is never drawn back on the card.
 *
 * Every call answers an array with 'ok'; a failure carries 'code' (the
 * service's error code, 'http_<status>', 'network', 'not_configured', ...)
 * and 'message' (a sentence for the operator). Nothing throws.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/aws_sign.php');


/**
 * The connect_apps key the settings live under.
 *
 * @return string
 */
function pg_os_app_key()
{
    return 'object_storage';
}


/**
 * Files above this size are sent as a multipart upload.
 *
 * @return int bytes
 */
function pg_os_multipart_threshold()
{
    return 64 * 1024 * 1024;
}


/**
 * The size of one part of a multipart upload. S3 wants at least 5 MiB for
 * every part but the last and at most 10,000 parts; 16 MiB parts reach
 * 160 GB and keep one part's memory small.
 *
 * @return int bytes
 */
function pg_os_part_size()
{
    return 16 * 1024 * 1024;
}


/**
 * Settings with every key present and typed. The region defaults to
 * us-east-1, which self-hosted stores accept; the prefix loses its outer
 * slashes and repeated ones.
 *
 * @param array $values
 * @return array endpoint, region, bucket, prefix, path_style, key_id, secret
 */
function pg_os_normalize(array $values)
{
    $text = function ($key) use ($values) {
        return (isset($values[$key]) && is_scalar($values[$key])) ? trim((string) $values[$key]) : '';
    };

    $endpoint = rtrim($text('endpoint'), '/');

    if (($endpoint !== '') && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint)) {
        $endpoint = 'https://' . $endpoint;
    }

    $region = $text('region');

    return array(
        'endpoint'   => $endpoint,
        'region'     => ($region !== '') ? $region : 'us-east-1',
        'bucket'     => $text('bucket'),
        'prefix'     => trim(preg_replace('#/+#', '/', $text('prefix')), '/'),
        'path_style' => !empty($values['path_style']),
        'key_id'     => $text('key_id'),
        'secret'     => $text('secret'),
    );
}


/**
 * The stored settings, the secret unsealed.
 *
 * @return array pg_os_normalize() plus enabled and secret_stored
 */
function pg_os_settings()
{
    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app(pg_os_app_key()) : null;

    if (!is_array($app)) {
        return pg_os_normalize(array()) + array('enabled' => false, 'secret_stored' => false);
    }

    $options = $app['options'];

    return pg_os_normalize(array(
        'endpoint'   => isset($options['endpoint']) ? $options['endpoint'] : '',
        'region'     => isset($options['region']) ? $options['region'] : '',
        'bucket'     => isset($options['bucket']) ? $options['bucket'] : '',
        'prefix'     => isset($options['prefix']) ? $options['prefix'] : '',
        'path_style' => !empty($options['path_style']),
        'key_id'     => $app['client_id'],
        'secret'     => $app['client_secret'],
    )) + array('enabled' => $app['enabled'], 'secret_stored' => $app['secret_stored']);
}


/**
 * Whether the settings are complete enough to send a request.
 *
 * @param array $config pg_os_settings()
 * @return bool
 */
function pg_os_ready(array $config)
{
    return !empty($config['enabled']) && ($config['endpoint'] !== '') && ($config['bucket'] !== '')
        && ($config['key_id'] !== '') && ($config['secret'] !== '');
}


/**
 * The store's name for a person: the bucket and the endpoint's host, as
 * Disk Usage and System Status name it. Pure.
 *
 * @param array $config pg_os_normalize()
 * @return string '' when no bucket is set
 */
function pg_os_store_name(array $config)
{
    $bucket = isset($config['bucket']) ? (string) $config['bucket'] : '';
    $host = isset($config['endpoint']) ? (string) parse_url((string) $config['endpoint'], PHP_URL_HOST) : '';

    if ($bucket === '') {
        return '';
    }

    return ($host !== '') ? $bucket . ' (' . $host . ')' : $bucket;
}


/**
 * What is wrong with settings about to be saved, by field name.
 *
 * @param array $config pg_os_normalize()
 * @return array field => sentence; empty when they can be saved
 */
function pg_os_config_errors(array $config)
{
    $errors = array();

    $parts = ($config['endpoint'] !== '') ? @parse_url($config['endpoint']) : false;

    if ($config['endpoint'] === '') {
        $errors['object_storage_endpoint'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('Endpoint address')));
    } elseif (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)
        || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])) {
        $errors['object_storage_endpoint'] = lang('The storage endpoint address is not valid.');
    }

    if (!preg_match('/^[a-z0-9-]{1,32}$/i', $config['region'])) {
        $errors['object_storage_region'] = lang('A region is a name such as us-east-1 or auto.');
    }

    if ($config['bucket'] === '') {
        $errors['object_storage_bucket'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('Bucket name')));
    } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,61}[A-Za-z0-9]$/', $config['bucket'])) {
        $errors['object_storage_bucket'] = lang('A bucket name has 3 to 63 letters, digits, dots, dashes or underscores.');
    } elseif (!$config['path_style'] && !preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $config['bucket'])) {
        // The bucket becomes part of the host name.
        $errors['object_storage_bucket'] = lang('This bucket name cannot be part of a host name. Switch on path-style addresses.');
    }

    if (($config['prefix'] !== '')
        && ((strlen($config['prefix']) > 200) || preg_match('/[\x00-\x1f\x7f\\\\]/', $config['prefix']) || in_array('..', explode('/', $config['prefix']), true) || in_array('.', explode('/', $config['prefix']), true))) {
        $errors['object_storage_prefix'] = lang('A folder in the bucket is up to 200 characters, without control characters, backslashes or . and .. levels.');
    }

    if ($config['key_id'] === '') {
        $errors['object_storage_key_id'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('Access Key')));
    }

    if ($config['secret'] === '') {
        $errors['object_storage_secret'] = lang(array('string' => '{var:1} is required.', 'vars' => lang('Secret Key')));
    }

    return $errors;
}


/**
 * Saves the settings. A secret of '' keeps the stored one.
 *
 * @param array $config pg_os_normalize()
 * @param bool $enabled
 * @param int $user_id
 * @return bool false when the site cannot seal the secret
 */
function pg_os_settings_save(array $config, $enabled, $user_id)
{
    if (!function_exists('pg_connect_app_save')) {
        return false;
    }

    return pg_connect_app_save(pg_os_app_key(), $config['key_id'], $config['secret'], array(
        'endpoint'   => $config['endpoint'],
        'region'     => $config['region'],
        'bucket'     => $config['bucket'],
        'prefix'     => $config['prefix'],
        'path_style' => (bool) $config['path_style'],
    ), (bool) $enabled, (int) $user_id);
}


/**
 * The full object key: the configured prefix, then the key.
 *
 * @param array $config
 * @param string $key a key below the prefix ("files/12/report.pdf")
 * @return string
 */
function pg_os_object_key(array $config, $key)
{
    $key = ltrim((string) $key, '/');

    return ($config['prefix'] !== '') ? $config['prefix'] . '/' . $key : $key;
}


/**
 * Where a request goes: the address, the Host header and the raw path to
 * sign. Path style puts the bucket in the path; virtual-hosted style in the
 * host name. An empty object key addresses the bucket itself (a list).
 *
 * @param array $config
 * @param string $object_key the full key (pg_os_object_key())
 * @param array $query raw query pairs
 * @return array url, host, path, scheme; empty when the endpoint cannot be read
 */
function pg_os_target(array $config, $object_key, array $query = array())
{
    $parts = @parse_url($config['endpoint']);

    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])
        || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return array();
    }

    $scheme = strtolower($parts['scheme']);

    // A default port is left out, as the signer names the host.
    $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    $host = strtolower($parts['host']) . ((($port > 0) && !in_array($port, array(80, 443), true)) ? ':' . $port : '');
    $base = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
    $object_key = (string) $object_key;

    if ($config['path_style']) {
        $path = $base . '/' . $config['bucket'] . (($object_key !== '') ? '/' . $object_key : '');
    } else {
        $host = $config['bucket'] . '.' . $host;
        $path = $base . '/' . $object_key;
    }

    $url = $scheme . '://' . $host . pg_os_uri_encode($path, true);

    if ($query) {
        $url .= '?' . pg_os_query($query);
    }

    return array('url' => $url, 'host' => $host, 'path' => $path, 'scheme' => $scheme);
}


/**
 * RFC 3986 encoding: every byte but A-Z a-z 0-9 - . _ ~ as %XX.
 *
 * @param string $value
 * @param bool $keep_slash true for a path
 * @return string
 */
function pg_os_uri_encode($value, $keep_slash)
{
    // PHP before 7.3 encoded the tilde, which RFC 3986 leaves alone.
    $encoded = str_replace('%7E', '~', rawurlencode((string) $value));

    return $keep_slash ? str_replace('%2F', '/', $encoded) : $encoded;
}


/**
 * A query string from name => value pairs, each encoded, sorted by name.
 *
 * @param array $query
 * @return string
 */
function pg_os_query(array $query)
{
    ksort($query, SORT_STRING);

    $parts = array();

    foreach ($query as $name => $value) {
        $parts[] = pg_os_uri_encode($name, false) . '=' . pg_os_uri_encode($value, false);
    }

    return implode('&', $parts);
}


/**
 * A failure answer.
 *
 * @param string $code
 * @param string $message
 * @return array
 */
function pg_os_fail($code, $message)
{
    return array('ok' => false, 'code' => (string) $code, 'message' => (string) $message);
}


/**
 * The text of the first XML element with that name, entities decoded.
 *
 * @param string $xml
 * @param string $tag
 * @return string '' when absent
 */
function pg_os_xml_value($xml, $tag)
{
    if (!preg_match('#<' . preg_quote($tag, '#') . '>(.*?)</' . preg_quote($tag, '#') . '>#s', (string) $xml, $match)) {
        return '';
    }

    return html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
}


/**
 * The failure answer of a request that did not succeed: a network error, or
 * a status with the service's XML error document.
 *
 * @param array $result pg_curl_run()
 * @return array pg_os_fail() plus status
 */
function pg_os_error(array $result)
{
    if ((int) $result['status'] === 0) {
        $error = trim((string) $result['error']);

        if (!empty($result['errno'])) {
            $error = trim('cURL ' . (int) $result['errno'] . ': ' . $error . (function_exists('pg_curl_tls_hint') ? pg_curl_tls_hint($result['errno']) : ''));
        }

        return pg_os_fail('network', lang(array('string' => 'The storage service cannot be reached: {var:1}', 'vars' => array($error)))) + array('status' => 0);
    }

    $status = (int) $result['status'];
    $code = trim(pg_os_xml_value($result['body'], 'Code'));
    $message = trim(pg_os_xml_value($result['body'], 'Message'));
    $reason = trim($code . ((($code !== '') && ($message !== '')) ? ': ' : '') . $message);

    if (($code === '') && ($status === 404)) {
        $code = 'not_found';
    }

    return pg_os_fail(($code !== '') ? $code : 'http_' . $status, trim(lang(array(
        'string' => 'The storage service answered HTTP {var:1}. {var:2}',
        'vars'   => array($status, $reason),
    )))) + array('status' => $status);
}


/**
 * One signed request.
 *
 * @param array $config pg_os_settings()
 * @param string $method
 * @param string $object_key the full key, '' for the bucket
 * @param array $options query (name => value), headers (name => value, signed and
 *                       sent), body (string), upload (array('file', 'size',
 *                       'hash')), sink, cap, response_headers, timeout
 * @return array pg_curl_run(), or pg_os_fail() when it could not be sent
 */
function pg_os_request(array $config, $method, $object_key, array $options = array())
{
    if (!pg_os_ready($config)) {
        return pg_os_fail('not_configured', lang('The object storage is not set up.'));
    }

    $query = isset($options['query']) ? $options['query'] : array();
    $target = pg_os_target($config, $object_key, $query);

    if (!$target) {
        return pg_os_fail('endpoint', lang('The storage endpoint address is not valid.'));
    }

    if (!function_exists('pg_aws_authorization')) {
        return pg_os_fail('not_configured', lang('The object storage is not set up.'));
    }

    $body = isset($options['body']) ? (string) $options['body'] : '';

    // A file streamed from disk is signed with its digest, never read into
    // memory to be hashed.
    $sign = array('normalize' => false);

    if (isset($options['upload'])) {
        $sign['payload_hash'] = $options['upload']['hash'];
    }

    $headers = array(array('Host', $target['host']));

    foreach ((isset($options['headers']) ? $options['headers'] : array()) as $name => $value) {
        $headers[] = array(strtolower($name), (string) $value);
    }

    $lines = array();

    foreach ($headers as $header) {
        $lines[] = $header[0] . ': ' . $header[1];
    }

    $lines = array_merge($lines, pg_aws_authorization(
        array('id' => $config['key_id'], 'secret' => $config['secret']),
        $config['region'], 's3', $method, $target['url'], $headers, $body, null, $sign));

    $run = array(
        'headers'    => $lines,
        'timeout'    => isset($options['timeout']) ? (int) $options['timeout'] : 60,
        'allow_http' => ($target['scheme'] === 'http'),
    );

    foreach (array('body', 'sink', 'cap', 'response_headers') as $name) {
        if (isset($options[$name])) {
            $run[$name] = $options[$name];
        }
    }

    $handle = null;

    if (isset($options['upload'])) {
        $handle = @fopen($options['upload']['file'], 'rb');

        if (!$handle) {
            return pg_os_fail('read_failed', lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(pg_basename($options['upload']['file'])))));
        }

        $run['upload'] = array('handle' => $handle, 'size' => (int) $options['upload']['size']);
    }

    $result = pg_curl_run($method, $target['url'], $run);

    if ($handle) {
        fclose($handle);
    }

    // Counted for the operations alert storage_unreachable.
    if (function_exists('pg_os_health_unreachable') && pg_os_health_unreachable($result)) {
        pg_os_health_record($result);
    }

    return $result;
}


/**
 * Stores a file, or a string, under a key. A file above
 * pg_os_multipart_threshold() goes up in parts.
 *
 * @param array $config pg_os_settings()
 * @param string $key below the prefix
 * @param string $file path of the file to send ('' with options data)
 * @param array $options content_type, data (a string sent instead of a file)
 * @return array ok, size, etag; or pg_os_fail()
 */
function pg_os_put(array $config, $key, $file, array $options = array())
{
    // Always named: a body sent from memory would otherwise go out as
    // libcurl's form type and be stored with it.
    $type = !empty($options['content_type']) ? (string) $options['content_type'] : 'application/octet-stream';
    $headers = array('content-type' => $type);
    $object_key = pg_os_object_key($config, $key);

    if (isset($options['data'])) {
        $size = strlen((string) $options['data']);
        $result = pg_os_request($config, 'PUT', $object_key, array(
            'headers'          => $headers,
            'body'             => (string) $options['data'],
            'response_headers' => true,
            'timeout'          => 120,
        ));
    } else {
        $size = @filesize($file);

        if (($size === false) || !is_readable($file)) {
            return pg_os_fail('read_failed', lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(pg_basename($file)))));
        }

        if ($size > pg_os_multipart_threshold()) {
            return pg_os_multipart_put($config, $object_key, $file, $size, $headers);
        }

        $hash = hash_file('sha256', $file);

        if ($hash === false) {
            return pg_os_fail('read_failed', lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(pg_basename($file)))));
        }

        $result = pg_os_request($config, 'PUT', $object_key, array(
            'headers'          => $headers,
            'upload'           => array('file' => $file, 'size' => $size, 'hash' => $hash),
            'response_headers' => true,
            'timeout'          => pg_os_timeout($size),
        ));
    }

    if (empty($result['ok'])) {
        return isset($result['status']) ? pg_os_error($result) : $result;
    }

    $head = pg_os_head_parse(isset($result['headers']) ? $result['headers'] : '');

    return array('ok' => true, 'size' => (int) $size, 'etag' => $head['etag']);
}


/**
 * Seconds a transfer of that size may take: two minutes, or one second per
 * 50 KB for a large file.
 *
 * @param int $size bytes
 * @return int
 */
function pg_os_timeout($size)
{
    return (int) max(120, ceil($size / 51200));
}


/**
 * Reads an object into a file, or into memory.
 *
 * @param array $config pg_os_settings()
 * @param string $key below the prefix
 * @param string|null $target path to write; null answers the bytes as 'body'
 * @param array $options cap (bytes; default 1 MB in memory, none to a file),
 *                       timeout
 * @return array ok, size, and body when read into memory; or pg_os_fail().
 *               A file left half-written is deleted.
 */
function pg_os_get(array $config, $key, $target = null, array $options = array())
{
    $object_key = pg_os_object_key($config, $key);
    $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 600;

    if ($target === null) {
        $result = pg_os_request($config, 'GET', $object_key, array(
            'cap'     => isset($options['cap']) ? (int) $options['cap'] : 1048576,
            'timeout' => $timeout,
        ));

        if (empty($result['ok'])) {
            return isset($result['status']) ? pg_os_get_error($result) : $result;
        }

        return array('ok' => true, 'size' => strlen($result['body']), 'body' => $result['body']);
    }

    $sink = @fopen($target, 'wb');

    if (!$sink) {
        return pg_os_fail('write_failed', lang(array('string' => 'The file {var:1} cannot be written.', 'vars' => array(pg_basename($target)))));
    }

    $request = array('sink' => $sink, 'timeout' => $timeout);

    if (!empty($options['cap'])) {
        $request['cap'] = (int) $options['cap'];
    }

    $result = pg_os_request($config, 'GET', $object_key, $request);

    fclose($sink);

    if (empty($result['ok'])) {
        @unlink($target);

        // The error document of a refusal went to the file with the body.
        return isset($result['status']) ? pg_os_get_error($result) : $result;
    }

    return array('ok' => true, 'size' => (int) $result['received']);
}


/**
 * The failure of a download, which may have stopped on the cap or a full
 * disk rather than on the service.
 *
 * @param array $result pg_curl_run() with cap or sink
 * @return array pg_os_fail()
 */
function pg_os_get_error(array $result)
{
    if (!empty($result['capped'])) {
        return pg_os_fail('too_large', lang('The stored file is larger than allowed here.'));
    }

    if (!empty($result['write_failed'])) {
        return pg_os_fail('write_failed', lang('The downloaded file could not be written to the disk.'));
    }

    return pg_os_error($result);
}


/**
 * An object's size and tag without its bytes.
 *
 * @param array $config
 * @param string $key below the prefix
 * @return array ok, size, etag, content_type, modified; or pg_os_fail()
 *               (code 'not_found' when there is no such object)
 */
function pg_os_head(array $config, $key)
{
    $result = pg_os_request($config, 'HEAD', pg_os_object_key($config, $key), array('response_headers' => true, 'timeout' => 30));

    if (empty($result['ok'])) {
        return isset($result['status']) ? pg_os_error($result) : $result;
    }

    return array('ok' => true) + pg_os_head_parse($result['headers']);
}


/**
 * Size, tag, type and time from a raw answer header block. When a redirect
 * or 100 Continue left several blocks, the last one counts.
 *
 * @param string $headers
 * @return array size (-1 unknown), etag (quotes removed), content_type, modified (Unix time, 0 unknown)
 */
function pg_os_head_parse($headers)
{
    $blocks = preg_split("/\r?\n\r?\n/", trim((string) $headers));
    $block = (string) end($blocks);

    $values = array();

    foreach (preg_split("/\r?\n/", $block) as $line) {
        $pair = explode(':', $line, 2);

        if (count($pair) === 2) {
            $values[strtolower(trim($pair[0]))] = trim($pair[1]);
        }
    }

    $modified = isset($values['last-modified']) ? strtotime($values['last-modified']) : false;

    return array(
        'size'         => isset($values['content-length']) ? (int) $values['content-length'] : -1,
        'etag'         => isset($values['etag']) ? trim($values['etag'], '"') : '',
        'content_type' => isset($values['content-type']) ? $values['content-type'] : '',
        'modified'     => ($modified !== false) ? (int) $modified : 0,
    );
}


/**
 * Deletes an object. One that is not there counts as deleted.
 *
 * @param array $config
 * @param string $key below the prefix
 * @return array ok; or pg_os_fail()
 */
function pg_os_delete(array $config, $key)
{
    $result = pg_os_request($config, 'DELETE', pg_os_object_key($config, $key), array('timeout' => 30));

    if (!empty($result['ok']) || (isset($result['status']) && ((int) $result['status'] === 404))) {
        return array('ok' => true);
    }

    return isset($result['status']) ? pg_os_error($result) : $result;
}


/**
 * The objects whose keys start with a prefix (below the configured one),
 * page after page.
 *
 * @param array $config
 * @param string $prefix below the configured prefix ('' for everything)
 * @param array $options limit (objects, default 10000); start_after (a key
 *                       below the configured prefix: only keys after it, in
 *                       the store's byte order)
 * @return array ok, items (key below the configured prefix, size, modified,
 *               etag), truncated (more than limit); or pg_os_fail()
 */
function pg_os_list(array $config, $prefix = '', array $options = array())
{
    $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 10000;
    $after = isset($options['start_after']) ? (string) $options['start_after'] : '';
    $full = pg_os_object_key($config, $prefix);

    // The prefix a listed key carries that callers do not see.
    $strip = ($config['prefix'] !== '') ? $config['prefix'] . '/' : '';

    $items = array();
    $token = '';

    do {
        $query = pg_os_list_query($config, $full, min(1000, $limit - count($items)), $token, $after);

        $result = pg_os_request($config, 'GET', '', array('query' => $query, 'timeout' => 60, 'cap' => 16777216));

        if (empty($result['ok'])) {
            return isset($result['status']) ? pg_os_get_error($result) : $result;
        }

        $page = pg_os_list_parse($result['body'], $strip);
        $items = array_merge($items, $page['items']);
        $token = $page['truncated'] ? $page['next'] : '';

    } while (($token !== '') && (count($items) < $limit));

    return array('ok' => true, 'items' => array_slice($items, 0, $limit), 'truncated' => ($token !== ''));
}


/**
 * The query of one ListObjectsV2 page. The first page starts after
 * $start_after when one is given; a later page follows its continuation
 * token, which already carries where the listing stands.
 *
 * @param array $config
 * @param string $full the full key prefix (pg_os_object_key()), '' for none
 * @param int $max keys on this page
 * @param string $token continuation token, '' on the first page
 * @param string $start_after a key below the configured prefix, '' for none
 * @return array raw query pairs
 */
function pg_os_list_query(array $config, $full, $max, $token, $start_after)
{
    $query = array('list-type' => '2', 'max-keys' => (string) (int) $max);

    if ($full !== '') {
        $query['prefix'] = $full;
    }

    if ($token !== '') {
        $query['continuation-token'] = $token;
    } elseif ($start_after !== '') {
        $query['start-after'] = pg_os_object_key($config, $start_after);
    }

    return $query;
}


/**
 * One ListObjectsV2 answer.
 *
 * @param string $xml
 * @param string $strip the leading part of every key to remove
 * @return array items, truncated, next (the continuation token)
 */
function pg_os_list_parse($xml, $strip)
{
    $items = array();

    if (preg_match_all('#<Contents>(.*?)</Contents>#s', (string) $xml, $matches)) {
        foreach ($matches[1] as $entry) {
            $key = pg_os_xml_value($entry, 'Key');

            if (($strip !== '') && (strpos($key, $strip) === 0)) {
                $key = (string) substr($key, strlen($strip));
            }

            $modified = strtotime(pg_os_xml_value($entry, 'LastModified'));

            $items[] = array(
                'key'      => $key,
                'size'     => (int) pg_os_xml_value($entry, 'Size'),
                'modified' => ($modified !== false) ? (int) $modified : 0,
                'etag'     => trim(pg_os_xml_value($entry, 'ETag'), '"'),
            );
        }
    }

    return array(
        'items'     => $items,
        'truncated' => (strtolower(pg_os_xml_value($xml, 'IsTruncated')) === 'true'),
        'next'      => pg_os_xml_value($xml, 'NextContinuationToken'),
    );
}


/**
 * The parts of a multipart upload: offset and length of each.
 *
 * @param int $size
 * @param int $part
 * @return array list of array(offset, length), numbered from 1
 */
function pg_os_part_ranges($size, $part)
{
    $ranges = array();
    $number = 1;

    for ($offset = 0; $offset < $size; $offset += $part) {
        $ranges[$number++] = array($offset, (int) min($part, $size - $offset));
    }

    return $ranges;
}


/**
 * The body that completes a multipart upload.
 *
 * @param array $etags part number => ETag
 * @return string
 */
function pg_os_multipart_complete_xml(array $etags)
{
    ksort($etags);

    $xml = '<CompleteMultipartUpload>';

    foreach ($etags as $number => $etag) {
        $xml .= '<Part><PartNumber>' . (int) $number . '</PartNumber><ETag>"'
            . htmlspecialchars(trim($etag, '"'), ENT_QUOTES | ENT_XML1, 'UTF-8') . '"</ETag></Part>';
    }

    return $xml . '</CompleteMultipartUpload>';
}


/**
 * Sends a large file in parts: start, each part (one retry), complete. On
 * any failure the upload is aborted, so no part is left billed in the
 * bucket.
 *
 * @param array $config
 * @param string $object_key the full key
 * @param string $file
 * @param int $size
 * @param array $headers content-type when known
 * @return array ok, size, etag; or pg_os_fail()
 */
function pg_os_multipart_put(array $config, $object_key, $file, $size, array $headers)
{
    $start = pg_os_request($config, 'POST', $object_key, array('query' => array('uploads' => ''), 'headers' => $headers, 'timeout' => 30));

    if (empty($start['ok'])) {
        return isset($start['status']) ? pg_os_error($start) : $start;
    }

    $upload_id = pg_os_xml_value($start['body'], 'UploadId');

    if ($upload_id === '') {
        return pg_os_fail('bad_answer', lang('The storage service did not start the upload.'));
    }

    $abort = function ($failure) use ($config, $object_key, $upload_id) {
        pg_os_request($config, 'DELETE', $object_key, array('query' => array('uploadId' => $upload_id), 'timeout' => 30));
        return $failure;
    };

    $handle = @fopen($file, 'rb');

    if (!$handle) {
        return $abort(pg_os_fail('read_failed', lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(pg_basename($file))))));
    }

    $etags = array();

    foreach (pg_os_part_ranges($size, pg_os_part_size()) as $number => $range) {
        $data = (fseek($handle, $range[0]) === 0) ? fread($handle, $range[1]) : false;

        if (($data === false) || (strlen($data) !== $range[1])) {
            fclose($handle);
            return $abort(pg_os_fail('read_failed', lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(pg_basename($file))))));
        }

        $part = array();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $part = pg_os_request($config, 'PUT', $object_key, array(
                'query'            => array('partNumber' => (string) $number, 'uploadId' => $upload_id),
                'headers'          => array('content-type' => 'application/octet-stream'),
                'body'             => $data,
                'response_headers' => true,
                'timeout'          => pg_os_timeout($range[1]),
            ));

            // A refusal (4xx) is not retried; a dropped connection or a
            // service error (5xx) is, once.
            if (!empty($part['ok']) || (isset($part['status']) && ($part['status'] >= 400) && ($part['status'] < 500))) {
                break;
            }
        }

        unset($data);

        if (empty($part['ok'])) {
            fclose($handle);
            return $abort(isset($part['status']) ? pg_os_error($part) : $part);
        }

        $etags[$number] = pg_os_head_parse($part['headers'])['etag'];
    }

    fclose($handle);

    $done = pg_os_request($config, 'POST', $object_key, array(
        'query'   => array('uploadId' => $upload_id),
        'headers' => array('content-type' => 'application/xml'),
        'body'    => pg_os_multipart_complete_xml($etags),
        'timeout' => 300,
    ));

    // S3 can answer 200 and put the error in the body of a completion.
    if (empty($done['ok']) || (strpos((string) $done['body'], '<Error>') !== false)) {
        if (!empty($done['ok'])) {
            $done['status'] = 500;
        }

        return $abort(isset($done['status']) ? pg_os_error($done) : $done);
    }

    return array('ok' => true, 'size' => (int) $size, 'etag' => trim(pg_os_xml_value($done['body'], 'ETag'), '"'));
}


/**
 * A presigned address that lets whoever holds it read one object until it
 * expires. Mint it only after the reader's access was checked, and never
 * write it into a page or a mail.
 *
 * @param array $config
 * @param string $key below the prefix
 * @param int $expires seconds
 * @param array $overrides content_type, disposition: the answer's headers
 * @param string|null $amz_date the signing time (20130524T000000Z; now when null)
 * @return string the address, '' when the settings are incomplete
 */
function pg_os_presign(array $config, $key, $expires = 300, array $overrides = array(), $amz_date = null)
{
    if (!pg_os_ready($config) || !function_exists('pg_aws_presign')) {
        return '';
    }

    $query = array();

    if (!empty($overrides['content_type'])) {
        $query['response-content-type'] = (string) $overrides['content_type'];
    }

    if (!empty($overrides['disposition'])) {
        $query['response-content-disposition'] = (string) $overrides['disposition'];
    }

    $target = pg_os_target($config, pg_os_object_key($config, $key), $query);

    if (!$target) {
        return '';
    }

    return pg_aws_presign(array('id' => $config['key_id'], 'secret' => $config['secret']), $config['region'], 's3',
        'GET', $target['url'], $expires, array(), 'UNSIGNED-PAYLOAD', $amz_date, false);
}


/**
 * The settings test: write a small object, read it back, compare, delete it.
 *
 * @param array $config pg_os_settings() (enabled or not)
 * @return array ok, step ('put', 'get', 'compare', 'delete'), code, message
 */
function pg_os_test(array $config)
{
    $config['enabled'] = true;

    $key = '.pinegrap-test-' . substr(md5(uniqid('', true) . mt_rand()), 0, 12) . '.txt';
    $data = 'Pinegrap storage test ' . gmdate('Y-m-d H:i:s') . " UTC\n";

    $put = pg_os_put($config, $key, '', array('data' => $data, 'content_type' => 'text/plain'));

    if (!$put['ok']) {
        return $put + array('step' => 'put');
    }

    $get = pg_os_get($config, $key, null, array('cap' => 4096, 'timeout' => 30));
    $delete = pg_os_delete($config, $key);

    if (!$get['ok']) {
        return $get + array('step' => 'get');
    }

    if ($get['body'] !== $data) {
        return pg_os_fail('mismatch', lang('The test file read back from the storage is not the one written.')) + array('step' => 'compare');
    }

    if (!$delete['ok']) {
        return $delete + array('step' => 'delete');
    }

    return array('ok' => true, 'step' => 'delete', 'code' => '', 'message' => '');
}
