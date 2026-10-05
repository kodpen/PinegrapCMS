<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - Pinegrap AI (the site's own model connection,
 * ai.pinegrap.com), and the one chat call every caller outside the Workspace
 * goes through.
 *
 * The connection, its licence, the model and the hold after a failed call
 * belong to the Workspace module (includes/workspace/ai.php); this file loads
 * that module and asks it, rather than carrying a second copy of the licence
 * logic. A site without the Workspace switched on has no Pinegrap AI, and the
 * engine says so.
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
 * Texts per model call. Small on purpose: the model answers in a web
 * request's time (ws_ai_call_timeout()), and a short list is what a small
 * local model answers reliably and quickly. A site with a fast model may
 * raise it with PG_TR_AI_BATCH_ITEMS in data/config.php.
 */
if (!defined('PG_TR_AI_BATCH_ITEMS')) {
    define('PG_TR_AI_BATCH_ITEMS', 3);
}

/** Characters of source text per model call. */
if (!defined('PG_TR_AI_BATCH_CHARS')) {
    define('PG_TR_AI_BATCH_CHARS', 2400);
}

/** Tokens the answer may run to: the translations of one batch, and no more. */
define('PG_TR_AI_MAX_TOKENS', 2000);

/**
 * Loads the Workspace module's files, which hold the Pinegrap AI connection.
 * Safe to call on a site without the module: the functions are then there
 * and answer "not ready".
 */
function pg_ai_boot()
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;

    if (!function_exists('ws_ai_http') && is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    }
}

/**
 * Whether Pinegrap AI can be asked right now, and why not when it cannot.
 * A hold after a failed call is temporary (retry true): a job waits it out
 * rather than failing.
 *
 * @return array('ready' => bool, 'reason' => string, 'retry' => bool)
 */
function pg_ai_state()
{
    pg_ai_boot();

    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !function_exists('ws_ai_ready')) {
        return array('ready' => false, 'reason' => lang('Pinegrap AI is set up in the Workspace module, which this site has not switched on.'), 'retry' => false);
    }

    $missing = ws_ai_missing();

    if ($missing) {
        return array('ready' => false, 'reason' => implode(' ', $missing), 'retry' => false);
    }

    $config = ws_ai_config(true);

    if ($config['hold_until'] > time()) {
        return array('ready' => false, 'reason' => (($config['error'] !== '') ? $config['error'] . ' ' : '') . lang(array('string' => 'Pinegrap AI is paused until {var:1}.', 'vars' => date('H:i', $config['hold_until']))), 'retry' => true);
    }

    return array('ready' => true, 'reason' => '', 'retry' => false);
}

/**
 * One chat completion from Pinegrap AI: the answer's words, or why there are
 * none. A failed call puts the connection on hold for a minute the way the
 * Workspace does, so a queue of jobs does not knock on a door that is shut.
 *
 * @param array $messages OpenAI-style messages (role, content)
 * @param array $options  temperature, max_tokens, timeout
 * @return array('ok' => bool, 'text' => string, 'error' => string)
 */
function pg_ai_chat($messages, $options = array())
{
    $state = pg_ai_state();

    if (!$state['ready']) {
        return array('ok' => false, 'text' => '', 'error' => $state['reason']);
    }

    $model = ws_ai_model();

    if ($model === '') {
        return array('ok' => false, 'text' => '', 'error' => lang('Pinegrap AI lists no model to answer with.'));
    }

    $timeout = isset($options['timeout']) ? (int) $options['timeout'] : (function_exists('ws_ai_call_timeout') ? ws_ai_call_timeout() : 55);

    $response = ws_ai_http('POST', '/chat/completions', array(
        'model'            => $model,
        'messages'         => $messages,
        'temperature'      => isset($options['temperature']) ? (float) $options['temperature'] : 0.2,
        'reasoning_effort' => 'low',
        'max_tokens'       => isset($options['max_tokens']) ? (int) $options['max_tokens'] : 4000,
        'stream'           => false,
    ), max(20, $timeout));

    // Reads the licence verdict, clears or sets the hold, forgets a model
    // that is no longer served - the same bookkeeping the Workspace does.
    $problem = ws_ai_response_problem($response);

    if ($problem !== '') {
        $error = ($problem === 'license') ? ws_ai_license_problem() : ws_ai_http_error($response);

        return array('ok' => false, 'text' => '', 'error' => ($error !== '') ? $error : lang('Pinegrap AI did not answer.'));
    }

    $message = $response['json']['choices'][0]['message'];
    $content = isset($message['content']) ? $message['content'] : '';

    // Some servers answer with content parts rather than one string.
    if (is_array($content)) {
        $joined = '';

        foreach ($content as $part) {
            if (is_array($part) && isset($part['text'])) {
                $joined .= (string) $part['text'];
            } elseif (is_string($part)) {
                $joined .= $part;
            }
        }

        $content = $joined;
    }

    return array('ok' => true, 'text' => ws_ai_clean((string) $content), 'error' => '');
}

/**
 * Translates a set of strings with Pinegrap AI, in small batches, each with
 * its context. A batch whose answer cannot be read is retried once without
 * the context; what still fails is left out and $error says why.
 *
 * @param array  $strings  translation_strings rows
 * @param string $source
 * @param string $target
 * @param string $error    out
 * @return array string_id => translated text
 */
function pg_tr_ai_translate($strings, $source, $target, &$error)
{
    $error = '';
    $out = array();
    $instructions = pg_tr_ai_instructions($source, $target);
    $batch = array();
    $chars = 0;

    $flush = function () use (&$batch, &$chars, &$out, &$error, $instructions, $target) {
        if (!$batch) {
            return true;
        }

        $items = pg_tr_package_items($batch, $target);
        $result = pg_tr_ai_batch($instructions, $items);

        if (!$result['ok'] && ($result['error'] !== '')) {
            $error = $result['error'];
            $batch = array();
            $chars = 0;
            return false;
        }

        foreach ($result['answers'] as $id => $text) {
            $out[(int) $id] = $text;
        }

        $batch = array();
        $chars = 0;

        return true;
    };

    foreach ($strings as $string) {
        $length = mb_strlen((string) $string['source_text']);

        if ($batch && (($chars + $length > PG_TR_AI_BATCH_CHARS) || (count($batch) >= PG_TR_AI_BATCH_ITEMS))) {
            if (!$flush()) {
                return $out;
            }
        }

        $batch[] = $string;
        $chars += $length;
    }

    $flush();

    return $out;
}

/**
 * One batch: the call, the answer read as JSON, a second try with the bare
 * texts when the first answer was not an object.
 *
 * @return array('ok' => bool, 'answers' => array id => text, 'error' => string)
 */
function pg_tr_ai_batch($instructions, $items)
{
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $ids = array();

    foreach ($items as $item) {
        $ids[(string) $item['string_id']] = true;
    }

    $payload = array();

    foreach ($items as $item) {
        $entry = array('id' => (string) $item['string_id'], 'text' => $item['text'], 'format' => $item['format']);

        if ($item['where']['owner'] !== '') {
            $entry['where'] = array_filter($item['where'], function ($v) { return $v !== ''; });
        }

        if ($item['previous']) {
            $entry['previous'] = $item['previous'];
        }

        if ($item['glossary']) {
            $entry['glossary'] = $item['glossary'];
        }

        $payload[] = $entry;
    }

    $messages = array(
        array('role' => 'system', 'content' => $instructions),
        array('role' => 'user', 'content' => json_encode($payload, $flags)),
    );

    $reply = pg_ai_chat($messages, array('max_tokens' => PG_TR_AI_MAX_TOKENS));

    if (!$reply['ok']) {
        return array('ok' => false, 'answers' => array(), 'error' => $reply['error']);
    }

    $answers = pg_tr_ai_parse_answer($reply['text']);

    if ($answers === null) {
        // Once more, with the texts alone: context is what a small model
        // most often trips over.
        $bare = array();

        foreach ($items as $item) {
            $bare[] = array('id' => (string) $item['string_id'], 'text' => $item['text'], 'format' => $item['format']);
        }

        $messages[1]['content'] = json_encode($bare, $flags);
        $messages[] = array('role' => 'user', 'content' => 'Answer with the JSON object only.');

        $reply = pg_ai_chat($messages, array('max_tokens' => PG_TR_AI_MAX_TOKENS, 'temperature' => 0.1));

        if (!$reply['ok']) {
            return array('ok' => false, 'answers' => array(), 'error' => $reply['error']);
        }

        $answers = pg_tr_ai_parse_answer($reply['text']);
    }

    if ($answers === null) {
        return array('ok' => true, 'answers' => array(), 'error' => '');
    }

    $kept = array();

    foreach ($answers as $id => $text) {
        if (isset($ids[(string) $id])) {
            $kept[(string) $id] = $text;
        }
    }

    return array('ok' => true, 'answers' => $kept, 'error' => '');
}
