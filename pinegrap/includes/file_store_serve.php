<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How get_file.php answers for a file whose bytes are in the object store
 * (files.storage 's3', includes/fn/file_storage.php): after every access
 * check, a redirect to a presigned address that lives five minutes, or the
 * bytes from the disk when a local file or a cached copy is there.
 *
 * get_file.php never loads functions.php (router.php dispatches it that way
 * on purpose), so this file reads the store's settings with its own query
 * and signs with includes/fn/aws_sign.php and the address builders of
 * includes/fn/object_storage.php, which call nothing beyond each other and
 * PHP. The secret is unsealed with decode_ssl_keys() from
 * includes/authentication.php, which both sides load, and the
 * Content-Disposition comes from includes/http_range.php, as get_file.php's
 * own answer does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/object_storage.php');
require_once(PG_FUNCTIONS_DIR . '/includes/http_range.php');


/**
 * The store's settings as pg_os_settings() gives them, read with the open
 * connection get_file.php holds.
 *
 * @param mysqli $connection
 * @return array pg_os_normalize() plus enabled; empty values when there is no row
 */
function pg_file_serve_store_config($connection)
{
    $row = null;
    $result = @mysqli_query($connection, "SELECT client_id, secret, options, enabled FROM connect_apps WHERE provider = 'object_storage'");

    if ($result) {
        $row = mysqli_fetch_assoc($result);
    }

    if (!is_array($row)) {
        return pg_os_normalize(array()) + array('enabled' => false);
    }

    return pg_file_serve_store_config_from_row($row, pg_file_serve_unseal((string) $row['secret']));
}


/**
 * The secret access key from its sealed form ("<data>:<iv>", as
 * pg_connect_unseal() reads it).
 *
 * @param string $stored
 * @return string '' when it cannot be opened
 */
function pg_file_serve_unseal($stored)
{
    if (($stored === '') || !defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '')
        || !function_exists('openssl_decrypt') || !function_exists('decode_ssl_keys')) {
        return '';
    }

    $parts = explode(':', $stored, 2);

    if (count($parts) !== 2) {
        return '';
    }

    $data = json_decode(decode_ssl_keys($parts[0], $parts[1]), true);

    return (is_array($data) && isset($data['client_secret'])) ? (string) $data['client_secret'] : '';
}


/**
 * The settings from a connect_apps row and its unsealed secret. Pure.
 *
 * @param array $row client_id, options (JSON), enabled
 * @param string $secret
 * @return array
 */
function pg_file_serve_store_config_from_row(array $row, $secret)
{
    $options = json_decode(isset($row['options']) ? (string) $row['options'] : '', true);
    $options = is_array($options) ? $options : array();

    return pg_os_normalize(array(
        'endpoint'   => isset($options['endpoint']) ? $options['endpoint'] : '',
        'region'     => isset($options['region']) ? $options['region'] : '',
        'bucket'     => isset($options['bucket']) ? $options['bucket'] : '',
        'prefix'     => isset($options['prefix']) ? $options['prefix'] : '',
        'path_style' => !empty($options['path_style']),
        'key_id'     => isset($row['client_id']) ? $row['client_id'] : '',
        'secret'     => $secret,
    )) + array('enabled' => isset($row['enabled']) && ((int) $row['enabled'] === 1));
}


/**
 * The cached copy pg_file_local_copy() keeps for a stored file, when it is
 * there with the row's size.
 *
 * @param int $id
 * @param string $type files.type (as pg_file_cache_name() names the copy)
 * @param int $size files.size
 * @return string path, or ''
 */
function pg_file_serve_cached_copy($id, $type, $size)
{
    if (!defined('FILE_DIRECTORY_PATH')) {
        return '';
    }

    $type = preg_replace('/[^a-z0-9]/', '', strtolower((string) $type));
    $path = dirname(FILE_DIRECTORY_PATH) . '/cache/objects/' . (int) $id . (($type !== '') ? '.' . $type : '');

    clearstatcache(true, $path);

    if (!is_file($path) || ((int) @filesize($path) !== (int) $size)) {
        return '';
    }

    // Used now, as pg_file_cache_used() marks it: the access time only, the
    // modification time is what a derivative's signature reads.
    $mtime = @filemtime($path);

    if ($mtime !== false) {
        @touch($path, (int) $mtime, time());
    }

    return $path;
}


/**
 * How a stored file is answered. A local file wins: a replace door wrote
 * new bytes under the name, and the general job sets the row back to local
 * (pg_file_storage_reconcile_run()). A cached copy is served as a local
 * file is, so ranges, derivatives and the download name behave as before.
 * Otherwise the presigned address, which the store answers with ranges too.
 * Pure.
 *
 * @param bool $local_exists
 * @param string $copy pg_file_serve_cached_copy()
 * @param string $presigned pg_os_presign() ('' when the settings cannot sign)
 * @return string 'local', 'copy', 'redirect' or 'unavailable'
 */
function pg_file_serve_plan($local_exists, $copy, $presigned)
{
    if ($local_exists) {
        return 'local';
    }

    if ($copy !== '') {
        return 'copy';
    }

    return ($presigned !== '') ? 'redirect' : 'unavailable';
}


/**
 * The Content-Disposition the store is asked to answer with: the one
 * get_file.php sends from the disk (pg_http_content_disposition(): inline
 * for a type the browser shows, attachment otherwise, the name in quotes and
 * in filename*), except that a document type the browser would run (HTML,
 * SVG, XML) is always an attachment. Pure.
 *
 * @param string $name
 * @param bool $inline
 * @return string
 */
function pg_file_serve_disposition($name, $inline)
{
    $name = preg_replace('/["\\\\\r\n]/', '', (string) $name);

    // HTML, XHTML, SVG and XML run as documents when opened by their own
    // address. A local answer drops them into a sandbox (Content-Security-
    // Policy in get_file.php); the store cannot be asked for that header, so
    // they download instead of running on the store's host. An <img> or CSS
    // reference ignores the disposition and keeps showing an SVG.
    if ($inline && preg_match('/\.(html?|xhtml|xht|svgz?|xml|xsl)$/i', $name)) {
        $inline = false;
    }

    return pg_http_content_disposition($name, $inline);
}
