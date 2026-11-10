<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents card of the Workspace settings: the agents of the
 * registry (includes/workspace/agents.php) with their door and schedule,
 * added, changed, paused and deleted by an administrator. Claude's row is
 * listed here too; its routine is written on the Claude card.
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
 * The applications an agent may work through: every one not revoked.
 *
 * @return array[] id, name, status
 */
function ws_agents_settings_apps()
{
    return (array) db_items("SELECT id, name, status FROM api_apps WHERE status <> 'revoked' ORDER BY name LIMIT 200");
}

/**
 * Saves, deletes or tries an agent from the card. Administrators only.
 *
 * @param array    $viewer
 * @param string   $action agent_save | agent_delete | agent_test
 * @param liveform $liveform
 */
function ws_agents_settings_post($viewer, $action, $liveform)
{
    if (!ws_agents_ready() || ((int) $viewer['role'] !== 0)) {
        $liveform->add_error(lang('Only an administrator can change the agents.'));
        return;
    }

    $id = (int) ($_POST['agent_id'] ?? 0);
    $current = ($id > 0) ? ws_agent($id) : null;

    if (($id > 0) && !$current) {
        $liveform->add_error(lang('That agent could not be found.'));
        return;
    }

    if ($action === 'agent_delete') {
        if ($current && ws_agent_delete($current)) {
            log_activity(lang(array('string' => 'the agent @{var:1} was deleted', 'vars' => $current['handle'])), $_SESSION['sessionusername'] ?? '');
            $liveform->add_notice(lang('The agent was deleted.'));
        }

        return;
    }

    // Try: one short answer from a model agent's model (model_agents.php).
    if ($action === 'agent_test') {
        if ($current && ($current['kind'] === 'model')) {
            $result = ws_model_agent_test($current['id']);
            $result['ok'] ? $liveform->add_notice(h($result['message'])) : $liveform->add_error(h($result['message']));
        }

        return;
    }

    $taken = ws_agent_handles($id);

    foreach (ws_mcp_servers() as $server) {
        $taken[] = mb_strtolower((string) $server['alias']);
    }

    $user_names = array();

    foreach ((array) db_items("SELECT user_username FROM user") as $row) {
        $user_names[] = (string) $row['user_username'];
    }

    $input = array();

    foreach (array('kind', 'handle', 'name', 'app_id', 'provider', 'model', 'status', 'door_kind', 'door_url', 'wake_every', 'wake_note') as $field) {
        $input[$field] = $_POST['agent_' . $field] ?? null;
    }

    // A model agent's address has its own field, apart from a push door's.
    if ((string) ($current ? $current['kind'] : ($input['kind'] ?? '')) === 'model') {
        $input['door_url'] = $_POST['agent_base'] ?? '';
    }

    $check = ws_agent_save_check($input, $current, $taken, $user_names, array_column(ws_agents_settings_apps(), 'id'));

    if (!$check['ok']) {
        $liveform->add_error(h($check['error']));
        return;
    }

    // Secrets are written, never shown: left empty, the stored one stays.
    $secret = function ($field) use ($liveform) {
        $plain = trim((string) ($_POST['agent_' . $field] ?? ''));

        if ($plain === '') {
            return empty($_POST['agent_' . $field . '_clear']) ? null : '';
        }

        $sealed = ws_agent_seal($plain);

        return ($sealed === '') ? false : $sealed;
    };
    $key = ($check['values']['kind'] === 'model') ? $secret('key') : null;
    $door = (($check['values']['kind'] === 'app') && ($check['values']['door_kind'] === 'push')) ? $secret('door_secret') : null;

    if (($key === false) || ($door === false)) {
        $liveform->add_error(lang('The token cannot be stored: encryption is not available on this site.'));
        return;
    }

    $providers = ws_model_providers();

    if (($check['values']['kind'] === 'model') && $providers[$check['values']['provider']]['key']
        && (($key === '') || (($key === null) && !($current && $current['key_set'])))) {
        $liveform->add_error(lang('Paste the service\'s API key.'));
        return;
    }

    ws_agent_save($viewer, $check['values'], $current, $key, $door);

    log_activity(lang(array('string' => 'the agent @{var:1} was saved', 'vars' => $check['values']['handle'])), $_SESSION['sessionusername'] ?? '');
    $liveform->add_notice(lang('The agent was saved.'));
}

/**
 * One agent's form: a new agent's when $agent is null.
 *
 * @param string     $self_url
 * @param array|null $agent ws_agent_shape()
 * @param array[]    $apps
 * @param bool       $admin
 * @return string
 */
function ws_agents_settings_form($self_url, $agent, $apps, $admin)
{
    $id = $agent ? (int) $agent['id'] : 0;
    $p = 'ws-agent-' . $id . '-';
    $claude = $agent && ($agent['kind'] === 'claude');
    $kind = $agent ? $agent['kind'] : 'app';
    $disabled = $admin ? '' : ' disabled';
    $value = function ($field, $default = '') use ($agent) {
        return h((string) ($agent ? $agent[$field] : $default));
    };
    $select = function ($name, $options, $chosen, $extra = '') use ($p, $disabled) {
        $out = '<select class="form-control form-control-sm" name="agent_' . $name . '" id="' . $p . $name . '"' . $disabled . $extra . '>';

        foreach ($options as $key => $label) {
            $out .= '<option value="' . h((string) $key) . '"' . (((string) $key === (string) $chosen) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        return $out . '</select>';
    };
    $field = function ($name, $label, $control, $help = '') use ($p) {
        return '<div class="md:col-6"><label class="form-label" for="' . $p . $name . '">' . h($label) . '</label>' . $control
            . (($help !== '') ? '<div class="form-text">' . h($help) . '</div>' : '') . '</div>';
    };
    $input = function ($name, $current, $extra = '') use ($p, $disabled) {
        return '<input class="form-control form-control-sm" name="agent_' . $name . '" id="' . $p . $name . '" value="' . $current . '"' . $extra . $disabled . '>';
    };
    $secret = function ($name, $set) use ($p, $disabled) {
        return '<input class="form-control form-control-sm font-monospace" type="password" name="agent_' . $name . '" id="' . $p . $name . '" autocomplete="new-password" placeholder="'
            . h($set ? lang('Stored. Type a new one to replace it.') : '') . '"' . $disabled . '>'
            . ($set ? '<div class="form-field mt-2"><input class="check" type="checkbox" name="agent_' . $name . '_clear" value="1" id="' . $p . $name . '-clear"' . $disabled . '><label class="small" for="' . $p . $name . '-clear">' . h(lang('Remove the stored one')) . '</label></div>' : '');
    };

    $app_options = array(0 => lang('Choose an application'));

    foreach ($apps as $app) {
        $app_options[(int) $app['id']] = $app['name'] . (($app['status'] !== 'active') ? ' (' . lang('not active') . ')' : '');
    }

    $rows = '';

    if (!$agent) {
        $rows .= $field('kind', lang('Kind'), $select('kind', array('app' => lang('Works through an application'), 'model' => lang('Runs on a model')), 'app'));
    }

    $rows .= $field('handle', lang('Handle'), $input('handle', $value('handle'), ' maxlength="32" autocomplete="off"' . ($claude ? ' readonly' : '')), lang('Called with @ in the channels: 2 to 32 small letters, digits, - or _.'));
    $rows .= $field('name', lang('Name'), $input('name', $value('name'), ' maxlength="100"'));
    $rows .= $field('status', lang('Status'), $select('status', array('active' => lang('Active'), 'paused' => lang('Paused'), 'off' => lang('Off')), $agent ? $agent['status'] : 'active'));

    if (!$claude) {
        $rows .= $field('app_id', lang('Application'), $select('app_id', $app_options, $agent ? $agent['app_id'] : 0), lang('The application it reads and writes through, with that application\'s permissions.'));
    }

    if ($kind === 'model' || !$agent) {
        $providers = array();
        $hints = array();

        foreach (ws_model_providers() as $provider_id => $provider) {
            $providers[$provider_id] = ws_model_provider_name($provider_id);
            $hints[] = $providers[$provider_id] . ': ' . (($provider_id === 'azure') ? lang('your deployment name') : $provider['model_hint']);
        }

        $chosen = $agent ? $agent['provider'] : 'openai';
        $terms = ($agent && ws_model_provider_terms($chosen) !== '') ? lang(ws_model_provider_terms($chosen)) : lang('For an agent that runs on a model.');

        $rows .= $field('provider', lang('Provider'), $select('provider', $providers, $chosen), $terms);
        $rows .= $field('model', lang('Model'), $input('model', $value('model'), ' maxlength="100"'), implode(' · ', $hints));
        $rows .= $field('base', lang('Address'), $input('base', ($kind === 'model') ? $value('door_url') : '', ' type="url" maxlength="500" placeholder="https://your-resource.openai.azure.com/openai/v1"'), lang('Only for Azure OpenAI and a custom address: the chat API\'s address.'));
        $rows .= $field('key', lang('Provider key'), $secret('key', $agent && $agent['key_set']));
    }

    if (!$claude && ($kind === 'app')) {
        $rows .= $field('door_kind', lang('Door'), $select('door_kind', array('poll' => lang('Poll: it reads its requests itself'), 'push' => lang('Push: the site knocks at its address')), $agent ? $agent['door_kind'] : 'poll'));
        $rows .= $field('door_url', lang('Push address'), $input('door_url', $value('door_url'), ' type="url" maxlength="500" placeholder="https://…"'), lang('A push carries only ids and the site\'s address, never the brief.'));
        $rows .= $field('door_secret', lang('Push token'), $secret('door_secret', $agent && $agent['door_secret_set']));
    }

    $rows .= $field('wake_every', lang('Wake it every (minutes)'), $input('wake_every', $value('wake_every', '0'), ' type="number" min="0" max="1440"'), lang('0: only when it is called.'));
    $rows .= $field('wake_note', lang('Wake note'), $input('wake_note', $value('wake_note'), ' maxlength="250"'));

    $try = ($agent && ($kind === 'model') && $admin)
        ? '<button type="submit" name="ws_action" value="agent_test" class="btn btn-sm btn-outline theme-secondary">' . h(lang('Try')) . '</button>'
        : '';
    $delete = ($agent && !$claude && $admin)
        ? '<button type="submit" name="ws_action" value="agent_delete" class="btn btn-sm btn-text theme-danger" onclick="return confirm(' . h(json_encode(lang('Delete this agent? It leaves every channel it is in.'))) . ');">' . h(lang('Delete')) . '</button>'
        : '';

    return '
        <form method="post" action="' . h($self_url) . '#ws-agents" class="mt-3">
            ' . get_token_field() . '
            <input type="hidden" name="agent_id" value="' . $id . '">
            ' . ($claude ? '<p class="small fg-secondary">' . h(lang('Claude\'s routine, its address and token, is set on the Claude card.')) . '</p>' : '') . '
            <div class="row g-5">' . $rows . '</div>
            ' . ($admin ? '<div class="d-flex gap-3 mt-5"><button type="submit" name="ws_action" value="agent_save" class="btn btn-sm btn-solid theme-primary">' . h(lang('Save')) . '</button>' . $try . $delete . '</div>' : '') . '
        </form>';
}

/**
 * The agents card.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_agents_settings_card($self_url, $viewer)
{
    if (!ws_agents_ready()) {
        return '';
    }

    $admin = ((int) $viewer['role'] === 0);
    $apps = ws_agents_settings_apps();
    $status = array('active' => lang('Active'), 'paused' => lang('Paused'), 'off' => lang('Off'));
    $list = '';
    $agents = ws_agents_list();
    $counts = function_exists('ws_agent_activity_read_counts') ? ws_agent_activity_read_counts($agents) : array();

    foreach ($agents as $agent) {
        $door = ($agent['door_kind'] === 'push') ? lang('Push') : lang('Poll');
        $knock = ($agent['door_last_knock'] > 0) ? lang(array('string' => 'last {var:1}', 'vars' => date('d.m.Y H:i', $agent['door_last_knock']))) : '';
        $error = ($agent['door_error'] !== '') ? '<div class="small fg-danger">' . h($agent['door_error']) . '</div>' : '';
        $schedule = ($agent['wake_every'] > 0) ? ' · ' . h(lang(array('string' => 'every {var:1} min', 'vars' => $agent['wake_every']))) : '';

        $list .= '
            <details class="border-bottom py-3" id="ws-agent-' . (int) $agent['id'] . '">
                <summary class="d-flex align-items-center gap-3">
                    <span class="fw-semibold">@' . h($agent['handle']) . '</span>
                    <span class="ws-grow">' . h($agent['name']) . '</span>
                    <span class="small fg-secondary">' . h($door) . (($knock !== '') ? ', ' . h($knock) : '') . $schedule . '</span>
                    <span class="badge badge-subtle ' . (($agent['status'] === 'active') ? 'theme-success' : 'theme-secondary') . '">' . h($status[$agent['status']] ?? $agent['status']) . '</span>
                </summary>
                ' . $error . (function_exists('ws_agent_activity_block') ? ws_agent_activity_block($self_url, $viewer, $agent, $counts) : '') . ws_agents_settings_form($self_url, $agent, $apps, $admin) . '
            </details>';
    }

    if ($list === '') {
        $list = '<p class="small fg-secondary mb-0">' . h(lang('No agent yet. An agent is called by its handle in the channels it is added to.')) . '</p>';
    }

    // A key of its own: "Agents" is the real-estate template's estate agents,
    // worded "Danışmanlar" in Turkish.
    return '
        <div class="card mt-7" id="ws-agents">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-robot me-1" aria-hidden="true"></i>' . h(lang('Workspace agents')) . '</h2></div>
            <div class="card-body">
                <p class="small fg-secondary">' . h(lang('Agents act as members of the channels they are added to, each through its own application and within the tier the channel\'s manager allows.')) . '</p>
                ' . $list . '
                ' . ($admin ? '<details class="mt-5"><summary class="small fw-semibold">' . h(lang('Add an agent')) . '</summary>' . ws_agents_settings_form($self_url, null, $apps, $admin) . '</details>' : '') . '
            </div>
        </div>';
}
