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
