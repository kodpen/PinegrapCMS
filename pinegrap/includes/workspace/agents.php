<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agent registry: the checks an agent's handle and door
 * meet before it is kept. An agent is called by its handle (@handle), so a
 * handle has the shape of an MCP server's alias, is held by one agent only
 * and is never a person's user name or one of the Workspace's own words. A
 * door is how the site wakes it: push (the site posts the ids and its own
 * address to the agent's https address) or poll (the agent reads its
 * requests itself, so the door has no address).
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
 * What is wrong with an agent's handle: '' when nothing is. Pure: the
 * handles held already and the people's user names are read by the caller.
 *
 * @param string   $handle     without the @
 * @param string[] $taken      handles other agents and MCP servers hold
 * @param string[] $user_names the people's user names
 * @return string
 */
function ws_agent_handle_problem($handle, $taken, $user_names)
{
    $handle = (string) $handle;
    $problem = ws_mcp_alias_shape_problem($handle);

    if ($problem !== '') {
        return $problem;
    }

    $lower = function ($list) {
        return array_map(function ($item) {
            return mb_strtolower((string) $item);
        }, (array) $list);
    };

    if (in_array($handle, $lower($taken), true)) {
        return lang('Another agent already has that handle. Choose another.');
    }

    if (in_array($handle, $lower($user_names), true)) {
        return lang('That handle is a person\'s user name. Choose another, so @ means one thing.');
    }

    return '';
}

/**
 * What is wrong with an agent's door: '' when nothing is. A push door needs
 * an https address the site can sign its knock for; a cloud session's own
 * inbound webhook is refused, since only Anthropic can sign a delivery to
 * it. A poll door has no address. Pure.
 *
 * @param string $kind push | poll
 * @param string $url  the push address, '' for a poll door
 * @return string
 */
function ws_agent_door_problem($kind, $url)
{
    $url = trim((string) $url);

    if ((string) $kind === 'poll') {
        return ($url === '') ? '' : lang('A poll door has no address: the agent reads its requests itself.');
    }

    if ((string) $kind !== 'push') {
        return lang('A door is push or poll.');
    }

    $parts = parse_url($url);

    if (!is_array($parts) || (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') || ((string) ($parts['host'] ?? '') === '')) {
        return lang('A push door needs an https address.');
    }

    if (strpos((string) ($parts['path'] ?? ''), '/webhook-triggers/') !== false) {
        return lang('A cloud session\'s own webhook cannot be knocked by the site: use a routine\'s fire address or a webhook receiver that checks the site\'s signature.');
    }

    return '';
}

/** How many agent messages may answer one another in a row before the site stops waking agents in that chain. */
define('WS_AGENT_DEPTH_MAX', 6);

/** How many times one agent may be woken in an hour. */
define('WS_AGENT_HOURLY_MAX', 30);

/**
 * Are the registry tables there? Files can land before the upgrade runs
 * (11.0.172): until then Claude's routine is read from config.
 *
 * @return bool
 */
function ws_agents_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_agents', 'door_secret_sealed') && pg_schema_has('ws_channel_agents', 'loop_guard');
    }

    return $ready;
}

/**
 * The wake rules a channel-agent row takes: a mention of its handle, every
 * message, a message that starts with its prefix, or replies to its own
 * messages.
 *
 * @return string[]
 */
function ws_agent_wake_rules()
{
    return array('mention', 'all', 'prefix', 'replies');
}

/**
 * An agent's row as the code reads it: numbers as numbers, the sealed values
 * replaced by whether they are set. Pure.
 *
 * @param array $row a ws_agents row
 * @return array
 */
function ws_agent_shape($row)
{
    $row = (array) $row;
    $int = array('id', 'app_id', 'door_failures', 'door_last_knock', 'wake_every', 'wake_next_at', 'created_by', 'created_at', 'updated_at');
    $out = array();

    foreach (array('id', 'kind', 'handle', 'name', 'avatar', 'app_id', 'provider', 'model', 'status', 'door_kind', 'door_url',
        'door_failures', 'door_last_knock', 'door_error', 'wake_every', 'wake_next_at', 'wake_note', 'created_by', 'created_at', 'updated_at') as $field) {
        $out[$field] = in_array($field, $int, true) ? (int) ($row[$field] ?? 0) : (string) ($row[$field] ?? '');
    }

    $out['key_set'] = (strpos((string) ($row['key_sealed'] ?? ''), ':') !== false);
    $out['door_secret_set'] = (strpos((string) ($row['door_secret_sealed'] ?? ''), ':') !== false);

    return $out;
}

/**
 * The registered agents, by handle.
 *
 * @return array[] shaped by ws_agent_shape()
 */
function ws_agents_list()
{
    if (!ws_agents_ready()) {
        return array();
    }

    return array_map('ws_agent_shape', (array) db_items("SELECT * FROM ws_agents ORDER BY handle"));
}

/**
 * One agent, or null.
 *
 * @param int $id
 * @return array|null shaped by ws_agent_shape()
 */
function ws_agent($id)
{
    if (!ws_agents_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE id = '" . (int) $id . "'");

    return $row ? ws_agent_shape($row) : null;
}

/**
 * Claude's row: the routine as the first push agent. Null before the upgrade
 * or while no routine was ever set.
 *
 * @return array|null the raw row, its sealed token included
 */
function ws_agent_claude_row()
{
    if (!ws_agents_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE kind = 'claude' ORDER BY id LIMIT 1");

    return $row ? (array) $row : null;
}

/**
 * Writes Claude's routine into its row, making the row the first time.
 *
 * @param string      $url    the routine's fire address
 * @param string|null $sealed the sealed token (cipher:iv), '' to remove it, null to keep it
 */
function ws_agent_claude_save($url, $sealed)
{
    $now = time();
    $row = ws_agent_claude_row();
    $secret = ($sealed === null) ? null : (($sealed === '') ? 'NULL' : "'" . e($sealed) . "'");

    if ($row) {
        db("UPDATE ws_agents SET door_url = '" . e(substr((string) $url, 0, 500)) . "', door_kind = 'push'"
            . (($secret === null) ? '' : ", door_secret_sealed = " . $secret)
            . ", door_failures = 0, door_error = '', updated_at = '" . $now . "' WHERE id = '" . (int) $row['id'] . "'");
        return;
    }

    db("INSERT INTO ws_agents (kind, handle, name, status, door_kind, door_url, door_secret_sealed, created_by, created_at, updated_at)
        VALUES ('claude', 'claude', 'Claude', 'active', 'push', '" . e(substr((string) $url, 0, 500)) . "', " . (($secret === null) ? 'NULL' : $secret) . ",
        '" . (int) ($_SESSION['sessionuserid'] ?? 0) . "', '" . $now . "', '" . $now . "')");
}

/**
 * Seals a secret the way the site seals the others (cipher:iv), '' when
 * encryption is not available here.
 *
 * @param string $plain
 * @return string
 */
function ws_agent_seal($plain)
{
    if (((string) $plain === '') || !function_exists('encrypt_string_with_iv') || !defined('ENCRYPTION_KEY') || (ENCRYPTION_KEY === '')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv((string) $plain);

    return $cipher . ':' . $iv;
}

/**
 * Opens a sealed secret, '' when there is none or it cannot be read back (a
 * site restored with another ENCRYPTION_KEY).
 *
 * @param string|null $stored
 * @return string
 */
function ws_agent_unseal($stored)
{
    $stored = (string) $stored;

    if (($stored === '') || (strpos($stored, ':') === false) || !function_exists('decode_ssl_keys') || !defined('ENCRYPTION_KEY')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $stored, 2);

    return (string) decode_ssl_keys($cipher, $iv);
}

/**
 * The handles agents hold, lower case, for ws_agent_handle_problem() and an
 * MCP server's alias check.
 *
 * @param int $except_id an agent whose own handle is left out
 * @return string[]
 */
function ws_agent_handles($except_id = 0)
{
    if (!ws_agents_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT handle FROM ws_agents WHERE id <> '" . (int) $except_id . "'") as $row) {
        $out[] = mb_strtolower((string) $row['handle']);
    }

    return $out;
}

/**
 * A channel-agent row's settings checked and filled from the current row or
 * the defaults. Raising the tier above 1, or changing it, is the channel
 * manager's: anybody else keeps the tier the row has. Pure.
 *
 * @param array      $input       wake_rule, wake_prefix, tier, loop_guard (any may be missing)
 * @param array|null $current     the row there is, null for a new one
 * @param bool       $can_manage  may the caller manage the channel
 * @return array ok, error, field, values (wake_rule, wake_prefix, tier, loop_guard)
 */
function ws_channel_agent_check($input, $current, $can_manage)
{
    $input = (array) $input;
    $values = array(
        'wake_rule'   => (string) ($current['wake_rule'] ?? 'mention'),
        'wake_prefix' => (string) ($current['wake_prefix'] ?? ''),
        'tier'        => (int) ($current['tier'] ?? 1),
        'loop_guard'  => (int) ($current['loop_guard'] ?? 1),
    );
    $fail = function ($error, $field) use ($values) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => $values);
    };

    if (array_key_exists('wake_rule', $input) && ($input['wake_rule'] !== null)) {
        $values['wake_rule'] = (string) $input['wake_rule'];
    }

    if (array_key_exists('wake_prefix', $input) && ($input['wake_prefix'] !== null)) {
        $values['wake_prefix'] = trim((string) $input['wake_prefix']);
    }

    if (!in_array($values['wake_rule'], ws_agent_wake_rules(), true)) {
        return $fail('wake_rule_invalid', 'wake_rule');
    }

    if ($values['wake_rule'] !== 'prefix') {
        $values['wake_prefix'] = '';
    } elseif (($values['wake_prefix'] === '') || (mb_strlen($values['wake_prefix']) > 32)) {
        return $fail('wake_prefix_invalid', 'wake_prefix');
    }

    if (array_key_exists('tier', $input) && ($input['tier'] !== null)) {
        $tier = (int) $input['tier'];

        if (($tier < 1) || ($tier > 3)) {
            return $fail('tier_invalid', 'tier');
        }

        if (($tier !== $values['tier']) && !$can_manage) {
            return $fail('tier_forbidden', 'tier');
        }

        $values['tier'] = $tier;
    }

    if (array_key_exists('loop_guard', $input) && ($input['loop_guard'] !== null)) {
        $values['loop_guard'] = empty($input['loop_guard']) ? 0 : 1;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values);
}

/**
 * The words for a refusal of ws_channel_agent_check() and the routes.
 *
 * @param string $error
 * @return string
 */
function ws_channel_agent_error_text($error)
{
    switch ((string) $error) {
        case 'claude_not_channel_agent':
            return lang('Claude is not added to a channel: it answers where the channel\'s Claude access allows.');
        case 'wake_rule_invalid':
            return lang('An agent is woken by a mention, every message, a prefix or replies to it.');
        case 'wake_prefix_invalid':
            return lang('A prefix wake needs a prefix of up to 32 characters.');
        case 'tier_invalid':
            return lang('The tier is 1, 2 or 3.');
        case 'tier_forbidden':
            return lang('Only the owner of the channel can change an agent\'s tier.');
        case 'member_only':
            return lang('Only a member of a private channel can add an agent to it.');
    }

    return lang('Invalid request.');
}

/**
 * Whether an agent of this kind can be a channel's agent. Claude is not one:
 * it is asked where the channel's claude_access allows, and no reader takes
 * a channel-agent row of kind claude (the tiers read app rows only). Pure.
 *
 * @param string $kind ws_agents.kind
 * @return string '' or 'claude_not_channel_agent'
 */
function ws_channel_agent_kind_problem($kind)
{
    return ((string) $kind === 'claude') ? 'claude_not_channel_agent' : '';
}

/**
 * The channel-agent rows that are read: a claude row made before Claude was
 * left out of the channels stays in the table, unread, until it is taken
 * out. Pure.
 *
 * @param array[] $rows ws_channel_agent_shape() rows
 * @return array[]
 */
function ws_channel_agents_readable($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        if (ws_channel_agent_kind_problem($row['agent']['kind'] ?? '') === '') {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * The agents of a channel: its channel-agent rows with the agent beside each,
 * a claude row left out (ws_channel_agents_readable()).
 *
 * @param int $channel_id
 * @return array[] agent (ws_agent_shape()), allowed, wake_rule, wake_prefix, tier, loop_guard, added_by, added_at
 */
function ws_channel_agents($channel_id)
{
    if (!ws_agents_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_channel_agents WHERE channel_id = '" . (int) $channel_id . "' ORDER BY added_at, agent_id");
    $agents = array();
    $out = array();

    if (!empty($rows)) {
        foreach ((array) db_items("SELECT * FROM ws_agents WHERE id IN (" . implode(',', array_map(function ($row) {
            return (int) $row['agent_id'];
        }, $rows)) . ")") as $agent) {
            $agents[(int) $agent['id']] = ws_agent_shape($agent);
        }
    }

    foreach ($rows as $row) {
        if (isset($agents[(int) $row['agent_id']])) {
            $out[] = ws_channel_agent_shape($row, $agents[(int) $row['agent_id']]);
        }
    }

    return ws_channel_agents_readable($out);
}

/**
 * One channel-agent row as read. Pure.
 *
 * @param array $row
 * @param array $agent
 * @return array
 */
function ws_channel_agent_shape($row, $agent)
{
    return array(
        'agent'       => $agent,
        'allowed'     => ((int) ($row['allowed'] ?? 1) === 1),
        'wake_rule'   => (string) ($row['wake_rule'] ?? 'mention'),
        'wake_prefix' => (string) ($row['wake_prefix'] ?? ''),
        'tier'        => (int) ($row['tier'] ?? 1),
        'loop_guard'  => ((int) ($row['loop_guard'] ?? 1) === 1),
        'added_by'    => (int) ($row['added_by'] ?? 0),
        'added_at'    => (int) ($row['added_at'] ?? 0),
    );
}

/**
 * An agent's row in a channel, or null when it has none.
 *
 * @param int $channel_id
 * @param int $agent_id
 * @return array|null the raw row
 */
function ws_channel_agent_row($channel_id, $agent_id)
{
    if (!ws_agents_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_channel_agents WHERE channel_id = '" . (int) $channel_id . "' AND agent_id = '" . (int) $agent_id . "'");

    return $row ? (array) $row : null;
}

/**
 * Who may add an agent to a channel, or change its row there: whoever may
 * post in it, and in a private channel only a member. Changing the tier is
 * checked apart (ws_channel_agent_check()).
 *
 * @param array $viewer
 * @param array $channel
 * @return string '' or the refusal code
 */
function ws_channel_agent_add_problem($viewer, $channel)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return 'forbidden';
    }

    if (($channel['kind'] !== 'public') && !ws_channel_membership($channel['id'], $viewer['id'])) {
        return 'member_only';
    }

    return '';
}

/**
 * Writes an agent's row in a channel, values checked by
 * ws_channel_agent_check().
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param int   $agent_id
 * @param array $values
 * @return bool true when the row is new
 */
function ws_channel_agent_save($viewer, $channel_id, $agent_id, $values)
{
    $sets = "allowed = 1, wake_rule = '" . e($values['wake_rule']) . "', wake_prefix = '" . e($values['wake_prefix']) . "',
        tier = '" . (int) $values['tier'] . "', loop_guard = '" . (int) $values['loop_guard'] . "'";

    if (ws_channel_agent_row($channel_id, $agent_id)) {
        db("UPDATE ws_channel_agents SET " . $sets . " WHERE channel_id = '" . (int) $channel_id . "' AND agent_id = '" . (int) $agent_id . "'");
        return false;
    }

    db("INSERT INTO ws_channel_agents SET channel_id = '" . (int) $channel_id . "', agent_id = '" . (int) $agent_id . "', " . $sets . ",
        added_by = '" . (int) $viewer['id'] . "', added_at = '" . time() . "'");

    return true;
}

/**
 * Takes an agent out of a channel.
 *
 * @param int $channel_id
 * @param int $agent_id
 * @return bool whether it was in it
 */
function ws_channel_agent_remove($channel_id, $agent_id)
{
    if (!ws_channel_agent_row($channel_id, $agent_id)) {
        return false;
    }

    db("DELETE FROM ws_channel_agents WHERE channel_id = '" . (int) $channel_id . "' AND agent_id = '" . (int) $agent_id . "'");

    return true;
}

/**
 * How many messages in a row, counted back from the newest of a reply chain,
 * agents wrote. A person's message ends the count. Pure.
 *
 * @param array[] $chain the chain's messages, newest first, each with by_agent (bool)
 * @return int
 */
function ws_agent_chain_depth($chain)
{
    $depth = 0;

    foreach ((array) $chain as $message) {
        if (empty($message['by_agent'])) {
            break;
        }

        $depth++;
    }

    return $depth;
}

/**
 * The brake that stops waking an agent: the chain of agent messages it would
 * answer is too deep, or the agent was woken too often this hour. A prompt
 * is no security layer: these hold whatever the brief says, for every tier.
 * Pure.
 *
 * @param int $depth     ws_agent_chain_depth() of the chain it would answer
 * @param int $last_hour times the agent was woken in the last hour
 * @return string '' | 'depth' | 'hourly'
 */
function ws_agent_brake($depth, $last_hour)
{
    if ((int) $depth >= WS_AGENT_DEPTH_MAX) {
        return 'depth';
    }

    if ((int) $last_hour >= WS_AGENT_HOURLY_MAX) {
        return 'hourly';
    }

    return '';
}

/**
 * An agent's fields from the card checked and filled from its current row.
 * Claude's row keeps its kind, handle and door (the Claude card writes its
 * routine); an `app` agent works through an application and has a door, a
 * `model` agent is run by the site with a provider's key and has none.
 * Secrets are written apart. Pure: what other agents, MCP servers and people
 * hold is read by the caller.
 *
 * @param array      $input      kind, handle, name, app_id, provider, model, status, door_kind, door_url, wake_every, wake_note
 * @param array|null $current    ws_agent_shape() of the agent, null for a new one
 * @param string[]   $taken      handles other agents and MCP servers hold
 * @param string[]   $user_names the people's user names
 * @param int[]      $app_ids    the applications an agent may work through
 * @return array ok, error (a sentence), field, values
 */
function ws_agent_save_check($input, $current, $taken, $user_names, $app_ids)
{
    $input = (array) $input;
    $claude = $current && ($current['kind'] === 'claude');
    $values = array(
        'kind'       => $current ? $current['kind'] : (string) ($input['kind'] ?? ''),
        'handle'     => $claude ? 'claude' : mb_strtolower(ltrim(trim((string) ($input['handle'] ?? '')), '@')),
        'name'       => trim((string) ($input['name'] ?? '')),
        'app_id'     => (int) ($input['app_id'] ?? 0),
        'provider'   => trim((string) ($input['provider'] ?? '')),
        'model'      => trim((string) ($input['model'] ?? '')),
        'status'     => (string) ($input['status'] ?? ($current ? $current['status'] : 'active')),
        'door_kind'  => (string) ($input['door_kind'] ?? 'poll'),
        'door_url'   => trim((string) ($input['door_url'] ?? '')),
        'wake_every' => (int) ($input['wake_every'] ?? 0),
        'wake_note'  => trim((string) ($input['wake_note'] ?? '')),
    );
    $fail = function ($error, $field) use (&$values) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => $values);
    };

    if (!in_array($values['kind'], array('claude', 'app', 'model'), true) || (!$current && ($values['kind'] === 'claude'))) {
        return $fail(lang('An agent works through an application or runs on a model.'), 'kind');
    }

    if (!$claude) {
        $problem = ws_agent_handle_problem($values['handle'], $taken, $user_names);

        if ($problem !== '') {
            return $fail($problem, 'handle');
        }
    }

    if (($values['name'] === '') || (mb_strlen($values['name']) > 100)) {
        return $fail(lang('Give the agent a name of up to 100 characters.'), 'name');
    }

    if (($values['app_id'] !== 0) && !in_array($values['app_id'], array_map('intval', (array) $app_ids), true)) {
        return $fail(lang('That application could not be found.'), 'app_id');
    }

    if (($values['kind'] === 'app') && ($values['app_id'] === 0)) {
        return $fail(lang('Choose the application the agent works through.'), 'app_id');
    }

    if ($values['kind'] === 'model') {
        $providers = function_exists('ws_model_providers') ? ws_model_providers() : array();

        if (!isset($providers[$values['provider']])) {
            return $fail(lang('Choose the service that answers.'), 'provider');
        }

        if (($values['model'] === '') || (mb_strlen($values['model']) > 100)) {
            return $fail(lang('A model agent needs its provider and model.'), 'model');
        }
    } else {
        $values['provider'] = '';
        $values['model'] = '';
    }

    if (!in_array($values['status'], array('active', 'paused', 'off'), true)) {
        return $fail(lang('Invalid request.'), 'status');
    }

    if ($claude) {
        $values['door_kind'] = 'push';
        $values['door_url'] = $current['door_url'];
    } elseif ($values['kind'] === 'model') {
        // The site is a model agent's door; door_url keeps the chat API's
        // address where the provider has no fixed one (Azure, a custom server).
        $problem = ws_model_base_problem($values['provider'], $values['door_url']);

        if ($problem !== '') {
            return $fail($problem, 'door_url');
        }

        $values['door_kind'] = 'poll';
        $values['door_url'] = ($providers[$values['provider']]['base'] === '') ? $values['door_url'] : '';
    } else {
        $problem = ws_agent_door_problem($values['door_kind'], $values['door_url']);

        if ($problem !== '') {
            return $fail($problem, 'door_url');
        }

        if (mb_strlen($values['door_url']) > 500) {
            return $fail(lang('A push door needs an https address.'), 'door_url');
        }
    }

    if (($values['wake_every'] < 0) || ($values['wake_every'] > 1440)) {
        return $fail(lang('Wake it every 1 to 1440 minutes, or 0 for never.'), 'wake_every');
    }

    if (mb_strlen($values['wake_note']) > 250) {
        return $fail(lang('The wake note is at most 250 characters.'), 'wake_note');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => $values);
}

/**
 * Writes an agent from values ws_agent_save_check() passed. A sealed secret
 * is written when given ('' removes it, null keeps it). A schedule changed
 * starts again from now.
 *
 * @param array       $viewer
 * @param array       $values
 * @param array|null  $current ws_agent_shape(), null for a new agent
 * @param string|null $key_sealed
 * @param string|null $door_sealed
 * @return int the agent's id
 */
function ws_agent_save($viewer, $values, $current, $key_sealed = null, $door_sealed = null)
{
    $now = time();
    $sealed = function ($value) {
        return ($value === '') ? 'NULL' : "'" . e($value) . "'";
    };
    $sets = array();

    foreach (array('kind', 'handle', 'name', 'provider', 'model', 'status', 'door_kind', 'door_url', 'wake_note') as $field) {
        $sets[] = $field . " = '" . e((string) $values[$field]) . "'";
    }

    $sets[] = "app_id = '" . (int) $values['app_id'] . "'";
    $sets[] = "wake_every = '" . (int) $values['wake_every'] . "'";
    $sets[] = "updated_at = '" . $now . "'";

    if (!$current || ((int) $current['wake_every'] !== (int) $values['wake_every'])) {
        $sets[] = "wake_next_at = '" . (((int) $values['wake_every'] > 0) ? $now + 60 * (int) $values['wake_every'] : 0) . "'";
    }

    if ($key_sealed !== null) {
        $sets[] = "key_sealed = " . $sealed($key_sealed);
    }

    if ($door_sealed !== null) {
        $sets[] = "door_secret_sealed = " . $sealed($door_sealed);
    }

    if ($current) {
        db("UPDATE ws_agents SET " . implode(', ', $sets) . " WHERE id = '" . (int) $current['id'] . "'");

        return (int) $current['id'];
    }

    db("INSERT INTO ws_agents SET " . implode(', ', $sets) . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes an agent and its rows in the channels. Claude's row is kept: its
 * card switches it off.
 *
 * @param array $agent ws_agent_shape()
 * @return bool
 */
function ws_agent_delete($agent)
{
    if ($agent['kind'] === 'claude') {
        return false;
    }

    db("DELETE FROM ws_channel_agents WHERE agent_id = '" . (int) $agent['id'] . "'");
    db("DELETE FROM ws_agents WHERE id = '" . (int) $agent['id'] . "'");

    return true;
}
