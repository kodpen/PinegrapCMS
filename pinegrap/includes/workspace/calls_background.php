<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the files behind a call's "Blur background" and "Remove
 * background": MediaPipe's tasks-vision runtime (its module, its WASM
 * loader and binary) and the Selfie Segmenter model, all Apache-2.0.
 *
 * They are not shipped: the Calls card's "Background effects" switch has
 * the server fetch them once, from the addresses below, into
 * data/call_background/. Each file must have the size and SHA-256 digest
 * written here; one that differs is refused and nothing is kept. Switching
 * off deletes them. The browser reads them only from this site
 * (workspace_call_background.php), which checks the digest again before it
 * answers, so a file changed on the disk is never handed to the page.
 * data/ is outside the file-integrity check; these digests are the check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The tasks-vision version the runtime files are pinned to. */
define('WS_CALL_BACKGROUND_RUNTIME', '1.2.2');

/** The manifest written after a complete fetch: the digests it fetched. */
define('WS_CALL_BACKGROUND_MANIFEST', 'pinned.json');

/**
 * The pinned files: the name served (?f=), the file on the disk, where it
 * is fetched, its size, SHA-256 and Content-Type. Pure.
 *
 * @return array[] name => array(file, url, size, sha256, type)
 */
function ws_call_background_files()
{
    $npm = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@' . WS_CALL_BACKGROUND_RUNTIME . '/';

    return array(
        'bundle' => array(
            'file'   => 'vision_bundle.mjs',
            'url'    => $npm . 'vision_bundle.mjs',
            'size'   => 156062,
            'sha256' => 'b65acb59b370cd6561afd9d04c29ed7a87e7ed6386922ac1b263af1f1ad06b7d',
            'type'   => 'text/javascript; charset=utf-8',
        ),
        'loader' => array(
            'file'   => 'vision_wasm_internal.js',
            'url'    => $npm . 'wasm/vision_wasm_internal.js',
            'size'   => 334128,
            'sha256' => 'f2b9c17131ea79716ec6bf07b117a087b2749a6f8a1e2f1f2e77a9382092db5b',
            'type'   => 'text/javascript; charset=utf-8',
        ),
        'wasm' => array(
            'file'   => 'vision_wasm_internal.wasm',
            'url'    => $npm . 'wasm/vision_wasm_internal.wasm',
            'size'   => 14603354,
            'sha256' => 'f5bd25147ac89d506074293ce2675969b10e827180db95a0d9689c2ed80764ab',
            'type'   => 'application/wasm',
        ),
        'model' => array(
            'file'   => 'selfie_segmenter.tflite',
            'url'    => 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_segmenter/float16/1/selfie_segmenter.tflite',
            'size'   => 249537,
            'sha256' => '191ac9529ae506ee0beefa6b2c945a172dab9d07d1e802a290a4e4038226658b',
            'type'   => 'application/octet-stream',
        ),
    );
}

/**
 * The folder the fetched files are kept in.
 *
 * @return string
 */
function ws_call_background_dir()
{
    return PG_FUNCTIONS_DIR . '/data/call_background';
}

/**
 * The manifest's text for a list of pinned files: their names and digests,
 * so an update that pins other digests reads the folder as stale. Pure.
 *
 * @param array $files ws_call_background_files()
 * @return string
 */
function ws_call_background_manifest($files)
{
    $out = array();

    foreach ($files as $name => $file) {
        $out[$name] = $file['sha256'];
    }

    return json_encode($out);
}

/**
 * Does a stored manifest name exactly the pinned digests. Pure.
 *
 * @param string $stored the manifest's text ('' when there is none)
 * @param array  $files  ws_call_background_files()
 * @return bool
 */
function ws_call_background_manifest_ok($stored, $files)
{
    $read = json_decode((string) $stored, true);

    return is_array($read) && ($read === json_decode(ws_call_background_manifest($files), true));
}

/**
 * Are the files there for the current pins: the manifest matches and each
 * file has its pinned size. The digests are read when a file is served.
 *
 * @return bool
 */
function ws_call_background_ready()
{
    $dir = ws_call_background_dir();
    $files = ws_call_background_files();
    $manifest = @file_get_contents($dir . '/' . WS_CALL_BACKGROUND_MANIFEST);

    if (($manifest === false) || !ws_call_background_manifest_ok($manifest, $files)) {
        return false;
    }

    foreach ($files as $file) {
        if (@filesize($dir . '/' . $file['file']) !== $file['size']) {
            return false;
        }
    }

    return true;
}

/**
 * Deletes the folder's files (the pinned ones, the manifest and any part
 * left by a broken fetch) and the folder.
 */
function ws_call_background_remove()
{
    $dir = ws_call_background_dir();

    if (!is_dir($dir)) {
        return;
    }

    foreach ((array) glob($dir . '/*') as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

/**
 * Fetches the pinned files once. A file whose size or digest differs from
 * its pin is refused; then nothing of the fetch is kept. Already there for
 * these pins: nothing is fetched.
 *
 * @return array ok (bool), error (string)
 */
function ws_call_background_fetch()
{
    if (ws_call_background_ready()) {
        return array('ok' => true, 'error' => '');
    }

    ws_call_background_remove();
    $dir = ws_call_background_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return array('ok' => false, 'error' => lang('The folder for the background files could not be made under data/.'));
    }

    if (!pg_dir_writable($dir)) {
        ws_call_background_remove();

        return array('ok' => false, 'error' => lang('The folder for the background files under data/ cannot be written to.'));
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    foreach (ws_call_background_files() as $file) {
        $part = $dir . '/' . $file['file'] . '.part';
        $sink = @fopen($part, 'wb');
        $answer = $sink ? pg_curl_run('GET', $file['url'], array('sink' => $sink, 'cap' => $file['size'] + 1, 'timeout' => 240)) : array('ok' => false);

        if ($sink) {
            fclose($sink);
        }

        $digest = is_file($part) ? hash_file('sha256', $part) : '';

        if (empty($answer['ok']) || (@filesize($part) !== $file['size']) || !hash_equals($file['sha256'], (string) $digest) || !@rename($part, $dir . '/' . $file['file'])) {
            ws_call_background_remove();
            log_activity('Workspace call background files were not fetched: ' . $file['file'] . (empty($answer['ok']) ? ' could not be downloaded.' : ' did not match its pinned digest.'));

            return array('ok' => false, 'error' => lang(array(
                'string' => 'The background files could not be fetched ({var:1}); nothing was kept.',
                'vars'   => $file['file'],
            )));
        }
    }

    if (@file_put_contents($dir . '/' . WS_CALL_BACKGROUND_MANIFEST, ws_call_background_manifest(ws_call_background_files())) === false) {
        ws_call_background_remove();

        return array('ok' => false, 'error' => lang('The folder for the background files under data/ cannot be written to.'));
    }

    log_activity('Workspace call background files were fetched (tasks-vision ' . WS_CALL_BACKGROUND_RUNTIME . ', Selfie Segmenter).');

    return array('ok' => true, 'error' => '');
}

/**
 * What the call window gets about the effects: the addresses of the four
 * files (each with its digest's start, so a new pin is a new address), or
 * null while the switch is off or the files are not there.
 *
 * @param array $settings ws_calls_settings()
 * @return array|null name => URL
 */
function ws_call_background_config($settings)
{
    if (empty($settings['background']) || !ws_call_background_ready()) {
        return null;
    }

    return ws_call_background_urls(ws_call_background_files(), PATH . SOFTWARE_DIRECTORY . '/workspace_call_background.php');
}

/**
 * The served addresses of the files. Pure.
 *
 * @param array  $files ws_call_background_files()
 * @param string $base  the serving script's path
 * @return array name => URL
 */
function ws_call_background_urls($files, $base)
{
    $out = array();

    foreach ($files as $name => $file) {
        $out[$name] = $base . '?f=' . rawurlencode($name) . '&v=' . substr($file['sha256'], 0, 12);
    }

    return $out;
}

/**
 * Answers one pinned file to the page's loader: only while the switch is on,
 * the files are there and this file's digest still matches its pin. Each
 * address carries the digest, so the browser may keep it for a year.
 *
 * @param string $name ?f=
 */
function ws_call_background_serve($name)
{
    $files = ws_call_background_files();
    $name = is_string($name) ? $name : '';
    $settings = ws_calls_ready() ? ws_calls_settings() : array();

    if (!isset($files[$name]) || empty($settings['background']) || !ws_call_background_ready()) {
        http_response_code(404);
        header('Cache-Control: no-store');
        exit;
    }

    $file = $files[$name];
    $path = ws_call_background_dir() . '/' . $file['file'];
    $etag = '"' . $file['sha256'] . '"';

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && (trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag)) {
        http_response_code(304);
        header('ETag: ' . $etag);
        exit;
    }

    if (!hash_equals($file['sha256'], (string) @hash_file('sha256', $path))) {
        log_activity('Workspace call background file ' . $file['file'] . ' no longer matches its pinned digest and was not served.');
        http_response_code(404);
        header('Cache-Control: no-store');
        exit;
    }

    header('Content-Type: ' . $file['type']);
    header('Content-Length: ' . $file['size']);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
