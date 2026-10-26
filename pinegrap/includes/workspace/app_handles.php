<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - how an application's tag (<@app:N>) is drawn.
 *
 * The application linked as Claude reads "@Claude" and Pinegrap AI's reads
 * "@ai"; any other application reads as a handle made from its own name
 * (api_apps.name), with that name beside it. A handle has the shape of an MCP
 * server's alias (ws_mcp_alias_shape_problem()) and never takes one of the
 * Workspace's own words or an alias a server already holds, so "@" means one
 * thing; where the name gives none, the handle is app-<id>. No history of
 * the links is kept, so an application Claude used before reads as itself.
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
 * The handle an application's name gives, without the @.
 *
 * @param string   $name   the application's name
 * @param int      $app_id its id, for the fallback app-<id>
 * @param string[] $taken  handles held already (MCP servers' aliases)
 * @return string
 */
function ws_app_handle($name, $app_id, $taken = array())
{
    // Accented Latin letters are folded so a Turkish name keeps its words;
    // anything else outside a-z, 0-9, _ and - becomes one dash.
    $fold = array(
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
        'ä' => 'a', 'Ä' => 'a', 'é' => 'e', 'É' => 'e', 'è' => 'e', 'È' => 'e',
        'ß' => 'ss',
    );

    $handle = strtolower(strtr(trim((string) $name), $fold));
    $handle = preg_replace('/[^a-z0-9_-]+/', '-', $handle);
    $handle = preg_replace('/-{2,}/', '-', $handle);
    $handle = preg_replace('/^[^a-z]+/', '', $handle);
    $handle = rtrim(substr($handle, 0, 32), '-_');

    $taken = array_map('ws_mcp_alias_clean', (array) $taken);

    if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $handle)
        || in_array($handle, ws_mcp_reserved_aliases(), true)
        || in_array($handle, $taken, true)) {
        return 'app-' . (int) $app_id;
    }

    return $handle;
}

/**
 * How one application's tag is drawn.
 *
 * @param int        $app_id        the tag's id
 * @param array|null $app           its api_apps row (id, name), null when it is gone
 * @param int        $claude_app_id the application linked as Claude (0 for none)
 * @param int        $ai_app_id     Pinegrap AI's application (0 for none)
 * @param string[]   $taken         handles held already (MCP servers' aliases)
 * @return array label, meta, icon, known, gone
 */
function ws_app_tag($app_id, $app, $claude_app_id, $ai_app_id, $taken = array())
{
    $app_id = (int) $app_id;

    // The linked assistants keep their names while the link points at them,
    // also when the link is switched off or its application was deleted.
    if (($app_id > 0) && ($app_id === (int) $ai_app_id)) {
        return array('label' => '@ai', 'meta' => lang('Pinegrap AI'), 'icon' => 'bi-cpu', 'known' => true, 'gone' => false);
    }

    if (($app_id > 0) && ($app_id === (int) $claude_app_id)) {
        return array('label' => '@Claude', 'meta' => lang('AI assistant'), 'icon' => 'bi-stars', 'known' => true, 'gone' => false);
    }

    if (!is_array($app)) {
        return array('label' => '@app-' . $app_id, 'meta' => lang('Deleted record'), 'icon' => 'bi-x-circle', 'known' => false, 'gone' => true);
    }

    $name = trim((string) ($app['name'] ?? ''));

    return array(
        'label' => '@' . ws_app_handle($name, $app_id, $taken),
        'meta'  => ($name !== '') ? $name : lang('Application'),
        'icon'  => 'bi-plug',
        'known' => true,
        'gone'  => false,
    );
}

/**
 * The applications the @ picker offers in one channel: those a mention there
 * reaches (ws_message_tell_apps()) - an active subscription to
 * workspace.app.mentioned, everywhere or narrowed to a group the channel is
 * in, a key that hears mentions and an owner who reads the channel. Claude's
 * and Pinegrap AI's applications are left out: the picker offers them as
 * themselves. Each entry is drawn with ws_app_tag(), so the picked text is
 * the tag as it will read.
 *
 * @param array    $apps          api_apps rows (id, name) by id
 * @param array    $hooks         subscriptions: app_id, events (JSON as stored), channel_group_id (missing for 0)
 * @param int[]    $chain         the groups the channel is in, nearest first
 * @param int      $claude_app_id the application linked as Claude (0 for none)
 * @param int      $ai_app_id     Pinegrap AI's application (0 for none)
 * @param string[] $taken         handles held already (MCP servers' aliases)
 * @param bool[]   $hears         by app id: ws_app_hears_mentions()
 * @param bool[]   $reads         by app id: ws_app_reads_channel() for its owner
 * @return array[] label, meta, icon, token, shown; by label
 */
function ws_app_picker_entries($apps, $hooks, $chain, $claude_app_id, $ai_app_id, $taken, $hears, $reads)
{
    $chain = array_map('intval', (array) $chain);
    $entries = array();

    foreach ((array) $hooks as $hook) {
        $app_id = (int) ($hook['app_id'] ?? 0);

        if (($app_id <= 0) || isset($entries[$app_id]) || !isset($apps[$app_id])
            || ($app_id === (int) $claude_app_id) || ($app_id === (int) $ai_app_id)
            || empty($hears[$app_id]) || empty($reads[$app_id])) {
            continue;
        }

        $events = json_decode((string) ($hook['events'] ?? ''), true);

        if (!is_array($events) || !in_array('workspace.app.mentioned', $events, true)) {
            continue;
        }

        $group_id = (int) ($hook['channel_group_id'] ?? 0);

        if (($group_id > 0) && !in_array($group_id, $chain, true)) {
            continue;
        }

        $tag = ws_app_tag($app_id, $apps[$app_id], $claude_app_id, $ai_app_id, $taken);

        $entries[$app_id] = array(
            'label' => $tag['label'],
            'meta'  => $tag['meta'],
            'icon'  => 'bi-plug',
            'token' => '<@app:' . $app_id . '>',
            'shown' => $tag['label'],
        );
    }

    usort($entries, function ($a, $b) {
        return strcmp($a['label'], $b['label']);
    });

    return $entries;
}

/**
 * The applications the @ picker offers in a channel (ws_app_picker_entries()),
 * read with one query for the subscriptions and the conditions
 * ws_message_tell_apps() asks before it tells an application.
 *
 * @param array $channel the channel or discussion
 * @return array[]
 */
function ws_channel_app_picker($channel)
{
    if (!function_exists('ws_app_hears_mentions') || !function_exists('api_webhook_channel_groups_ready')) {
        return array();
    }

    $narrowed = api_webhook_channel_groups_ready();

    $hooks = db_items("SELECT api_webhooks.app_id, api_webhooks.events"
        . ($narrowed ? ", api_webhooks.channel_group_id" : "") . ",
        api_apps.name, api_apps.status, api_apps.expires_at, api_apps.owner_user_id, api_apps.scopes
        FROM api_webhooks
        INNER JOIN api_apps ON api_apps.id = api_webhooks.app_id
        WHERE api_webhooks.status IN ('active', 'failing') AND api_apps.status = 'active'
        AND api_webhooks.events LIKE '%workspace.app.mentioned%'");

    if (!is_array($hooks) || empty($hooks)) {
        return array();
    }

    $claude_app_id = (function_exists('ws_claude_schema_ready') && ws_claude_schema_ready()) ? (int) ws_claude_config()['app_id'] : 0;
    $ai_app_id = (function_exists('ws_ai_schema_ready') && ws_ai_schema_ready()) ? (int) ws_ai_config()['app_id'] : 0;

    $apps = array();
    $hears = array();
    $reads = array();
    $chain = null;

    foreach ($hooks as $hook) {
        $app_id = (int) $hook['app_id'];

        // A narrowed subscription is matched against the channel's groups as
        // the queue matches it (a discussion by the channel it is about);
        // read once and only when there is one.
        if (($chain === null) && ((int) ($hook['channel_group_id'] ?? 0) > 0)) {
            $thread = ws_channel_is_thread($channel) ? ws_thread($channel['id']) : null;
            $chain = ws_webhook_event_groups('workspace.app.mentioned', array(
                'channel_id' => (int) $channel['id'],
                'thread'     => is_array($thread) ? array('channel_id' => (int) $thread['parent_channel_id']) : null,
            ));
        }

        if (isset($apps[$app_id]) || ($app_id === $claude_app_id) || ($app_id === $ai_app_id)) {
            continue;
        }

        $apps[$app_id] = array('id' => $app_id, 'name' => $hook['name']);
        $hears[$app_id] = ws_app_hears_mentions($hook);

        if ($hears[$app_id]) {
            $owner = pg_load_user_row((int) $hook['owner_user_id']);
            $reads[$app_id] = ws_app_reads_channel(is_array($owner) ? ws_viewer($owner) : null, $channel);
        }
    }

    $taken = function_exists('ws_mcp_servers') ? array_column(ws_mcp_servers(), 'alias') : array();

    return ws_app_picker_entries($apps, $hooks, (array) $chain, $claude_app_id, $ai_app_id, $taken, $hears, $reads);
}
