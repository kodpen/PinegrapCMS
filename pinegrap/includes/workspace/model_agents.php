<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - model assistants: agents of kind 'model' in the registry
 * (ws_agents), each an OpenAI-compatible chat API (OpenAI, Gemini, Mistral,
 * DeepSeek, OpenRouter, Azure OpenAI, or a custom address such as Ollama)
 * with its own handle, sealed key and model. The site answers their
 * requests (ws_ai_requests.agent = 'model:<id>') with Pinegrap AI's loop,
 * ws_ai_step(): the same briefing, tools, proposals and approvals, only the
 * address, the key, the model and the application it writes through are the
 * assistant's. The agent card (agents_settings.php) keeps its row. While one runs, ws_model_running() holds it and the few
 * places of ai.php that reach the gateway or its config read it from there;
 * the loop's failure sentences name it (ws_model_loop_sentence()), its own
 * row in a channel says whether it is asked there
 * (ws_model_channel_allowed()) and the activity log's line of its answer
 * names it (ws_model_log_answered()).
 *
 * For a model agent the door is the site itself, so no knock goes out;
 * door_url holds the chat API's base address where the provider has no
 * fixed one (Azure OpenAI, a custom server).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest a model agent's requests wait after a failed call, in seconds. */
define('WS_MODEL_HOLD_MAX', 3600);

/**
 * The chat APIs a model assistant can use. Each: name, base (the address
 * before /chat/completions; '' when the person gives it), key (required),
 * header (bearer, or api-key for Azure, which also takes the bearer), http
 * (a plain http address allowed: only a custom server on the local network),
 * tokens (max_completion_tokens where the API wants it instead of
 * max_tokens), terms (what the provider does with what is sent, rechecked
 * 2026-11-08) and terms_url. Pure.
 *
 * @return array[] by provider id
 */
function ws_model_providers()
{
    return array(
        'openai' => array(
            'name' => 'OpenAI', 'base' => 'https://api.openai.com/v1', 'key' => true, 'header' => 'bearer', 'http' => false,
            'tokens' => 'max_completion_tokens', 'model_hint' => 'gpt-4.1-mini',
            'terms' => 'OpenAI does not train on what is sent through its API unless you opt in, and keeps it up to 30 days for abuse monitoring.',
            'terms_url' => 'https://openai.com/enterprise-privacy/',
        ),
        'gemini' => array(
            'name' => 'Google Gemini', 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'key' => true, 'header' => 'bearer', 'http' => false,
            'tokens' => 'max_tokens', 'model_hint' => 'gemini-2.5-flash',
            'terms' => 'On the free tier Google may use what is sent to improve its products and people may read it; with billing turned on it does not.',
            'terms_url' => 'https://ai.google.dev/gemini-api/terms',
        ),
        'mistral' => array(
            'name' => 'Mistral', 'base' => 'https://api.mistral.ai/v1', 'key' => true, 'header' => 'bearer', 'http' => false,
            'tokens' => 'max_tokens', 'model_hint' => 'mistral-medium-latest',
            'terms' => 'On the free Experiment plan Mistral may use what is sent to train its models; on a paid plan it does not unless you allow it.',
            'terms_url' => 'https://mistral.ai/terms',
        ),
        'deepseek' => array(
            'name' => 'DeepSeek', 'base' => 'https://api.deepseek.com/v1', 'key' => true, 'header' => 'bearer', 'http' => false,
            'tokens' => 'max_tokens', 'model_hint' => 'deepseek-chat',
            'terms' => 'DeepSeek keeps and processes what is sent on servers in the People\'s Republic of China and may use it to improve its services.',
            'terms_url' => 'https://platform.deepseek.com/downloads/DeepSeek%20Open%20Platform%20Terms%20of%20Service.html',
        ),
        'openrouter' => array(
            'name' => 'OpenRouter', 'base' => 'https://openrouter.ai/api/v1', 'key' => true, 'header' => 'bearer', 'http' => false,
            'tokens' => 'max_tokens', 'model_hint' => 'openai/gpt-4.1-mini',
            'terms' => 'OpenRouter keeps no prompts unless you opt in, but passes them to the model\'s provider, whose own policy applies; its privacy settings can leave out providers that train on them.',
            'terms_url' => 'https://openrouter.ai/docs/features/privacy-and-logging',
        ),
        'azure' => array(
            'name' => 'Azure OpenAI', 'base' => '', 'key' => true, 'header' => 'api-key', 'http' => false,
            'tokens' => 'max_completion_tokens', 'model_hint' => 'your deployment name',
            'terms' => 'Azure OpenAI does not use what is sent to train models; it is processed in the region of your resource.',
            'terms_url' => 'https://learn.microsoft.com/legal/cognitive-services/openai/data-privacy',
        ),
        'custom' => array(
            'name' => 'Custom address', 'base' => '', 'key' => false, 'header' => 'bearer', 'http' => true,
            'tokens' => 'max_tokens', 'model_hint' => 'llama3.1',
            'terms' => 'What is sent goes to the address given here: its own terms apply, the site knows nothing more of them.',
            'terms_url' => '',
        ),
    );
}

/**
 * The sentence a person reads before a channel's words go to the
 * assistant: which provider receives them and what it does with them.
 * In English, translated where it is drawn. Pure.
 *
 * @param string $provider
 * @return string '' for an unknown provider
 */
function ws_model_provider_terms($provider)
{
    $providers = ws_model_providers();

    return isset($providers[$provider]) ? $providers[$provider]['terms'] : '';
}

/**
 * A provider's name as the card shows it: the brand, or "Custom address"
 * translated.
 *
 * @param string $provider
 * @return string
 */
function ws_model_provider_name($provider)
{
    if ($provider === 'custom') {
        return lang('Custom address');
    }

    $providers = ws_model_providers();

    return isset($providers[$provider]) ? $providers[$provider]['name'] : (string) $provider;
}

/**
 * The address a chat call goes to: the provider's base, or the one given,
 * with /chat/completions after it. An address given that already ends in
 * /chat/completions is kept as it is. Pure.
 *
 * @param string $provider
 * @param string $base the address given ('' for a provider with a fixed one)
 * @param string $path /chat/completions or /models
 * @return string
 */
function ws_model_endpoint($provider, $base, $path = '/chat/completions')
{
    $providers = ws_model_providers();
    $fixed = isset($providers[$provider]) ? $providers[$provider]['base'] : '';
    $base = rtrim(trim(($fixed !== '') ? $fixed : (string) $base), '/');

    if (substr($base, -17) === '/chat/completions') {
        $base = substr($base, 0, -17);
    }

    return $base . $path;
}

/**
 * The headers of a call, the key included when there is one. Pure.
 *
 * @param string $provider
 * @param string $key the key, opened
 * @return string[]
 */
function ws_model_headers($provider, $key)
{
    $providers = ws_model_providers();
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    $key = trim((string) $key);

    if ($key !== '') {
        $headers[] = 'Authorization: Bearer ' . $key;

        if (isset($providers[$provider]) && ($providers[$provider]['header'] === 'api-key')) {
            $headers[] = 'api-key: ' . $key;
        }
    }

    return $headers;
}

/**
 * Pinegrap AI's call body made fit for another API: the model is the
 * assistant's, reasoning_effort goes (a model that does not reason refuses
 * it), and the token limit takes the name the API reads. Pure.
 *
 * @param string $provider
 * @param string $model
 * @param array  $payload as ws_ai_step() writes it
 * @return array
 */
function ws_model_payload($provider, $model, $payload)
{
    $providers = ws_model_providers();
    $payload = (array) $payload;
    $payload['model'] = (string) $model;
    unset($payload['reasoning_effort']);

    if (isset($payload['max_tokens']) && isset($providers[$provider]) && ($providers[$provider]['tokens'] === 'max_completion_tokens')) {
        $payload['max_completion_tokens'] = $payload['max_tokens'];
        unset($payload['max_tokens']);
    }

    return $payload;
}

/**
 * The agent a request is for, from ws_ai_requests.agent: the model agent's
 * id, 0 for any other agent. Pure.
 *
 * @param string $agent
 * @return int
 */
function ws_model_agent_id_of($agent)
{
    return preg_match('/^model:([1-9][0-9]{0,9})$/', (string) $agent, $match) ? (int) $match[1] : 0;
}

/**
 * How long a model agent's requests wait after the failures in a row it has
 * had: a minute, doubled each time, an hour at most. Pure.
 *
 * @param int $failures
 * @return int seconds
 */
function ws_model_hold_seconds($failures)
{
    $failures = max(0, (int) $failures);

    if ($failures === 0) {
        return 0;
    }

    return (int) min(WS_MODEL_HOLD_MAX, 60 * pow(2, min($failures - 1, 6)));
}

/**
 * What is wrong with the chat API's address a model assistant is given: ''
 * when nothing is. Only Azure OpenAI and a custom server take one (the
 * others have a fixed address); only a custom server may be plain http (an
 * Ollama server on the local network). Pure.
 *
 * @param string $provider
 * @param string $base
 * @return string
 */
function ws_model_base_problem($provider, $base)
{
    $providers = ws_model_providers();

    if (!isset($providers[$provider])) {
        return lang('Choose the service that answers.');
    }

    if ($providers[$provider]['base'] !== '') {
        return '';
    }

    $base = trim((string) $base);
    $parts = parse_url($base);
    $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

    if (!is_array($parts) || ((string) ($parts['host'] ?? '') === '') || (strlen($base) > 500)
        || !(($scheme === 'https') || ($providers[$provider]['http'] && ($scheme === 'http')))) {
        return $providers[$provider]['http']
            ? lang('Write the address of the chat API, such as http://localhost:11434/v1.')
            : lang('Write the https address of the chat API, such as https://your-resource.openai.azure.com/openai/v1.');
    }

    return '';
}

/* ---------------------------------------------------------------------------
   The registry rows
   --------------------------------------------------------------------------- */

/**
 * The model assistants, raw rows (sealed key included), by handle.
 *
 * @return array[]
 */
function ws_model_agents()
{
    if (!ws_agents_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_agents WHERE kind = 'model' ORDER BY handle");
}

/**
 * One model assistant's raw row, or null.
 *
 * @param int $id
 * @return array|null
 */
function ws_model_agent_row($id)
{
    if (!ws_agents_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE id = '" . (int) $id . "' AND kind = 'model'");

    return $row ? (array) $row : null;
}

/**
 * The assistant whose request is being answered now, while ws_ai_step()
 * runs it; null for Pinegrap AI's own requests.
 *
 * @param array|null|false $set a row to hold, null to let go, false to read
 * @return array|null
 */
function ws_model_running($set = false)
{
    static $running = null;

    if ($set !== false) {
        $running = is_array($set) ? $set : null;
    }

    return $running;
}

/**
 * Pinegrap AI's config as the running assistant has it: its application,
 * its model, no licence hold or error of the gateway's. Pure.
 *
 * @param array $config ws_ai_config()
 * @param array $agent  the raw row
 * @return array
 */
function ws_model_config_over($config, $agent)
{
    $config['enabled'] = true;
    $config['app_id'] = (int) ($agent['app_id'] ?? 0);
    $config['model'] = (string) ($agent['model'] ?? '');
    $config['error'] = '';
    $config['hold_until'] = 0;

    return $config;
}

/**
 * One chat call to a model assistant's API, answered in ws_ai_http()'s shape.
 *
 * @param array  $agent   the raw row
 * @param string $method  GET | POST
 * @param string $path    /chat/completions or /models
 * @param array|null $payload
 * @param int    $timeout
 * @return array http, json, body, headers, errno, error
 */
function ws_model_http($agent, $method, $path, $payload = null, $timeout = 60)
{
    $out = array('http' => 0, 'json' => null, 'body' => '', 'headers' => '', 'errno' => 0, 'error' => '');

    if (!function_exists('curl_init')) {
        $out['error'] = lang('This server cannot make outgoing HTTPS requests (cURL is missing).');
        return $out;
    }

    $provider = (string) $agent['provider'];
    $options = array(
        'response_headers' => true,
        'connect_timeout'  => 8,
        'timeout'          => max(5, (int) $timeout),
        'user_agent'       => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (workspace-model)',
        'headers'          => ws_model_headers($provider, ws_agent_unseal($agent['key_sealed'] ?? '')),
        'allow_http'       => ($provider === 'custom'),
    );

    if ($method === 'POST') {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0); // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined()
        $json = json_encode(ws_model_payload($provider, (string) $agent['model'], (array) $payload), $flags);

        if ($json === false) {
            $out['error'] = lang('The request to the model could not be written.');
            return $out;
        }

        $options['body'] = $json;
    }

    $answer = pg_curl_run($method, ws_model_endpoint($provider, (string) $agent['door_url'], $path), $options);

    $out['http'] = $answer['status'];
    $out['errno'] = $answer['errno'];
    $out['error'] = $answer['error'];

    if ($answer['errno'] === 0) {
        $out['headers'] = $answer['headers'];
        $out['body'] = $answer['body'];
        $out['json'] = json_decode($out['body'], true);
    }

    return $out;
}

/**
 * What went wrong with a call, in a sentence naming the assistant.
 *
 * @param array $agent
 * @param array $response
 * @return string
 */
function ws_model_http_error($agent, $response)
{
    $name = (string) $agent['name'];
    $http = (int) $response['http'];

    if ($http > 0) {
        $json = is_array($response['json']) ? $response['json'] : array();
        $said = $json['error']['message'] ?? ($json['error'] ?? '');
        $said = is_string($said) ? mb_substr(trim($said), 0, 160) : '';

        if (in_array($http, array(401, 403), true)) {
            return lang(array('string' => '{var:1} refused the key (HTTP {var:2}).', 'vars' => array($name, $http))) . (($said !== '') ? ' ' . $said : '');
        }

        return lang(array('string' => '{var:1} answered HTTP {var:2}.', 'vars' => array($name, $http))) . (($said !== '') ? ' ' . $said : '');
    }

    if ((int) $response['errno'] === 28) {
        return lang(array('string' => '{var:1} did not answer in time.', 'vars' => $name));
    }

    $error = trim((string) $response['error']);

    return lang(array('string' => '{var:1} could not be reached: {var:2}', 'vars' => array($name, mb_substr(($error !== '') ? $error : '-', 0, 150))));
}

/**
 * A failure sentence of the loop the assistant shares with Pinegrap AI
 * (ws_ai_loop_sentence()), naming the assistant.
 *
 * @param array  $agent the raw row
 * @param string $which unreachable | no_model | undone | unfinished | channel
 * @return string '' for any other
 */
function ws_model_loop_sentence($agent, $which)
{
    $name = (string) $agent['name'];

    switch ((string) $which) {
        case 'unreachable':
            return lang(array('string' => '{var:1} could not be reached or took too long to answer.', 'vars' => $name));

        case 'no_model':
            return lang(array('string' => '{var:1} has no model to answer with.', 'vars' => $name));

        case 'undone':
            return lang(array('string' => '{var:1} could not do it', 'vars' => $name));

        case 'unfinished':
            return lang(array('string' => '{var:1} could not finish the answer.', 'vars' => $name));

        case 'channel':
            return lang(array('string' => '{var:1} may not be asked in this channel.', 'vars' => $name));
    }

    return '';
}

/**
 * The activity log's sentence for an answer the assistant wrote in a
 * channel: by its @handle, in the words an application agent's answer is
 * logged with (ws_api_agent_request_answer()).
 *
 * @param array $agent   the raw row
 * @param array $channel the channel or discussion the answer was written in
 * @return string
 */
function ws_model_log_answered($agent, $channel)
{
    return lang(array('string' => '@{var:1} answered a request in #{var:2}', 'vars' => array((string) $agent['handle'], (string) $channel['name'])));
}

/**
 * May a model assistant be asked in a channel, from what is known of the
 * two? Its own row there decides (ws_channel_agents.allowed), as it decides
 * an application agent's answer (ws_api_agent_request_place()): the
 * channel's people added it, so a private channel nobody opened to Pinegrap
 * AI does not keep it out. A channel closed to Pinegrap AI on purpose
 * (ai_access 2) does: closing is the channel manager's
 * (ws_ai_channel_set()), while a row there can be a member's
 * (ws_channel_agent_add_problem(); only a private channel's new agent is
 * its manager's, ws_channel_agent_enter_verdict()), so an answer there
 * would send a closed channel's words to the assistant's provider on a
 * member's word. Nor is it asked where the assistants never are: a guest's
 * room, a channel a guest reads along in. Pure.
 *
 * @param array|null $channel     the channel; for a discussion, the channel it belongs to
 * @param bool       $guest_reads it is shared with somebody outside the team (ws_channel_share_open())
 * @param array|null $row         the assistant's ws_channel_agents row where the request was made
 * @return bool
 */
function ws_model_channel_verdict($channel, $guest_reads, $row)
{
    if (!is_array($channel) || (($channel['kind'] ?? '') === 'guest') || $guest_reads) {
        return false;
    }

    if ((int) ($channel['ai_access'] ?? 0) === 2) {
        return false;
    }

    return is_array($row) && ((int) ($row['allowed'] ?? 0) === 1);
}

/**
 * May this model assistant be asked in this channel
 * (ws_model_channel_verdict())?
 *
 * @param array      $agent   the raw row
 * @param array|null $channel
 * @return bool
 */
function ws_model_channel_allowed($agent, $channel)
{
    if (!is_array($channel)) {
        return false;
    }

    // A discussion follows its channel (threads.php) in what closes it; the
    // assistant's row is the one of the channel the request was made in.
    $thread = (($channel['kind'] ?? '') === 'thread');
    $place = $thread ? ws_thread_parent_channel($channel) : $channel;
    $guest_reads = function_exists('ws_channel_share_open')
        && (ws_channel_share_open($channel) || ($thread && ws_channel_share_open($place)));

    return ws_model_channel_verdict($place, $guest_reads, ws_channel_agent_row((int) $channel['id'], (int) $agent['id']));
}

/**
 * ws_ai_response_problem() for a model assistant: its failures go on its
 * own row, never on Pinegrap AI's licence or hold.
 *
 * @param array $agent
 * @param array $response
 * @return string '' | format | error
 */
function ws_model_response_problem($agent, $response)
{
    if (($response['http'] === 200) && isset($response['json']['choices'][0]['message']) && is_array($response['json']['choices'][0]['message'])) {
        if ((int) $agent['door_failures'] > 0) {
            db("UPDATE ws_agents SET door_failures = 0, door_error = '' WHERE id = '" . (int) $agent['id'] . "'");
        }

        return '';
    }

    $body = strtolower((string) $response['body']);

    if (($response['http'] === 400) && ((strpos($body, 'does not match') !== false) || (strpos($body, 'parse') !== false))) {
        return 'format';
    }

    ws_model_hold($agent, ws_model_http_error($agent, $response));

    return 'error';
}

/**
 * Holds a model assistant's requests back after a failed call: the failure
 * counted, its sentence kept, the next try after ws_model_hold_seconds().
 *
 * @param array  $agent
 * @param string $error
 */
function ws_model_hold($agent, $error)
{
    db("UPDATE ws_agents SET door_failures = door_failures + 1, door_error = '" . e(mb_substr((string) $error, 0, 250)) . "',
        door_last_knock = '" . time() . "' WHERE id = '" . (int) $agent['id'] . "'");

    foreach ((array) db_values("SELECT message_id FROM ws_ai_requests WHERE agent = 'model:" . (int) $agent['id'] . "' AND status IN ('queued', 'running')") as $message_id) {
        ws_message_touch($message_id);
    }
}

/**
 * What a model assistant still lacks before it can answer: '' when nothing.
 *
 * @param array $agent the raw row
 * @return string
 */
function ws_model_agent_missing($agent)
{
    $providers = ws_model_providers();
    $provider = $providers[(string) $agent['provider']] ?? null;

    if ((string) $agent['status'] !== 'active') {
        return lang('It is paused.');
    }

    if (!$provider) {
        return lang('Choose the service that answers.');
    }

    if (ws_model_base_problem((string) $agent['provider'], (string) $agent['door_url']) !== '') {
        return lang('Its chat API address is missing.');
    }

    if ($provider['key'] && (ws_agent_unseal($agent['key_sealed'] ?? '') === '')) {
        return lang('Its key is missing or cannot be read on this server any more: paste it again.');
    }

    if (((int) $agent['app_id'] <= 0) || !ws_claude_app((int) $agent['app_id'])) {
        return lang('It has no application to write through: choose one on its card.');
    }

    return '';
}

/* ---------------------------------------------------------------------------
   The work
   --------------------------------------------------------------------------- */

/**
 * The model assistants' requests gone stale: running too long, or queued
 * for a day.
 */
function ws_model_watchdog()
{
    static $done = false;

    if ($done || !ws_agents_ready() || !ws_ai_schema_ready()) {
        return;
    }

    $done = true;
    $now = time();

    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE agent LIKE 'model:%' AND status IN ('sent', 'running') AND GREATEST(sent_at, claimed_at) < '" . ($now - WS_AI_STALE) . "'
        LIMIT 50") as $row) {
        $agent = ws_model_agent_row(ws_model_agent_id_of($row['agent']));
        ws_model_running($agent);
        ws_ai_finish_fail($row, lang(array('string' => '{var:1} did not answer in time.', 'vars' => $agent ? (string) $agent['name'] : lang('The assistant'))));
        ws_model_running(null);
    }

    db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e(lang('The assistant did not take it within a day.')) . "', ai_state = NULL
        WHERE agent LIKE 'model:%' AND status = 'queued' AND created_at < '" . ($now - WS_AI_QUEUE_TTL) . "'");
}

/**
 * Carries the model assistants' waiting requests on, one call at a time per
 * assistant, while the budget lasts. Called beside ws_ai_kick() by the
 * screens waiting and the hourly job.
 *
 * @param int $budget seconds after which no new call is started
 * @return string idle | busy | held | working
 */
function ws_model_kick($budget = WS_AI_BUDGET)
{
    if (!ws_agents_ready() || !ws_ai_schema_ready()) {
        return 'idle';
    }

    ws_model_watchdog();

    $waiting = (array) db_values("SELECT DISTINCT agent FROM ws_ai_requests WHERE agent LIKE 'model:%' AND status IN ('queued', 'sent', 'running') LIMIT 20");

    if (empty($waiting)) {
        return 'idle';
    }

    $started = time();
    $status = 'idle';

    foreach ($waiting as $value) {
        $agent = ws_model_agent_row(ws_model_agent_id_of($value));

        if (!$agent) {
            db("UPDATE ws_ai_requests SET status = 'cancelled', error = '" . e(lang('The assistant was removed.')) . "', ai_state = NULL
                WHERE agent = '" . e($value) . "' AND status IN ('queued', 'sent', 'running')");
            continue;
        }

        if (ws_model_agent_missing($agent) !== '') {
            continue;
        }

        if (((int) $agent['door_last_knock'] + ws_model_hold_seconds((int) $agent['door_failures'])) > time()) {
            $status = 'held';
            continue;
        }

        if ((time() - $started) >= $budget) {
            break;
        }

        $status = ws_model_agent_run($agent, max(1, $budget - (time() - $started)));
    }

    return $status;
}

/**
 * One assistant's requests, one call at a time, under its own lock.
 *
 * @param array $agent the raw row
 * @param int   $budget
 * @return string busy | held | working | idle
 */
function ws_model_agent_run($agent, $budget)
{
    $lock = 'pg_ws_model_run_' . (int) $agent['id'];

    if ((int) db_value("SELECT GET_LOCK('" . $lock . "', 0)") !== 1) {
        return 'busy';
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(ws_ai_call_timeout() + 60);
    }

    $value = 'model:' . (int) $agent['id'];
    $started = time();
    $status = 'working';
    $guard = 0;

    ws_model_running($agent);

    do {
        $row = db_item("SELECT * FROM ws_ai_requests WHERE agent = '" . $value . "' AND status IN ('sent', 'running') ORDER BY id LIMIT 1");

        if (!is_array($row)) {
            $row = db_item("SELECT * FROM ws_ai_requests WHERE agent = '" . $value . "' AND status = 'queued' ORDER BY id LIMIT 1");

            if (!is_array($row)) {
                $status = 'idle';
                break;
            }

            if (!ws_ai_claim($row)) {
                continue;
            }

            $row = ws_ai_request_row($row['id']);
        }

        // An assistant that used its month's tokens takes no further step:
        // the request fails with the sentence that names the limit.
        $refusal = function_exists('ws_agent_token_refusal') ? ws_agent_token_refusal($agent) : '';

        if ($refusal !== '') {
            ws_ai_finish_fail($row, $refusal);
            continue;
        }

        // The answers of this step add their tokens to the request.
        ws_ai_usage_request((int) $row['id']);
        $step = ws_ai_step($row);
        ws_ai_usage_request(0);

        if ($step === 'held') {
            $status = 'held';
            break;
        }

        // The row is read again: a failure or a success above changed it.
        $agent = ws_model_agent_row((int) $agent['id']) ?: $agent;
        ws_model_running($agent);
    } while (((time() - $started) < $budget) && (++$guard < 20));

    ws_model_running(null);
    db_value("SELECT RELEASE_LOCK('" . $lock . "')");

    return $status;
}

/**
 * Tries a model assistant: one short answer from its model. For the agent
 * card's Try button.
 *
 * @param int $id
 * @return array ok, message (a sentence for the card)
 */
function ws_model_agent_test($id)
{
    $agent = ws_model_agent_row($id);

    if (!$agent) {
        return array('ok' => false, 'message' => lang('That assistant could not be found.'));
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_AI_CALL_TIMEOUT + 30);
    }

    $started = microtime(true);
    $response = ws_model_http($agent, 'POST', '/chat/completions', array(
        'messages'   => array(array('role' => 'user', 'content' => 'Reply with the single word: OK')),
        'max_tokens' => 200,
        'stream'     => false,
    ), WS_AI_CALL_TIMEOUT);

    if (($response['http'] !== 200) || !isset($response['json']['choices'][0]['message'])) {
        $error = ws_model_http_error($agent, $response);
        db("UPDATE ws_agents SET door_error = '" . e(mb_substr($error, 0, 250)) . "' WHERE id = '" . (int) $id . "'");

        return array('ok' => false, 'message' => $error);
    }

    db("UPDATE ws_agents SET door_failures = 0, door_error = '' WHERE id = '" . (int) $id . "'");

    return array('ok' => true, 'message' => lang(array(
        'string' => '{var:1} answered in {var:2} seconds with the model {var:3}.',
        'vars'   => array((string) $agent['name'], number_format(microtime(true) - $started, 1), (string) $agent['model']),
    )));
}
