<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - Google Cloud Translation (Basic, v2).
 *
 * The site owner's own API key, stored encrypted in the config row. Texts go
 * as HTML so the inline markup of a heading survives; the data tokens the
 * renderer fills in are wrapped in a notranslate span and unwrapped on the
 * way back. A request carries at most a hundred texts and about twenty-five
 * thousand characters, under the limits the service documents.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_TR_GOOGLE_ENDPOINT', 'https://translation.googleapis.com/language/translate/v2');
define('PG_TR_GOOGLE_BATCH_ITEMS', 100);
define('PG_TR_GOOGLE_BATCH_CHARS', 25000);

/**
 * The language code Google expects for one of ours.
 */
function pg_tr_google_code($code)
{
    $map = array('no' => 'no', 'zh-CN' => 'zh-CN', 'zh-TW' => 'zh-TW', 'pt-BR' => 'pt', 'tl' => 'tl', 'he' => 'he');

    if (isset($map[$code])) {
        return $map[$code];
    }

    return (string) $code;
}

/**
 * Wraps the fixed tokens of a text, and the glossary terms that stay as
 * written, so Google leaves them alone.
 *
 * @param string $text
 * @param array  $keep_terms as pg_tr_keep_terms() lists them
 */
function pg_tr_google_protect($text, $keep_terms = array())
{
    $patterns = array_merge(array(
        '\^\^(?i:[a-z0-9_]+)\^\^(?:%%[^%]*%%)?',
        '\{(?i:[a-z_]+)(?::[0-9]+)?(?:\|(?i:[a-z]))?\}',
    ), pg_tr_keep_patterns($keep_terms));

    return preg_replace_callback('/' . implode('|', $patterns) . '/u', function ($m) {
        return '<span class="notranslate">' . $m[0] . '</span>';
    }, (string) $text);
}

/**
 * Takes the protection off an answer, and resolves what the service escaped
 * inside the token.
 */
function pg_tr_google_unprotect($text)
{
    return preg_replace_callback('~<span class="notranslate">(.*?)</span>~is', function ($m) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }, (string) $text);
}

/**
 * Translates a set of texts. Texts are sent in batches; a batch that fails
 * leaves its texts out of the answer and sets $error.
 *
 * @param array  $texts  id => text (HTML for inline texts, plain otherwise)
 * @param string $source language code
 * @param string $target language code
 * @param string $error  out: the last error, '' when every batch went through
 * @param array  $keep_terms glossary terms to leave as written
 * @return array id => translated text
 */
function pg_tr_google_translate($texts, $source, $target, &$error, $keep_terms = array())
{
    $error = '';
    $key = pg_tr_google_key();
    $out = array();

    if ($key === '') {
        $error = lang('No Google Cloud API key is stored. Enter one in Settings > Languages and Translation.');
        return $out;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $batch = array();
    $chars = 0;

    $flush = function () use (&$batch, &$chars, &$out, &$error, $key, $source, $target, $keep_terms) {
        if (!$batch) {
            return;
        }

        $result = pg_tr_google_request($batch, $source, $target, $key, $keep_terms);

        if ($result['ok']) {
            foreach ($result['translations'] as $id => $text) {
                $out[$id] = $text;
            }
        } else {
            $error = $result['error'];
        }

        $batch = array();
        $chars = 0;
    };

    foreach ($texts as $id => $text) {
        $text = (string) $text;
        $length = mb_strlen($text);

        if ($batch && (($chars + $length > PG_TR_GOOGLE_BATCH_CHARS) || (count($batch) >= PG_TR_GOOGLE_BATCH_ITEMS))) {
            $flush();
        }

        $batch[$id] = $text;
        $chars += $length;
    }

    $flush();

    return $out;
}

/**
 * One request to the service.
 *
 * @return array('ok' => bool, 'translations' => array id => text, 'error' => string)
 */
function pg_tr_google_request($batch, $source, $target, $key, $keep_terms = array())
{
    $ids = array_keys($batch);
    $fields = array();

    foreach ($batch as $text) {
        $fields[] = 'q=' . rawurlencode(pg_tr_google_protect($text, $keep_terms));
    }

    $fields[] = 'source=' . rawurlencode(pg_tr_google_code($source));
    $fields[] = 'target=' . rawurlencode(pg_tr_google_code($target));
    $fields[] = 'format=html';

    $response = api_http_request('POST', PG_TR_GOOGLE_ENDPOINT . '?key=' . rawurlencode($key), array(
        'body'     => implode('&', $fields),
        'headers'  => array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'),
        'timeout'  => 40,
        'max_body' => 8 * 1024 * 1024,
        'agent'    => 'translate',
    ));

    if (!$response['ok']) {
        $message = $response['error'];
        $body = json_decode((string) $response['body'], true);

        if (is_array($body) && isset($body['error']['message'])) {
            $message = 'Google: ' . $body['error']['message'];
        } elseif ($response['status'] > 0) {
            $message = 'Google: HTTP ' . $response['status'] . ($message !== '' ? ' - ' . $message : '');
        }

        return array('ok' => false, 'translations' => array(), 'error' => $message);
    }

    $body = json_decode((string) $response['body'], true);

    if (!is_array($body) || !isset($body['data']['translations']) || !is_array($body['data']['translations'])) {
        return array('ok' => false, 'translations' => array(), 'error' => 'Google: the answer could not be read.');
    }

    $translations = array();

    foreach ($body['data']['translations'] as $i => $item) {
        if (!isset($ids[$i]) || !isset($item['translatedText'])) {
            continue;
        }

        $translations[$ids[$i]] = pg_tr_google_unprotect((string) $item['translatedText']);
    }

    return array('ok' => true, 'translations' => $translations, 'error' => '');
}
