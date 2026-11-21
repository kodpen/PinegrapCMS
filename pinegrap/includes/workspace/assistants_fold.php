<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the folded rows of Site Settings > Assistants and agents
 * (includes/settings/fold.php): the rows' order, names and icons, and each
 * part's one line of state with its pill and whether it needs attention.
 * The ws_assistants_fold_<part>() lines are pure but for lang() and date();
 * the facts they take are read by ws_assistants_fold_facts_<part>().
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
 * The rows' keys in reading order: Claude, Pinegrap AI, Agents, MCP servers,
 * Voice, Canvas templates, Bulk changes. A key is its card's id without
 * pgset-.
 *
 * @return string[]
 */
function ws_assistants_fold_order()
{
    return array('ws-claude', 'ws-ai', 'ws-agents', 'ws-mcp', 'ws-voice', 'ws-canvas-templates', 'ws-bulk');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of ws_assistants_fold_order()
 * @return array name, icon, tone
 */
function ws_assistants_fold_face($key)
{
    switch ($key) {
        case 'ws-claude':
            return array('name' => lang('Claude'), 'icon' => 'bi-stars', 'tone' => 'pg-tone-violet');
        case 'ws-ai':
            return array('name' => lang('Pinegrap AI'), 'icon' => 'bi-cpu', 'tone' => 'pg-tone-blue');
        case 'ws-agents':
            // "Agents" alone is the real-estate template's estate agents.
            return array('name' => lang('Workspace agents'), 'icon' => 'bi-robot', 'tone' => 'pg-tone-teal');
        case 'ws-mcp':
            return array('name' => lang('MCP servers'), 'icon' => 'bi-hdd-network', 'tone' => 'pg-tone-amber');
        case 'ws-voice':
            return array('name' => lang('Voice'), 'icon' => 'bi-mic', 'tone' => 'pg-tone-rose');
        case 'ws-canvas-templates':
            return array('name' => lang('Canvas templates'), 'icon' => 'bi-easel', 'tone' => '');
        default:
            return array('name' => lang('Bulk changes'), 'icon' => 'bi-collection', 'tone' => '');
    }
}

/**
 * The state of a part switched on or off by one switch and ready when
 * nothing is missing: the pill and the attention all three share.
 *
 * @param bool     $enabled
 * @param string[] $missing
 * @param string   $ready_line
 * @param string   $off_line
 * @return array line, pill (words, tone), attention
 */
function ws_assistants_fold_switched($enabled, $missing, $ready_line, $off_line)
{
    $missing = array_values((array) $missing);

    if (empty($missing)) {
        return array('line' => $ready_line, 'pill' => array(lang('Ready'), 'pg-tone-teal'), 'attention' => false);
    }

    if (!$enabled) {
        return array('line' => $off_line, 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    return array('line' => (string) $missing[0], 'pill' => array(lang('Not ready'), 'pg-tone-amber'), 'attention' => true);
}

/**
 * Claude's line: "Ready · last run 14:05" (the day too when it was not
 * today), the first thing missing, or switched off.
 *
 * @param array $facts enabled, missing (sentences), last_run (a time, 0 for none)
 * @param int   $now
 * @return array line, pill, attention
 */
function ws_assistants_fold_claude($facts, $now)
{
    $last = (int) ($facts['last_run'] ?? 0);

    if ($last > 0) {
        $when = (date('Y-m-d', $last) === date('Y-m-d', (int) $now)) ? date('H:i', $last) : date('d.m.Y H:i', $last);
        $run = lang(array('string' => 'last run {var:1}', 'vars' => $when));
    } else {
        $run = lang('no run yet');
    }

    return ws_assistants_fold_switched(!empty($facts['enabled']), $facts['missing'] ?? array(), lang('Ready') . ' · ' . $run, lang('Switched off'));
}

/**
 * Pinegrap AI's line.
 *
 * @param array $facts enabled, missing
 * @return array line, pill, attention
 */
function ws_assistants_fold_ai($facts)
{
    return ws_assistants_fold_switched(!empty($facts['enabled']), $facts['missing'] ?? array(), lang('On · answers in the channels'), lang('Switched off'));
}

/**
 * The voice transcripts' line.
 *
 * @param array $facts enabled, missing
 * @return array line, pill, attention
 */
function ws_assistants_fold_voice($facts)
{
    return ws_assistants_fold_switched(!empty($facts['enabled']), $facts['missing'] ?? array(), lang('Voice messages are turned into text'), lang('Voice messages are not turned into text'));
}

/**
 * The agents' line: how many, how many not seen yet; the pill counts the
 * requests waiting. A waiting request is work for the agent, not a fault of
 * the settings, so it asks for no attention.
 *
 * @param array $facts total, not_seen, waiting
 * @return array line, pill, attention
 */
function ws_assistants_fold_agents($facts)
{
    $total = (int) ($facts['total'] ?? 0);
    $not_seen = (int) ($facts['not_seen'] ?? 0);
    $waiting = (int) ($facts['waiting'] ?? 0);

    if ($total === 0) {
        $line = lang('No agent yet');
    } else {
        $line = ($total === 1) ? lang('1 agent') : lang(array('string' => '{var:1} agents', 'vars' => $total));

        if ($not_seen > 0) {
            $line .= ' · ' . lang(array('string' => '{var:1} not seen yet', 'vars' => $not_seen));
        }
    }

    return array(
        'line'      => $line,
        'pill'      => ($waiting > 0) ? array(lang(array('string' => '{var:1} waiting', 'vars' => $waiting)), 'pg-tone-amber') : null,
        'attention' => false,
    );
}

/**
 * The MCP servers' line: how many, how many ask for a sign-in. A sign-in or
 * a server out of reach asks for attention.
 *
 * @param array $facts total, sign_in, unreachable, ready
 * @return array line, pill, attention
 */
function ws_assistants_fold_mcp($facts)
{
    $total = (int) ($facts['total'] ?? 0);
    $sign_in = (int) ($facts['sign_in'] ?? 0);
    $unreachable = (int) ($facts['unreachable'] ?? 0);

    if ($total === 0) {
        return array('line' => lang('No server yet'), 'pill' => null, 'attention' => false);
    }

    $line = ($total === 1) ? lang('1 server') : lang(array('string' => '{var:1} servers', 'vars' => $total));

    if ($sign_in > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} asks for a sign-in', 'vars' => $sign_in));
    }

    if ($unreachable > 0) {
        $pill = array(lang('Cannot be reached'), 'pg-tone-rose');
    } elseif ($sign_in > 0) {
        $pill = array(lang('Sign-in needed'), 'pg-tone-amber');
    } elseif ((int) ($facts['ready'] ?? 0) === $total) {
        $pill = array(lang('Ready'), 'pg-tone-teal');
    } else {
        $pill = null;
    }

    return array('line' => $line, 'pill' => $pill, 'attention' => ($sign_in + $unreachable) > 0);
}

/**
 * The canvas templates' line.
 *
 * @param int $count
 * @return array line, pill, attention
 */
function ws_assistants_fold_templates($count)
{
    $count = (int) $count;

    if ($count === 0) {
        $line = lang('No template yet');
    } elseif ($count === 1) {
        $line = lang('1 template');
    } else {
        $line = lang(array('string' => '{var:1} templates', 'vars' => $count));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The bulk changes' line: what the one switch says.
 *
 * @param bool $delete_allowed
 * @return array line, pill, attention
 */
function ws_assistants_fold_bulk($delete_allowed)
{
    return array('line' => $delete_allowed ? lang('Bulk deletes may be proposed') : lang('Bulk deletes are off'), 'pill' => null, 'attention' => false);
}

/* ---------------------------------------------------------------------------
   The facts, read where the cards read them
   --------------------------------------------------------------------------- */

/**
 * @return array enabled, missing, last_run
 */
function ws_assistants_fold_facts_claude()
{
    $config = ws_claude_config();

    return array(
        'enabled'  => $config['enabled'],
        'missing'  => ws_claude_missing(),
        'last_run' => (int) db_value("SELECT MAX(answered_at) FROM ws_ai_requests WHERE 1 = 1" . ws_claude_only()),
    );
}

/**
 * @return array enabled, missing
 */
function ws_assistants_fold_facts_ai()
{
    return array('enabled' => ws_ai_config()['enabled'], 'missing' => ws_ai_missing());
}

/**
 * @return array enabled, missing
 */
function ws_assistants_fold_facts_voice()
{
    return array('enabled' => ws_voice_config()['enabled'], 'missing' => ws_voice_missing());
}

/**
 * @param array $agents   ws_agents_list()
 * @param array $requests ws_agent_request_counts() of them
 * @param int   $now
 * @return array total, not_seen, waiting
 */
function ws_assistants_fold_facts_agents($agents, $requests, $now)
{
    $facts = array('total' => count($agents), 'not_seen' => 0, 'waiting' => 0);

    foreach ($agents as $agent) {
        if ((string) $agent['status'] === 'off') {
            continue;
        }

        if (ws_agent_seen_words($agent, $now)['not_seen']) {
            $facts['not_seen']++;
        }

        $mine = ((array) $requests)[ws_agent_request_value($agent)] ?? array();
        $facts['waiting'] += (int) ($mine['queued'] ?? 0);
    }

    return $facts;
}

/**
 * @param array $servers ws_mcp_servers()
 * @return array total, sign_in, unreachable, ready
 */
function ws_assistants_fold_facts_mcp($servers)
{
    $facts = array('total' => count($servers), 'sign_in' => 0, 'unreachable' => 0, 'ready' => 0);

    foreach ($servers as $server) {
        $state = ws_mcp_server_state($server)['state'];

        if (in_array($state, array('connect_again', 'not_connected'), true)) {
            $facts['sign_in']++;
        } elseif ($state === 'unreachable') {
            $facts['unreachable']++;
        } elseif ($state === 'ready') {
            $facts['ready']++;
        }
    }

    return $facts;
}

/* ---------------------------------------------------------------------------
   A long list: five rows in the pane, every row in the part's drawer
   --------------------------------------------------------------------------- */

/**
 * The rows a part's list shows in its pane (MCP servers, Workspace agents,
 * Canvas templates). With five or fewer every row stands, in the list's
 * order; with more, the rows that need something come first, then the
 * others, five in all, and the rest wait in the part's drawer. Pure.
 *
 * @param bool[] $asks  one per row in the list's order: true when the row
 *                      needs something (a rose or amber pill)
 * @param int    $limit
 * @return array the keys of $asks to show, in the order they stand
 */
function ws_assistants_list_cut($asks, $limit = 5)
{
    $keys = array_keys((array) $asks);

    if (count($keys) <= $limit) {
        return $keys;
    }

    $first = array();
    $rest = array();

    foreach ((array) $asks as $key => $ask) {
        if ($ask) {
            $first[] = $key;
        } else {
            $rest[] = $key;
        }
    }

    return array_slice(array_merge($first, $rest), 0, $limit);
}

/**
 * A row's pill asks for something when it is rose or amber.
 *
 * @param string $tone the pill's pg-tone-* class
 * @return bool
 */
function ws_assistants_list_asks($tone)
{
    return in_array((string) $tone, array('pg-tone-rose', 'pg-tone-amber'), true);
}

/**
 * "Show all N" under a cut list, opening the part's drawer
 * (ws_settings_assistants_all_drawer()); '' when nothing was cut.
 *
 * @param string $key   ws-mcp, ws-agents or ws-canvas-templates
 * @param int    $count every row of the list
 * @param int    $limit
 * @return string HTML
 */
function ws_assistants_show_all_html($key, $count, $limit = 5)
{
    if ((int) $count <= $limit) {
        return '';
    }

    $id = 'pgset-' . $key . '-all';

    return '<button type="button" class="pg-button pg-button-quiet pg-button-sm mt-3" data-bs-toggle="drawer" data-bs-target="#' . h($id) . '" aria-controls="' . h($id) . '">' . h(lang(array('string' => 'Show all {var:1}', 'vars' => array((int) $count)))) . '</button>';
}

/**
 * The drawer "Show all N" opens: every row of the part's list with the
 * actions the rows have in the pane. Its own drawer buttons (a server's
 * Tools and settings, an agent's Edit) open the dialog's drawers over it.
 *
 * @param string $key       ws-mcp, ws-agents or ws-canvas-templates
 * @param string $body      every row, as markup
 * @param bool   $in_dialog drawn in the settings dialog
 * @param string $foot      buttons kept in view under the rows, as markup
 * @return string HTML
 */
function ws_settings_assistants_all_drawer($key, $body, $in_dialog, $foot = '')
{
    if ($in_dialog) {
        $body = str_replace('data-bs-toggle="drawer"', 'data-pgdrawer="open"', $body);
    }

    return pg_settings_drawer('pgset-' . $key . '-all', h(ws_assistants_fold_face($key)['name']), $body, $in_dialog, $foot);
}
