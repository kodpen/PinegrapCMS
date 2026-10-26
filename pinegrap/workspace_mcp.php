<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - my MCP connections: the other services' MCP servers of the
 * Workspace as the signed-in person meets them. On a server people sign in
 * to, their own account: whether it is connected and works, and the buttons
 * that connect it, connect it again or end it. On every server, the tools
 * that read, each run here with a form drawn from its input schema, with no
 * need for Pinegrap AI; the answer is shown here and, when a channel is
 * chosen, posted there. The rules are in includes/workspace/mcp_form.php.
 *
 * Every person sees and ends only their own connections. Servers are added
 * and their tools turned on in Workspace Settings › MCP servers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_mcp_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('workspace_mcp');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_mcp.php';

$servers = ws_mcp_active_servers();

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $action = (string) ($_POST['mcp_action'] ?? '');
    $server = $servers[(int) ($_POST['mcp_id'] ?? 0)] ?? null;
    $back = $self_url;

    if (!$server) {
        $liveform->add_error(lang('That MCP server could not be found.'));
        go($back);
    }

    $anchor = '#ws-mcp-' . (int) $server['id'];

    switch ($action) {

        case 'connect':
            $start = ws_mcp_person_connect_start($viewer, $server, 'workspace_mcp.php?mcp_connected=' . (int) $server['id'] . $anchor);

            if ($start['ok']) {
                header('Location: ' . $start['url']);
                exit();
            }

            $liveform->add_error($start['error']);
            $back .= $anchor;
            break;

        case 'disconnect':
            if (ws_mcp_person_disconnect($viewer, $server)) {
                $liveform->add_notice(lang(array('string' => 'Your own account is disconnected from @{var:1}.', 'vars' => $server['alias'])));
            }

            $back .= $anchor;
            break;

        case 'run':
            $tools = ws_mcp_form_tools($server);
            $name = (string) ($_POST['mcp_tool'] ?? '');
            $tool = $tools[$name] ?? null;

            if (!$tool) {
                $liveform->add_error(lang('That tool cannot be run with a form.'));
                $back .= $anchor;
                break;
            }

            $channel_id = (int) ($_POST['mcp_channel'] ?? 0);
            $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;
            $back = $self_url . ws_mcp_form_query((int) $server['id'], $name, $channel_id);

            $input = (isset($_POST['mcp_arg']) && is_array($_POST['mcp_arg'])) ? $_POST['mcp_arg'] : array();
            $fields = ws_mcp_form_fields($tool['schema']);
            $parsed = ws_mcp_form_arguments($fields, $input);
            $values = array();

            foreach ($fields as $field) {
                $values[$field['name']] = (isset($input[$field['name']]) && is_string($input[$field['name']])) ? mb_substr($input[$field['name']], 0, 20000) : '';
            }

            // What was typed and what came back, shown once by the screen the
            // redirect opens.
            $state = array('server' => (int) $server['id'], 'tool' => $name, 'values' => $values, 'errors' => $parsed['errors'], 'result' => null);

            if (($channel_id > 0) && (!$channel || !ws_can_post_channel($viewer, $channel))) {
                $liveform->add_error(lang('You cannot post in that channel.'));
            } elseif (!empty($parsed['errors'])) {
                $liveform->add_error(lang('Some fields need a look.'));
            } else {
                $run = ws_mcp_form_run($viewer, $server, $tool, $parsed['arguments'], $channel);

                if ($run['ok']) {
                    $state['result'] = array('text' => $run['text'], 'is_error' => $run['is_error']);

                    if ($run['message_id'] > 0) {
                        $liveform->add_notice(lang(array('string' => 'The answer was posted in {var:1}.', 'vars' => '#' . h($channel['name']))));
                    } elseif (!$run['is_error']) {
                        $liveform->add_notice(lang(array('string' => '@{var:1} answered.', 'vars' => $server['alias'])));
                    }
                }

                if ($run['error'] !== '') {
                    $liveform->add_error(h($run['error']));
                }
            }

            $_SESSION['software']['ws_mcp_form'] = $state;
            break;

        default:
            $back .= $anchor;
    }

    go($back);

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

// Back from the service (connect.php).
if ((int) ($_GET['mcp_connected'] ?? 0) > 0) {
    $flash = pg_connect_flash_take();

    if ($flash && ($flash['kind'] === 'error')) {
        $liveform->add_error(h($flash['text']));
    } elseif ($flash) {
        $liveform->add_notice(h($flash['text']));
    }
}

$state = (isset($_SESSION['software']['ws_mcp_form']) && is_array($_SESSION['software']['ws_mcp_form'])) ? $_SESSION['software']['ws_mcp_form'] : null;

unset($_SESSION['software']['ws_mcp_form']);

$open_server = $servers[(int) ($_GET['server'] ?? 0)] ?? null;
$open_tools = $open_server ? ws_mcp_form_tools($open_server) : array();
$open_name = (string) ($_GET['tool'] ?? '');
$open_tool = $open_tools[$open_name] ?? null;
$channel_id = (int) ($_GET['channel'] ?? 0);

if ($state && (!$open_tool || ((int) $state['server'] !== (int) $open_server['id']) || ($state['tool'] !== $open_name))) {
    $state = null;
}

// The channels the answer may be posted in: those the person writes in.
$channels = array();

foreach (ws_channels_for($viewer) as $channel) {
    if (($channel['kind'] === 'public') || $channel['joined']) {
        $channels[(int) $channel['id']] = $channel;
    }
}

$server_cards = '';

foreach ($servers as $server) {
    $id = (int) $server['id'];
    $mine = ws_mcp_my_connection($viewer, $server);
    $tools = ws_mcp_form_tools($server);
    $asks_first = count(ws_mcp_tools_on($server)) - count($tools);

    if (!$mine['oauth']) {
        $badge = '<span class="badge theme-secondary fw-normal">' . h(lang('Site connection')) . '</span>';
        $account = '<p class="small fg-secondary mb-3">' . h(lang('Everybody uses the site\'s connection to this server: there is nothing of yours to connect.')) . '</p>';
    } else {
        if ($mine['works']) {
            $badge = '<span class="badge theme-success fw-normal">' . h(lang('Connected')) . '</span>';
            $line = lang(array('string' => 'Your own account is connected ({var:1}).', 'vars' => ws_time_label($mine['mine']['refreshed_timestamp'] ?: $mine['mine']['created_timestamp'])));

            if ((int) $mine['mine']['last_used_timestamp'] > 0) {
                $line .= ' ' . lang(array('string' => 'Last used {var:1}.', 'vars' => ws_time_label($mine['mine']['last_used_timestamp'])));
            }

            $button = '<button type="button" class="btn btn-sm btn-outline theme-danger ws-mcp-action" data-action="disconnect" data-id="' . $id . '"'
                . ' data-confirm="' . h(lang(array('string' => 'Disconnect your own account from @{var:1}? Calls that wait for your approval cannot run until you connect it again.', 'vars' => $server['alias']))) . '">'
                . '<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect my account')) . '</button>';
        } else {
            $badge = $mine['mine']
                ? '<span class="badge theme-danger fw-normal">' . h(lang('Connect again')) . '</span>'
                : '<span class="badge theme-warning fw-normal">' . h(lang('Not connected')) . '</span>';
            $line = $mine['mine'] ? lang('Your own connection ended.') : lang('Your own account is not connected.');
            $line .= ' ' . ($mine['site']
                ? lang('Tools that read use the site\'s account until you connect yours.')
                : lang('Nothing can be called for you until you connect it.'));

            $button = '<button type="button" class="btn btn-sm btn-solid theme-primary ws-mcp-action" data-action="connect" data-id="' . $id . '">'
                . '<i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>' . h($mine['mine'] ? lang('Connect my account again') : lang('Connect my account')) . '</button>';
        }

        $account = '
            <p class="small mb-3">' . h($line) . '</p>
            ' . (($asks_first > 0) ? '<p class="small fg-secondary mb-3">' . h(lang(array('string' => '{var:1} tools here ask first: each call waits under the answer until you approve it, and runs with your own account only.', 'vars' => $asks_first))) . '</p>' : '') . '
            <div class="mb-5">' . $button . '</div>';
    }

    $links = '';

    foreach ($tools as $name => $tool) {
        $active = $open_tool && ((int) $open_server['id'] === $id) && ($open_name === $name);

        $links .= '<a class="btn btn-sm ' . ($active ? 'btn-secondary' : 'btn-outline-secondary') . ' text-start" href="' . h($self_url . ws_mcp_form_query($id, $name, $channel_id)) . '"'
            . (($tool['description'] !== '') ? ' title="' . h(mb_substr($tool['description'], 0, 200)) . '"' : '')
            . ($active ? ' aria-current="true"' : '') . '>'
            . '<i class="bi bi-ui-checks me-1" aria-hidden="true"></i>' . h(($tool['title'] !== '') ? $tool['title'] : $name) . '</a>';
    }

    $details = array(ws_mcp_host($server['url']));

    if ($server['server_info'] !== '') {
        $details[] = $server['server_info'];
    }

    $server_cards .= '
        <div class="card mb-3' . (($open_server && ((int) $open_server['id'] === $id)) ? ' border-primary' : '') . '" id="ws-mcp-' . $id . '">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <i class="bi bi-plug fs-lg opacity-75" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">@' . h($server['alias']) . (($server['name'] !== '') ? ' <span class="fw-normal fg-secondary">· ' . h($server['name']) . '</span>' : '') . '</div>
                        <div class="small fg-secondary">' . h(implode(' · ', $details)) . '</div>
                    </div>
                    ' . $badge . '
                </div>
                ' . $account . '
                <div class="small fw-semibold mb-1">' . h(lang('Run a tool with a form')) . '</div>
                ' . (($links !== '')
                    ? '<div class="d-flex flex-wrap gap-3">' . $links . '</div>'
                    : '<p class="small fg-secondary mb-0">' . h(lang('No tool that reads is turned on here.')) . '</p>') . '
            </div>
        </div>';
}

$form_card = '';

if ($open_tool) {
    $fields = ws_mcp_form_fields($open_tool['schema']);
    $mine = ws_mcp_my_connection($viewer, $open_server);
    $values = $state ? (array) $state['values'] : array();
    $errors = $state ? (array) $state['errors'] : array();
    $output_fields = '';

    foreach ($fields as $field) {
        $output_fields .= ws_mcp_form_field_html($field, array_key_exists($field['name'], $values) ? (string) $values[$field['name']] : null, (string) ($errors[$field['name']] ?? ''));
    }

    if ($output_fields === '') {
        $output_fields = '<p class="small fg-secondary">' . h(lang('This tool takes nothing: run it as it is.')) . '</p>';
    }

    $options = '<option value="0">' . h(lang('Nowhere: only show it to me')) . '</option>';

    foreach ($channels as $channel) {
        $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === $channel_id) ? ' selected' : '') . '>#' . h($channel['name']) . '</option>';
    }

    if (!$mine['oauth']) {
        $runs_with = lang('Runs with the site\'s connection.');
    } elseif ($mine['works']) {
        $runs_with = lang(array('string' => 'Runs with your own account on @{var:1}.', 'vars' => $open_server['alias']));
    } elseif ($mine['site']) {
        $runs_with = lang('Runs with the site\'s account, as you have not connected your own.');
    } else {
        $runs_with = '';
    }

    $result = '';

    if ($state && is_array($state['result'])) {
        $text = (string) $state['result']['text'];

        $result = '
            <hr>
            <h3 class="h6 d-flex align-items-center gap-3">' . h(lang('Answer')) . ($state['result']['is_error'] ? ' <span class="badge theme-danger fw-normal">' . h(lang('Error')) . '</span>' : '') . '</h3>
            ' . (($text !== '')
                ? '<pre class="small bg-body-tertiary border rounded-4 p-3 mb-0" style="max-height: 30rem; overflow: auto; white-space: pre-wrap; word-break: break-word;">' . h($text) . '</pre>'
                : '<p class="small fg-secondary mb-0">' . h(lang('(empty)')) . '</p>');
    }

    $form_card = '
        <div class="card mb-5" id="ws-mcp-form">
            <div class="card-header">
                <h2 class="h6 mb-0"><i class="bi bi-ui-checks me-1" aria-hidden="true"></i>@' . h($open_server['alias']) . ': ' . h(($open_tool['title'] !== '') ? $open_tool['title'] : $open_name) . ' <code class="small fg-secondary">' . h($open_name) . '</code></h2>
            </div>
            <div class="card-body">
                ' . (($open_tool['description'] !== '') ? '<p class="small fg-secondary" style="white-space: pre-line;">' . h(mb_substr($open_tool['description'], 0, 1000)) . '</p>' : '') . '
                <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="mcp_action" value="run">
                    <input type="hidden" name="mcp_id" value="' . (int) $open_server['id'] . '">
                    <input type="hidden" name="mcp_tool" value="' . h($open_name) . '">
                    ' . $output_fields . '
                    <div class="mb-5 mt-5">
                        <label class="form-label small mb-1" for="ws_mcp_channel">' . h(lang('Post the answer in')) . '</label>
                        <select class="form-control form-control-sm" name="mcp_channel" id="ws_mcp_channel">' . $options . '</select>
                        <div class="form-text">' . h(lang('In a channel the answer is posted in your name and nobody can edit it.')) . '</div>
                    </div>
                    ' . (($runs_with !== '')
                        ? '<p class="small fg-secondary mb-3"><i class="bi bi-person-check me-1" aria-hidden="true"></i>' . h($runs_with) . '</p>
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>' . h(lang('Run')) . '</button>'
                        : '<div class="alert theme-warning small py-3 mb-0">' . h(lang(array('string' => 'Connect your own account to @{var:1} first: nobody\'s account is connected there for you.', 'vars' => $open_server['alias']))) . '</div>') . '
                </form>
                ' . $result . '
            </div>
        </div>';
}

$help = '
        <div class="card mb-5">
            <div class="card-body small">
                <h2 class="h6 mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('How it works')) . '</h2>
                <p class="fg-secondary">' . h(lang('Written in a channel where Pinegrap AI may be asked, an alias such as @github is answered by Pinegrap AI with the server\'s tools. On a server people sign in to, it uses your own account when you have connected one.')) . '</p>
                <p class="fg-secondary mb-0">' . h(lang('Here you can also run a tool that reads yourself, without Pinegrap AI: choose it, fill in its form and choose whether the answer goes to a channel.')) . '</p>
            </div>
        </div>';

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

echo
pg_page_shell(array(
    'title'               => lang('My MCP connections'),
    'extra classes'       => 'workspace workspace_mcp',
    'icon'                => 'workspace',
    'heading'             => lang('My MCP connections'),
    'heading_description' => lang('Your own accounts on the other services\' MCP servers of the Workspace, and their tools that read, run with a form.'),
    'cancel'              => false,
)) . '
<main id="content" class="container-fluid p-0">
<div class="ws-shell">
    ' . ws_screen_rail($viewer, 'mcp') . '
<div class="ws-shell-main">
    ' . $liveform_messages . '
    ' . ($viewer['settings'] ? '
    <nav id="button_bar" class="pg-toolbar navigation mb-5" aria-label="' . lang('Button Bar') . '">
        <a class="btn btn-sm btn-ghost" href="workspace_settings.php#ws-mcp"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('MCP servers')) . '</a>
    </nav>' : '') . '
    ' . (empty($servers)
        ? pg_empty_state('bi-plug', lang('No MCP server yet'), lang('An administrator adds the other services\' MCP servers under Workspace Settings › MCP servers. They appear here once they are on.'))
        : '<div class="row g-5">
        <div class="col-12 xl:col-5">' . $server_cards . '</div>
        <div class="col-12 xl:col-7">' . (($form_card !== '') ? $form_card : $help) . '</div>
    </div>') . '

    <form method="post" action="' . h($self_url) . '" id="ws_mcp_action_form" class="disable_shortcut d-none">
        ' . get_token_field() . '
        <input type="hidden" name="mcp_action" value="" id="ws_mcp_action">
        <input type="hidden" name="mcp_id" value="" id="ws_mcp_id">
    </form>
</div>
</div>
</main>

<script>
(function () {

    // Connecting and ending an account post through one form outside the
    // cards; ending one asks first.
    document.addEventListener("click", function (e) {
        var button = e.target.closest(".ws-mcp-action");
        if (!button) { return; }
        if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) { return; }
        document.getElementById("ws_mcp_action").value = button.dataset.action;
        document.getElementById("ws_mcp_id").value = button.dataset.id;
        document.getElementById("ws_mcp_action_form").submit();
    });

})();
</script>' . output_footer();
