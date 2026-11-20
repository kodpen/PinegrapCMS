<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One connection per AI provider (OpenAI, Anthropic, Google Gemini,
 * Mistral, DeepSeek, OpenRouter, Azure OpenAI): the provider's key, typed
 * once on its page on Connections (connections.php?service=<key>) and used
 * by every feature of that provider that keeps no key of its own. The key
 * is kept sealed among the service entries of the connected accounts
 * (pg_connect_service_put(), key ai_<provider>), so no table or column is
 * added.
 *
 * A model assistant that still holds a key of its own goes on using it;
 * the open version's data step (upgrade_2026_11_0_ai_connections()) moved
 * the first stored key of each provider into its connection and emptied
 * the assistants' keys equal to it (pg_ai_connection_move()).
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
 * The AI connections: the registry key of each one and the model provider
 * (ws_model_providers()) it holds the key of. Pure.
 *
 * @return array registry key => model provider key
 */
function pg_ai_connection_services()
{
    return array(
        'openai'       => 'openai',
        'anthropic'    => 'anthropic',
        'gemini'       => 'gemini',
        'mistral'      => 'mistral',
        'deepseek'     => 'deepseek',
        'openrouter'   => 'openrouter',
        'azure_openai' => 'azure',
    );
}

/**
 * The Site Settings category whose gate an AI connection keeps: the one its
 * key was typed in before it moved to Connections. Pure.
 *
 * @return string
 */
function pg_ai_connection_category()
{
    return 'workspace_assistants';
}

/**
 * The name a provider's connection is kept under among the service
 * entries. Pure.
 *
 * @param string $provider a model provider key
 * @return string
 */
function pg_ai_connection_store_key($provider)
{
    return 'ai_' . (string) $provider;
}

/**
 * Is this a model provider that has a connection? Pure.
 *
 * @param string $provider
 * @return bool
 */
function pg_ai_connection_known($provider)
{
    return in_array((string) $provider, pg_ai_connection_services(), true);
}

/**
 * The key a provider's connection holds, opened; '' when none is stored or
 * it cannot be read on this server any more.
 *
 * @param string $provider a model provider key
 * @return string
 */
function pg_ai_connection_key($provider)
{
    if (!pg_ai_connection_known($provider) || !function_exists('pg_connect_service_get')) {
        return '';
    }

    $data = pg_connect_service_get(pg_ai_connection_store_key($provider));

    return isset($data['key']) ? trim((string) $data['key']) : '';
}

/**
 * Is the provider connected: a key is stored that can be read? The state
 * reader of the registry's AI rows.
 *
 * @param string $provider a model provider key
 * @return bool
 */
function pg_ai_connection_ready($provider)
{
    return pg_ai_connection_key($provider) !== '';
}

/**
 * Could this be a provider's key? Printable characters without spaces, as
 * the assistants' form took them. Pure.
 *
 * @param string $key
 * @return bool
 */
function pg_ai_connection_key_shape_ok($key)
{
    return (bool) preg_match('/^[\x21-\x7e]{8,512}$/', (string) $key);
}

/**
 * Stores a provider's key as its connection.
 *
 * @param string $provider a model provider key
 * @param string $name     the provider's name, for the screens
 * @param string $key      the key, plain
 * @return bool false when the site cannot seal it
 */
function pg_ai_connection_save($provider, $name, $key)
{
    if (!pg_ai_connection_known($provider) || !function_exists('pg_connect_service_put')) {
        return false;
    }

    return pg_connect_service_put(pg_ai_connection_store_key($provider), (string) $name, array('key' => (string) $key));
}

/**
 * Removes a provider's connection; the assistants with a key of their own
 * go on with it.
 *
 * @param string $provider a model provider key
 */
function pg_ai_connection_remove($provider)
{
    if (pg_ai_connection_known($provider) && function_exists('pg_connect_service_delete')) {
        pg_connect_service_delete(pg_ai_connection_store_key($provider));
    }
}

/**
 * The key an assistant calls its provider with: its own when it keeps one,
 * otherwise its provider's connection.
 *
 * @param string $provider
 * @param string $own the assistant's own key, opened ('' for none)
 * @return string
 */
function pg_ai_connection_key_for($provider, $own)
{
    $own = trim((string) $own);

    return ($own !== '') ? $own : pg_ai_connection_key($provider);
}

/**
 * What the data step does for one provider. Where no connection exists the
 * first stored key (in the order given) becomes it; every key equal to the
 * connection's is emptied; a key that differs is kept and goes on working.
 * Run again it changes nothing. Pure.
 *
 * @param string  $connection the connection's key, '' for none
 * @param array[] $agents     id, key (opened, '' for none), in id order
 * @return array connection (the key to store, '' for none), store (bool:
 *               it is new), empty (the ids whose key is emptied)
 */
function pg_ai_connection_move($connection, $agents)
{
    $connection = trim((string) $connection);
    $store = false;

    if ($connection === '') {
        foreach ($agents as $agent) {
            $key = trim((string) ($agent['key'] ?? ''));

            if ($key !== '') {
                $connection = $key;
                $store = true;
                break;
            }
        }
    }

    $empty = array();

    if ($connection !== '') {
        foreach ($agents as $agent) {
            if (trim((string) ($agent['key'] ?? '')) === $connection) {
                $empty[] = (int) $agent['id'];
            }
        }
    }

    return array('connection' => $connection, 'store' => $store, 'empty' => $empty);
}

/**
 * The model assistants of a provider as its page's "Where it is used"
 * lists them: id, name, own (it keeps a key of its own). Pure.
 *
 * @param string  $provider a model provider key
 * @param array[] $agents   raw ws_agents rows of kind model
 * @return array[]
 */
function pg_ai_connection_users($provider, $agents)
{
    $users = array();

    foreach ($agents as $agent) {
        if ((string) ($agent['provider'] ?? '') !== (string) $provider) {
            continue;
        }

        $users[] = array(
            'id'   => (int) $agent['id'],
            'name' => (string) $agent['name'],
            'own'  => (strpos((string) ($agent['key_sealed'] ?? ''), ':') !== false),
        );
    }

    return $users;
}

/**
 * Asks the provider whether a key works, with its model list: an address of
 * the provider's own, so Azure OpenAI, whose address each assistant gives,
 * is tried through its assistants instead. Needs the Workspace's model
 * functions.
 *
 * @param string $provider a model provider key
 * @param string $key      the key, plain
 * @return array ok (bool|null: null when nothing could be tried), message
 */
function pg_ai_connection_try($provider, $key)
{
    if (!function_exists('ws_model_http') || !function_exists('ws_model_providers')) {
        return array('ok' => null, 'message' => lang('The Workspace is off, so the key could not be tried.'));
    }

    $providers = ws_model_providers();

    if (!isset($providers[$provider]) || ((string) $providers[$provider]['base'] === '')) {
        return array('ok' => null, 'message' => lang('Each assistant gives this provider\'s address, so the key is tried with Test on an assistant.'));
    }

    $name = (string) $providers[$provider]['name'];
    $response = ws_model_http(array('provider' => $provider, 'door_url' => '', 'model' => '', 'name' => $name, 'key_plain' => (string) $key), 'GET', '/models', null, 20);

    if ($response['http'] === 200) {
        return array('ok' => true, 'message' => lang(array('string' => '{var:1} accepted the key.', 'vars' => $name)));
    }

    return array('ok' => false, 'message' => ws_model_http_error(array('name' => $name), $response));
}

/**
 * The registry key of a provider's connection, its page's service. Pure.
 *
 * @param string $provider a model provider key
 * @return string '' for a provider without a connection
 */
function pg_ai_connection_service_of($provider)
{
    $service = array_search((string) $provider, pg_ai_connection_services(), true);

    return ($service === false) ? '' : (string) $service;
}

/**
 * The address of a provider's page on Connections. Pure.
 *
 * @param string $provider a model provider key
 * @return string '' for a provider without a connection
 */
function pg_ai_connection_page_url($provider)
{
    $service = pg_ai_connection_service_of($provider);

    return ($service === '') ? '' : 'connections.php?service=' . rawurlencode($service);
}

/**
 * Under a feature's "Provider" select: the chosen provider's connection with
 * its state and the way to it on Connections ("Manage in Connections", or
 * "Connect in Connections" with a line saying it is not connected). A
 * feature that still keeps a key of its own says so, with a box that gives
 * it up for the connection's key, posted with the form's Save.
 *
 * @param string $provider a model provider key that has a connection
 * @param string $name     the provider's name
 * @param bool   $ready    the connection holds a key (pg_ai_connection_ready())
 * @param array  $own      null, or the own key's box: name, id, attrs (the
 *                         form's disabled and form="" attributes)
 * @return string HTML
 */
function pg_ai_connection_block_html($provider, $name, $ready, $own = null)
{
    $url = pg_ai_connection_page_url($provider);
    $pill = $ready
        ? '<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>'
        : '<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>';
    $line = $ready
        ? lang(array('string' => 'Calls {var:1} with the key of its connection.', 'vars' => $name))
        : lang(array('string' => '{var:1} is not connected: connect it before switching this on.', 'vars' => $name));
    $own_html = '';

    if ($own !== null) {
        $own_html = '<div class="d-flex flex-wrap align-items-center gap-3"><span class="pg-text-meta">' . h(lang('Uses its own key')) . '</span>'
            . '<label class="pg-text-2" for="' . h($own['id']) . '"><input class="pg-checkbox" type="checkbox" name="' . h($own['name']) . '" value="1" id="' . h($own['id']) . '"' . $own['attrs'] . '> '
            . '<span>' . h(lang('Use the connection\'s key')) . '</span></label></div>';
    }

    return '<div class="d-flex flex-column align-items-start gap-2">'
        . '<div class="d-flex flex-wrap align-items-center gap-3"><span class="fw-semibold">' . h(lang(array('string' => '{var:1} connection', 'vars' => $name))) . '</span>' . $pill . '</div>'
        . '<div class="pg-field-help mt-0">' . h($line) . '</div>'
        . $own_html
        . (($url !== '') ? '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url) . '">' . h($ready ? lang('Manage in Connections') : lang('Connect in Connections')) . '</a>' : '')
        . '</div>';
}

/**
 * Is a voice service address OpenAI's own: empty (the default) or OpenAI's
 * base, with or without the endpoint pasted after it? Pure.
 *
 * @param string $url
 * @return bool
 */
function pg_ai_connection_voice_on_openai($url)
{
    $url = preg_replace('#/audio/(transcriptions|speech)$#i', '', rtrim(trim((string) $url), '/'));

    return (rtrim((string) $url, '/') === '') || (strtolower(rtrim((string) $url, '/')) === 'https://api.openai.com/v1');
}

/**
 * What the data step does with the voice feature's keys, by the same rule
 * as the assistants' (pg_ai_connection_move()): the transcripts' key, and
 * the spoken answers' when they go to OpenAI too, are OpenAI keys. Where no
 * connection exists the first of them becomes it; a key equal to it is
 * emptied; a key that differs is kept. A key of another address is left
 * alone. Pure.
 *
 * @param string $connection OpenAI's connection key, '' for none
 * @param array  $voice      url, key, speech_url, speech_key (keys opened)
 * @return array connection, store (bool), empty (the config_text names to
 *               empty: ws_voice_key, ws_voice_speech_key)
 */
function pg_ai_connection_voice_move($connection, $voice)
{
    $transcripts = pg_ai_connection_voice_on_openai($voice['url'] ?? '');
    $speech_url = trim((string) ($voice['speech_url'] ?? ''));
    $speech = ($speech_url === '') ? $transcripts : pg_ai_connection_voice_on_openai($speech_url);
    $keys = array();

    if ($transcripts) {
        $keys[] = array('id' => 1, 'key' => (string) ($voice['key'] ?? ''));
    }

    if ($speech) {
        $keys[] = array('id' => 2, 'key' => (string) ($voice['speech_key'] ?? ''));
    }

    $move = pg_ai_connection_move($connection, $keys);

    // An emptied speech key falls back to OpenAI's connection only when the
    // speech has an OpenAI address of its own beside another transcripts
    // service, or when the transcripts' key reads the connection too;
    // otherwise it would fall back to a different key of the transcripts.
    if (in_array(2, $move['empty'], true)) {
        $transcripts_key = trim((string) ($voice['key'] ?? ''));
        $falls_to_connection = $transcripts
            ? (($transcripts_key === '') || in_array(1, $move['empty'], true))
            : pg_ai_connection_voice_speech_on_connection($speech_url, $voice['url'] ?? '');

        if (!$falls_to_connection) {
            $move['empty'] = array_values(array_diff($move['empty'], array(2)));
        }
    }

    $names = array(1 => 'ws_voice_key', 2 => 'ws_voice_speech_key');
    $move['empty'] = array_values(array_map(function ($id) use ($names) {
        return $names[$id];
    }, $move['empty']));

    return $move;
}

/**
 * Do the spoken answers call with OpenAI's connection when they keep no key
 * of their own? Only with an OpenAI address of their own while the
 * transcripts go to another service: otherwise they take the transcripts'
 * key (ws_voice_key()), which falls back to the connection for OpenAI, and a
 * key of another service is never sent to OpenAI. Pure.
 *
 * @param string $speech_url      the spoken answers' address, '' to follow
 *                                the transcripts
 * @param string $transcripts_url the transcripts' address, '' for OpenAI
 * @return bool
 */
function pg_ai_connection_voice_speech_on_connection($speech_url, $transcripts_url)
{
    return (trim((string) $speech_url) !== '')
        && pg_ai_connection_voice_on_openai($speech_url)
        && !pg_ai_connection_voice_on_openai($transcripts_url);
}
