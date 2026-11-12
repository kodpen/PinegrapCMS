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
 * An agent form's values, each a scalar string: the Workspace Settings form
 * posts them as agent_<field>, the Site Settings dialog as
 * ws_agent[<id>][<field>]; each caller hands the fields over by their bare
 * name.
 *
 * @param array $source field => value
 * @return array kind, handle, name, app_id, provider, model, status,
 *               door_kind, door_url, base, wake_every, wake_note, key,
 *               key_clear, door_secret, door_secret_clear, token_limit
 */
function ws_agents_settings_input($source)
{
    $input = array();

    foreach (array('kind', 'handle', 'name', 'app_id', 'provider', 'model', 'status', 'door_kind', 'door_url', 'base', 'wake_every', 'wake_note',
        'key', 'key_clear', 'door_secret', 'door_secret_clear', 'token_limit') as $field) {
        $value = is_array($source) ? ($source[$field] ?? null) : null;
        $input[$field] = is_scalar($value) ? (string) $value : '';
    }

    return $input;
}

/**
 * The Workspace Settings form's agent_<field> names, read as bare names.
 *
 * @param array $post $_POST
 * @return array ws_agents_settings_input()
 */
function ws_agents_settings_old_input($post)
{
    $source = array();

    foreach ((array) $post as $name => $value) {
        if (strpos((string) $name, 'agent_') === 0) {
            $source[substr((string) $name, 6)] = $value;
        }
    }

    return ws_agents_settings_input($source);
}

/**
 * Checks an agent's form before anything is written: the registry's rules
 * (ws_agent_save_check()), the secrets sealed, a model's key and its
 * monthly token limit. Administrators only.
 *
 * @param array $viewer
 * @param int   $id    0 for a new agent
 * @param array $input ws_agents_settings_input()
 * @return array ok, error (a sentence), field (the form's field), and what
 *               ws_agents_settings_write() needs: current, values, key, door, token_limit
 */
function ws_agents_settings_check($viewer, $id, $input)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    if (!ws_agents_ready() || ((int) $viewer['role'] !== 0)) {
        return $fail(lang('Only an administrator can change the agents.'));
    }

    $id = (int) $id;
    $current = ($id > 0) ? ws_agent($id) : null;

    if (($id > 0) && !$current) {
        return $fail(lang('That agent could not be found.'));
    }

    $taken = ws_agent_handles($id);

    foreach (ws_mcp_servers() as $server) {
        $taken[] = mb_strtolower((string) $server['alias']);
    }

    $user_names = array();

    foreach ((array) db_items("SELECT user_username FROM user") as $row) {
        $user_names[] = (string) $row['user_username'];
    }

    $fields = $input;

    // A model agent's address has its own field, apart from a push door's.
    if ((string) ($current ? $current['kind'] : $input['kind']) === 'model') {
        $fields['door_url'] = $input['base'];
    }

    $check = ws_agent_save_check($fields, $current, $taken, $user_names, array_column(ws_agents_settings_apps(), 'id'));

    if (!$check['ok']) {
        $field = (string) $check['field'];

        return $fail($check['error'], (($field === 'door_url') && ($check['values']['kind'] === 'model')) ? 'base' : $field);
    }

    // Secrets are written, never shown: left empty, the stored one stays.
    $secret = function ($field) use ($input) {
        $plain = trim($input[$field]);

        if ($plain === '') {
            return ($input[$field . '_clear'] === '' || $input[$field . '_clear'] === '0') ? null : '';
        }

        $sealed = ws_agent_seal($plain);

        return ($sealed === '') ? false : $sealed;
    };
    $key = ($check['values']['kind'] === 'model') ? $secret('key') : null;
    $door = (($check['values']['kind'] === 'app') && ($check['values']['door_kind'] === 'push')) ? $secret('door_secret') : null;

    if (($key === false) || ($door === false)) {
        return $fail(lang('The token cannot be stored: encryption is not available on this site.'), ($key === false) ? 'key' : 'door_secret');
    }

    $providers = ws_model_providers();

    if (($check['values']['kind'] === 'model') && $providers[$check['values']['provider']]['key']
        && (($key === '') || (($key === null) && !($current && $current['key_set'])))) {
        return $fail(lang('Paste the service\'s API key.'), 'key');
    }

    // The monthly token limit belongs to a model assistant alone.
    $token_limit = ($check['values']['kind'] === 'model') ? ws_agent_token_limit_check($input['token_limit']) : null;

    if ($token_limit && !$token_limit['ok']) {
        return $fail($token_limit['error'], 'token_limit');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'current' => $current, 'values' => $check['values'], 'key' => $key, 'door' => $door, 'token_limit' => $token_limit);
}

/**
 * Writes an agent ws_agents_settings_check() passed, with its Site Log line.
 *
 * @param array $viewer
 * @param array $checked ws_agents_settings_check(), ok
 * @return int the agent's id
 */
function ws_agents_settings_write($viewer, $checked)
{
    $saved_id = (int) ws_agent_save($viewer, $checked['values'], $checked['current'], $checked['key'], $checked['door']);

    if ($checked['token_limit'] && ($saved_id > 0)) {
        ws_agent_token_limit_save($saved_id, $checked['token_limit']['value']);
    }

    log_activity(lang(array('string' => 'the agent @{var:1} was saved', 'vars' => $checked['values']['handle'])), $_SESSION['sessionusername'] ?? '');

    return $saved_id;
}

/**
 * Deletes or tries an agent: a model agent's Try asks its model for one
 * short answer (model_agents.php).
 *
 * @param array    $viewer
 * @param string   $action   agent_delete | agent_test
 * @param int      $id
 * @param liveform $liveform
 * @param callable $refuse   takes a refusal's sentence
 */
function ws_agents_settings_act($viewer, $action, $id, $liveform, $refuse)
{
    if (!ws_agents_ready() || ((int) $viewer['role'] !== 0)) {
        $refuse(lang('Only an administrator can change the agents.'));
        return;
    }

    $current = ((int) $id > 0) ? ws_agent((int) $id) : null;

    if (!$current) {
        $refuse(lang('That agent could not be found.'));
        return;
    }

    if ($action === 'agent_delete') {
        if (ws_agent_delete($current)) {
            log_activity(lang(array('string' => 'the agent @{var:1} was deleted', 'vars' => $current['handle'])), $_SESSION['sessionusername'] ?? '');
            $liveform->add_notice(lang('The agent was deleted.'));
        }

        return;
    }

    if ($current['kind'] === 'model') {
        $result = ws_model_agent_test($current['id']);
        $result['ok'] ? $liveform->add_notice(h($result['message'])) : $refuse(h($result['message']));
    }
}

/**
 * Saves, deletes or tries an agent from the Workspace Settings card.
 * Administrators only.
 *
 * @param array    $viewer
 * @param string   $action agent_save | agent_delete | agent_test
 * @param liveform $liveform
 */
function ws_agents_settings_post($viewer, $action, $liveform)
{
    $id = (int) ($_POST['agent_id'] ?? 0);

    if ($action !== 'agent_save') {
        // A new agent's form has nothing to delete or try.
        if ($id > 0) {
            ws_agents_settings_act($viewer, $action, $id, $liveform, function ($sentence) use ($liveform) {
                $liveform->add_error($sentence);
            });
        }

        return;
    }

    $checked = ws_agents_settings_check($viewer, $id, ws_agents_settings_old_input($_POST));

    if (!$checked['ok']) {
        $liveform->add_error(h($checked['error']));
        return;
    }

    ws_agents_settings_write($viewer, $checked);
    $liveform->add_notice(lang('The agent was saved.'));
}

/**
 * One agent's form: a new agent's when $agent is null. Kind comes first and
 * the form shows only that kind's fields (an application's handle, name,
 * application and door, the push address and token only for a push door; a
 * model's handle, name, provider, model and key, the address only for a
 * provider without one of its own); status, the wake interval and note and
 * the monthly token limit sit under a closed Advanced part.
 *
 * On Workspace Settings it is a form of its own; in the Site Settings
 * dialog ($form, the settings form's id) its fields are named
 * ws_agent[<id>][<field>] and join that form by its id, with named submit
 * buttons ws_agent_save, ws_agent_test and ws_agent_delete (value: the id).
 *
 * @param string      $self_url
 * @param array|null  $agent ws_agent_shape()
 * @param array[]     $apps
 * @param bool        $admin
 * @param string|null $form
 * @return string
 */
function ws_agents_settings_form($self_url, $agent, $apps, $admin, $form = null)
{
    $id = $agent ? (int) $agent['id'] : 0;
    $p = 'ws-agent-' . $id . '-';
    $claude = $agent && ($agent['kind'] === 'claude');
    $kind = $agent ? $agent['kind'] : 'app';
    $dialog = ($form !== null);
    $disabled = ($admin ? '' : ' disabled') . ($dialog ? ' form="' . h($form) . '"' : '');
    $name = function ($field) use ($dialog, $id) {
        return $dialog ? 'ws_agent[' . $id . '][' . $field . ']' : 'agent_' . $field;
    };
    $value = function ($field, $default = '') use ($agent) {
        return h((string) ($agent ? $agent[$field] : $default));
    };
    $select = function ($field, $options, $chosen, $extra = '') use ($p, $disabled, $name) {
        $out = '<select class="form-control form-control-sm" name="' . h($name($field)) . '" id="' . $p . $field . '" data-ws-agent-field="' . $field . '"' . $disabled . $extra . '>';

        foreach ($options as $key => $label) {
            $out .= '<option value="' . h((string) $key) . '"' . (((string) $key === (string) $chosen) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        return $out . '</select>';
    };
    // $when: field=value[|value] of the field it hangs on; hidden while that
    // field holds another value.
    $field = function ($field, $label, $control, $help = '', $when = '', $shown = true) use ($p) {
        return '<div class="md:col-6' . ($shown ? '' : ' d-none') . '"' . (($when !== '') ? ' data-ws-agent-when="' . h($when) . '"' : '') . '><label class="form-label" for="' . $p . $field . '">' . h($label) . '</label>' . $control
            . (($help !== '') ? '<div class="form-text">' . h($help) . '</div>' : '') . '</div>';
    };
    $input = function ($field, $current, $extra = '') use ($p, $disabled, $name) {
        return '<input class="form-control form-control-sm" name="' . h($name($field)) . '" id="' . $p . $field . '" value="' . $current . '"' . $extra . $disabled . '>';
    };
    $secret = function ($field, $set) use ($p, $disabled, $name) {
        return '<input class="form-control form-control-sm font-monospace" type="password" name="' . h($name($field)) . '" id="' . $p . $field . '" autocomplete="new-password" placeholder="'
            . h($set ? lang('Stored. Type a new one to replace it.') : '') . '"' . $disabled . '>'
            . ($set ? '<div class="form-field mt-2"><input class="check" type="checkbox" name="' . h($name($field . '_clear')) . '" value="1" id="' . $p . $field . '-clear"' . $disabled . '><label class="small" for="' . $p . $field . '-clear">' . h(lang('Remove the stored one')) . '</label></div>' : '');
    };

    $app_options = array(0 => lang('Choose an application'));

    foreach ($apps as $app) {
        $app_options[(int) $app['id']] = $app['name'] . (($app['status'] !== 'active') ? ' (' . lang('not active') . ')' : '');
    }

    $rows = '';
    $advanced = '';

    // A new agent's kind is chosen first; an agent's kind never changes.
    $when_kind = function ($of) use ($agent) {
        return $agent ? '' : 'kind=' . $of;
    };

    if (!$agent) {
        $rows .= $field('kind', lang('Kind'), $select('kind', array('app' => lang('Works through an application'), 'model' => lang('Runs on a model')), 'app'));
    }

    $rows .= $field('handle', lang('Handle (@name)'), $input('handle', $value('handle'), ' maxlength="32" autocomplete="off"' . ($claude ? ' readonly' : '')), lang('Called with @ in the channels: 2 to 32 small letters, digits, - or _.'));
    $rows .= $field('name', lang('Name'), $input('name', $value('name'), ' maxlength="100"'));

    if (!$claude && ($kind === 'app')) {
        $push = $agent && ($agent['door_kind'] === 'push');

        $rows .= $field('app_id', lang('Application'), $select('app_id', $app_options, $agent ? $agent['app_id'] : 0), lang('The application it reads and writes through, with that application\'s permissions.'), $when_kind('app'));
        $rows .= $field('door_kind', lang('Door'), $select('door_kind', array('poll' => lang('Poll: it reads its requests itself'), 'push' => lang('Push: the site knocks at its address')), $agent ? $agent['door_kind'] : 'poll'), '', $when_kind('app'));
        $rows .= $field('door_url', lang('Push address'), $input('door_url', $value('door_url'), ' type="url" maxlength="500" placeholder="https://…"'), lang('A push carries only ids and the site\'s address, never the brief.'), 'door_kind=push', $push);
        $rows .= $field('door_secret', lang('Push token'), $secret('door_secret', $agent && $agent['door_secret_set']), '', 'door_kind=push', $push);
    }

    if ($kind === 'model' || !$agent) {
        $providers = array();
        $hints = array();
        $own_address = array();

        foreach (ws_model_providers() as $provider_id => $provider) {
            $providers[$provider_id] = ws_model_provider_name($provider_id);
            $hints[] = $providers[$provider_id] . ': ' . (!empty($provider['hint_lang']) ? lang($provider['model_hint']) : $provider['model_hint']);

            if ((string) $provider['base'] === '') {
                $own_address[] = $provider_id;
            }
        }

        $chosen = $agent ? $agent['provider'] : 'openai';
        $terms = ($agent && ws_model_provider_terms($chosen) !== '') ? lang(ws_model_provider_terms($chosen)) : lang('For an agent that runs on a model.');
        $shown = (bool) $agent;

        $rows .= $field('provider', lang('Provider'), $select('provider', $providers, $chosen), $terms, $when_kind('model'), $shown);
        $rows .= $field('model', lang('Model'), $input('model', $value('model'), ' maxlength="100"'), implode(' · ', $hints), $when_kind('model'), $shown);
        $rows .= $field('base', lang('Address'), $input('base', ($kind === 'model') ? $value('door_url') : '', ' type="url" maxlength="500" placeholder="https://your-resource.openai.azure.com/openai/v1"'), lang('Only for Azure OpenAI and a custom address: the chat API\'s address.'),
            'provider=' . implode('|', $own_address), in_array($chosen, $own_address, true));
        $rows .= $field('key', lang('Provider key'), $secret('key', $agent && $agent['key_set']), '', $when_kind('model'), $shown);

        if (ws_agent_token_limit_ready()) {
            $limit = $agent ? ws_agent_token_limit((int) $agent['id']) : 0;
            $advanced .= $field('token_limit', lang('Monthly token limit'), $input('token_limit', (string) $limit, ' type="number" min="0" max="4294967295" step="1"'), lang('Tokens in and out in a calendar month; once reached, its requests fail until the next month. 0: no limit.'), $when_kind('model'), $shown);
        }
    }

    $advanced = $field('status', lang('Status'), $select('status', array('active' => lang('Active'), 'paused' => lang('Paused'), 'off' => lang('Off')), $agent ? $agent['status'] : 'active'))
        . $field('wake_every', lang('Wake it every (minutes)'), $input('wake_every', $value('wake_every', '0'), ' type="number" min="0" max="1440"'), lang('0: only when it is called.'))
        . $field('wake_note', lang('Wake note'), $input('wake_note', $value('wake_note'), ' maxlength="250"'))
        . $advanced;

    // A card button: the old screen's form posts ws_action; the dialog's is
    // a named submit button of the settings form carrying the agent's id.
    $button = function ($action, $class, $label, $confirm = '') use ($dialog, $form, $id) {
        $ask = ($confirm !== '') ? ' onclick="return confirm(' . h(json_encode($confirm)) . ');"' : '';

        return $dialog
            ? '<button type="submit" name="ws_' . $action . '" value="' . $id . '" form="' . h($form) . '" class="' . $class . '"' . $ask . '>' . h($label) . '</button>'
            : '<button type="submit" name="ws_action" value="' . $action . '" class="' . $class . '"' . $ask . '>' . h($label) . '</button>';
    };

    $try = ($agent && ($kind === 'model') && $admin) ? $button('agent_test', 'btn btn-sm btn-outline theme-secondary', lang('Try')) : '';
    $delete = ($agent && !$claude && $admin) ? $button('agent_delete', 'btn btn-sm btn-text theme-danger', lang('Delete'), lang('Delete this agent? It leaves every channel it is in.')) : '';
    $save = $button('agent_save', 'btn btn-sm btn-solid theme-primary', $agent ? lang('Save') : lang('Add'));

    $body = '
            ' . ($claude ? '<p class="small fg-secondary">' . h(lang('Claude\'s routine, its address and token, is set on the Claude card.')) . '</p>' : '') . '
            <div class="row g-5">' . $rows . '</div>
            <details class="mt-5">
                <summary class="small fw-semibold">' . h(lang('Advanced')) . '</summary>
                <div class="row g-5 mt-1">' . $advanced . '</div>
            </details>
            ' . ($admin ? '<div class="d-flex gap-3 mt-5">' . $save . $try . $delete . '</div>' : '');

    if ($dialog) {
        return '<div class="ws-agent-form">' . $body . '</div>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-agents" class="ws-agent-form mt-3">
            ' . get_token_field() . '
            <input type="hidden" name="agent_id" value="' . $id . '">' . $body . '
        </form>';
}

/**
 * The script that shows an agent form's fields for the chosen kind, door and
 * provider (data-ws-agent-when="field=value|value"). Delegated to the
 * document: the dialog draws its forms again with every pane.
 *
 * @return string HTML
 */
function ws_agents_settings_script()
{
    return '
        <script>
        (function () {
            if (window.pgWsAgentFormBound) {
                return;
            }

            window.pgWsAgentFormBound = true;

            document.addEventListener(\'change\', function (event) {
                var changed = event.target.closest ? event.target.closest(\'[data-ws-agent-field]\') : null;
                var box = changed ? changed.closest(\'.ws-agent-form\') : null;

                if (!box) {
                    return;
                }

                var values = {};

                box.querySelectorAll(\'[data-ws-agent-field]\').forEach(function (field) {
                    values[field.getAttribute(\'data-ws-agent-field\')] = field.value;
                });

                box.querySelectorAll(\'[data-ws-agent-when]\').forEach(function (part) {
                    var rule = part.getAttribute(\'data-ws-agent-when\').split(\'=\');

                    part.classList.toggle(\'d-none\', rule[1].split(\'|\').indexOf(values[rule[0]]) === -1);
                });

                // A push door\'s fields hang on the door, which hangs on the kind.
                if (values.kind === \'model\') {
                    box.querySelectorAll(\'[data-ws-agent-when^="door_kind="]\').forEach(function (part) {
                        part.classList.add(\'d-none\');
                    });
                } else if (values.kind === \'app\') {
                    box.querySelectorAll(\'[data-ws-agent-when^="provider="]\').forEach(function (part) {
                        part.classList.add(\'d-none\');
                    });
                }
            });
        })();
        </script>';
}

/**
 * One agent's line on the card: handle, name, door, last knock, schedule,
 * status.
 *
 * @param array $agent ws_agent_shape()
 * @return string HTML, the inside of a summary or a row
 */
function ws_agents_settings_line_html($agent)
{
    $status = array('active' => lang('Active'), 'paused' => lang('Paused'), 'off' => lang('Off'));
    // Keys of their own: "Poll" is a vote and "Handle" a user name elsewhere.
    $door = ($agent['door_kind'] === 'push') ? lang('Push door') : lang('Poll door');
    $knock = ($agent['door_last_knock'] > 0) ? lang(array('string' => 'last {var:1}', 'vars' => date('d.m.Y H:i', $agent['door_last_knock']))) : '';
    $schedule = ($agent['wake_every'] > 0) ? ' · ' . h(lang(array('string' => 'every {var:1} min', 'vars' => $agent['wake_every']))) : '';

    return '
                    <span class="fw-semibold">@' . h($agent['handle']) . '</span>
                    <span class="ws-grow">' . h($agent['name']) . '</span>
                    <span class="small fg-secondary">' . h($door) . (($knock !== '') ? ', ' . h($knock) : '') . $schedule . '</span>
                    <span class="badge badge-subtle ' . (($agent['status'] === 'active') ? 'theme-success' : 'theme-secondary') . '">' . h($status[$agent['status']] ?? $agent['status']) . '</span>';
}

/**
 * The agents card.
 *
 * In the Site Settings dialog ($form, the settings form's id) each agent is
 * a line with an Edit button; its activity and its form open in a drawer
 * (ws_settings_agent_drawer()), as does Add an agent.
 *
 * @param string      $self_url
 * @param array       $viewer
 * @param string|null $form
 * @return string
 */
function ws_agents_settings_card($self_url, $viewer, $form = null)
{
    if (!ws_agents_ready()) {
        return '';
    }

    $admin = ((int) $viewer['role'] === 0);
    $apps = ws_agents_settings_apps();
    $list = '';
    $agents = ws_agents_list();
    $counts = function_exists('ws_agent_activity_read_counts') ? ws_agent_activity_read_counts($agents) : array();
    $intro = h(lang('Agents act as members of the channels they are added to, each through its own application and within the tier the channel\'s manager allows.'));

    foreach ($agents as $agent) {
        $error = ($agent['door_error'] !== '') ? '<div class="small fg-danger">' . h($agent['door_error']) . '</div>' : '';

        if ($form !== null) {
            $drawer = 'pgset-ws-agent-' . (int) $agent['id'];
            $list .= '
            <div class="d-flex flex-wrap align-items-center gap-3 border-bottom py-3">
                ' . ws_agents_settings_line_html($agent) . '
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="drawer" data-bs-target="#' . $drawer . '" aria-controls="' . $drawer . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), '@' . $agent['handle'])))) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
                ' . (($error !== '') ? '<div class="w-100">' . $error . '</div>' : '') . '
            </div>';
            continue;
        }

        $list .= '
            <details class="border-bottom py-3" id="ws-agent-' . (int) $agent['id'] . '">
                <summary class="d-flex align-items-center gap-3">' . ws_agents_settings_line_html($agent) . '
                </summary>
                ' . $error . (function_exists('ws_agent_activity_block') ? ws_agent_activity_block($self_url, $viewer, $agent, $counts) : '') . ws_agents_settings_form($self_url, $agent, $apps, $admin) . '
            </details>';
    }

    if ($list === '') {
        $list = '<p class="small fg-secondary mb-0">' . h(lang('No agent yet. An agent is called by its handle in the channels it is added to.')) . '</p>';
    }

    // A key of its own: "Agents" is the real-estate template's estate agents,
    // worded "Danışmanlar" in Turkish.
    if ($form !== null) {
        return '
    <div id="pgset-ws-agents" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0 ws-grow">' . h(lang('Workspace agents')) . '</span>
                ' . ($admin ? '<button type="button" class="btn btn-sm btn-outline theme-primary rounded-pill" data-bs-toggle="drawer" data-bs-target="#pgset-ws-agent-0" aria-controls="pgset-ws-agent-0"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add an agent')) . '</button>' : '') . '
            </div>
            <div class="card-body">
                <p class="small fg-secondary">' . $intro . '</p>
                ' . $list . '
            </div>
        </div>
    </div>' . ws_agents_settings_script();
    }

    return '
        <div class="card mt-7" id="ws-agents">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-robot me-1" aria-hidden="true"></i>' . h(lang('Workspace agents')) . '</h2></div>
            <div class="card-body">
                <p class="small fg-secondary">' . $intro . '</p>
                ' . $list . '
                ' . ($admin ? '<details class="mt-5"><summary class="small fw-semibold">' . h(lang('Add an agent')) . '</summary>' . ws_agents_settings_form($self_url, null, $apps, $admin) . '</details>' : '') . '
            </div>
        </div>' . ws_agents_settings_script();
}

/**
 * A Site Settings drawer of the agents card: an agent's (its door error,
 * its activity with the request rows' buttons, its form) or, with $agent
 * null, Add an agent's.
 *
 * @param array|null $agent     ws_agent_shape()
 * @param array      $viewer
 * @param array[]    $apps      ws_agents_settings_apps()
 * @param array      $counts    ws_agent_activity_read_counts()
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog pg_settings_drawer()'s
 * @return string HTML
 */
function ws_settings_agent_drawer($agent, $viewer, $apps, $counts, $form, $in_dialog)
{
    $admin = ((int) $viewer['role'] === 0);

    if (!$agent) {
        return pg_settings_drawer('pgset-ws-agent-0', h(lang('Add an agent')), ws_agents_settings_form('', null, $apps, $admin, $form), $in_dialog);
    }

    $error = ($agent['door_error'] !== '') ? '<div class="alert theme-danger small py-3">' . h($agent['door_error']) . '</div>' : '';

    return pg_settings_drawer('pgset-ws-agent-' . (int) $agent['id'], '@' . h($agent['handle']) . ' · ' . h($agent['name']),
        $error . (function_exists('ws_agent_activity_block') ? '<div class="mb-5">' . ws_agent_activity_block('', $viewer, $agent, $counts, $form) . '</div>' : '') . ws_agents_settings_form('', $agent, $apps, $admin, $form), $in_dialog);
}
