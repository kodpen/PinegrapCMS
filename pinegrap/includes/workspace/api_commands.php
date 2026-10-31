<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the channel commands in the external API: the registry
 * (ws_commands()) as the composer's / picker, /help and the argument popup
 * read it, for the owner of the application. Only the commands that person
 * may use are listed. The list never runs a command: what a command does has
 * its own endpoint (tasks, plan items, scheduled messages, changes). An
 * application registers and removes its own commands here too
 * (includes/workspace/app_commands.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function ws_api_command_argument_present($arg)
{
    return array(
        'name'     => (string) $arg['name'],
        'kind'     => (string) $arg['kind'],
        'label'    => (string) $arg['label'],
        'optional' => (bool) $arg['optional'],
        'suggest'  => ((string) $arg['suggest'] !== '') ? (string) $arg['suggest'] : null,
        'multiple' => (bool) $arg['multiple'],
        'anywhere' => (bool) $arg['anywhere'],
    );
}

// What ws_api_command_argument_present() returns.
function ws_api_command_argument_schema()
{
    return array(
        'name'     => 'string',
        'kind'     => 'string',
        'label'    => 'string',
        'optional' => 'boolean',
        'suggest'  => 'string?',
        'multiple' => 'boolean',
        'anywhere' => 'boolean',
    );
}

function ws_api_command_present($entry)
{
    return array(
        'key'         => (string) $entry['key'],
        'names'       => array_values(array_map('strval', $entry['names'])),
        'name'        => (string) $entry['name'],
        'label'       => (string) $entry['label'],
        'description' => (string) $entry['description'],
        'usage'       => (string) $entry['usage'],
        'example'     => (string) $entry['example'],
        'args'        => array_values(array_map('ws_api_command_argument_present', $entry['args'])),
        'live'        => (bool) $entry['live'],
        'app_id'      => (int) ($entry['app_id'] ?? 0),
        'app'         => ((string) ($entry['app'] ?? '') !== '') ? (string) $entry['app'] : null,
    );
}

// What ws_api_command_present() returns.
function ws_api_command_schema()
{
    return array(
        'key'         => 'string',
        'names'       => array('string'),
        'name'        => 'string',
        'label'       => 'string',
        'description' => 'string',
        'usage'       => 'string',
        'example'     => 'string',
        'args'        => 'WorkspaceCommandArgument[]',
        'live'        => 'boolean',
        'app_id'      => 'integer',
        'app'         => 'string?',
    );
}

/**
 * Registry entries in the shape the API answers, in the registry's order.
 *
 * @param array[] $entries ws_commands() entries
 * @return array[]
 */
function ws_api_commands_present($entries)
{
    return array_values(array_map('ws_api_command_present', $entries));
}

function ws_api_commands_list($params)
{
    $viewer = ws_api_viewer();
    $out = ws_api_commands_present(ws_commands($viewer));

    api_ok_list($out, count($out));
}

function ws_api_app_command_present($name, $description, $usage, $app_id)
{
    return array(
        'name'        => '/' . (string) $name,
        'description' => (string) $description,
        'usage'       => (string) $usage,
        'app_id'      => (int) $app_id,
    );
}

// What ws_api_app_command_present() returns.
function ws_api_app_command_schema()
{
    return array(
        'name'        => 'string',
        'description' => 'string',
        'usage'       => 'string',
        'app_id'      => 'integer',
    );
}

/**
 * The application a command is registered for: a key or an OAuth connection,
 * never a person's devices session, whose commands would be nobody's.
 *
 * @return int
 */
function ws_api_command_app_id()
{
    if (function_exists('api_current_device') && (api_current_device() !== null)) {
        api_fail(403, 'forbidden', lang('Only an application registers commands; a devices session cannot.'));
    }

    $app = api_current_app();

    return is_array($app) ? (int) $app['id'] : 0;
}

/**
 * The refusal for a registration ws_app_command_save_check() turned down.
 *
 * @param array $check
 */
function ws_api_command_refuse($check)
{
    $messages = array(
        'name_invalid'         => lang('A command name is one word of 2 to 20 letters.'),
        'name_taken'           => lang('That command name is already taken.'),
        'description_required' => lang('Say what the command does.'),
        'description_too_long' => lang('The description is longer than 200 characters.'),
        'usage_too_long'       => lang('The usage is longer than 200 characters.'),
        'limit_reached'        => lang('An application may register twenty commands.'),
        'not_ready'            => lang('Run the software upgrade first.'),
    );
    $codes = array(422 => 'validation_failed', 503 => 'service_unavailable');
    $error = (string) $check['error'];

    // A 409 names its reason: name_taken or limit_reached.
    api_fail((int) $check['status'], $codes[(int) $check['status']] ?? $error, $messages[$error] ?? $error, ($check['field'] !== '') ? $check['field'] : null);
}

function ws_api_commands_register($params)
{
    ws_api_viewer();
    $app_id = ws_api_command_app_id();
    $check = ws_app_command_save_check($app_id, $params);

    if (!$check['ok']) {
        ws_api_command_refuse($check);
    }

    api_dry_run_stop($check['created'] ? 'created' : 'updated', 'workspace_app_command', array('name' => '/' . $check['name']));

    if (!ws_app_command_write($app_id, $check)) {
        ws_api_command_refuse(array('status' => 409, 'error' => 'name_taken', 'field' => 'name'));
    }

    api_ok(ws_api_app_command_present($check['name'], $check['description'], $check['usage'], $app_id), $check['created'] ? 201 : 200);
}

function ws_api_commands_delete($params)
{
    ws_api_viewer();
    $app_id = ws_api_command_app_id();
    $row = ws_app_command_find(ltrim((string) $params['name'], '/'), ws_app_commands_rows($app_id));

    if ($row === null) {
        api_fail(404, 'not_found', lang('This application has no such command.'));
    }

    api_dry_run_stop('deleted', 'workspace_app_command', array('name' => '/' . $row['name']));

    ws_app_command_delete($app_id, $row['name']);

    api_ok(array('name' => '/' . (string) $row['name'], 'deleted' => true));
}

/* ---------------------------------------------------------------------------
   Running a command (POST /workspace/commands/run)
   --------------------------------------------------------------------------- */

// The answers of a command that are shown back before anything is written.
function ws_api_command_preview_types()
{
    return array('preview', 'record_preview', 'create_preview', 'poll_form');
}

/**
 * The text as the composer hands it to ws_command_run(): "/claude ..." and
 * "/ai ..." become the assistant's tag first, so they are messages, not
 * commands.
 *
 * @param string $text
 * @return string
 */
function ws_api_command_text($text)
{
    $body = (string) $text;

    if (function_exists('ws_claude_command_body')) {
        $body = ws_claude_command_body($body);
    }

    if (function_exists('ws_ai_command_body')) {
        $body = ws_ai_command_body($body);
    }

    return $body;
}

/**
 * The options ws_command_run() takes, read from the route's values as the
 * composer's ws_send reads them from its request.
 *
 * @param array $params validated route values
 * @return array
 */
function ws_api_command_options($params)
{
    return array(
        'confirm'   => !empty($params['confirm']),
        'force'     => !empty($params['force']),
        'assignees' => isset($params['assignees']) ? array_values(array_map('intval', (array) $params['assignees'])) : null,
        'from'      => (isset($params['from']) && is_scalar($params['from'])) ? $params['from'] : null,
        'decision'  => !empty($params['decision']),
        'on_sale'   => !empty($params['on_sale']),
        'parent_id' => (int) ($params['parent_id'] ?? 0),
    );
}

/**
 * What a text is to the run route: no command at all ('none', posted as a
 * message by the composer, refused here), a command whose unconfirmed run
 * only shows a preview and writes nothing ('preview'), or one that acts at
 * once, so a dry run cannot show it ('run'). Read before anything runs; a
 * case in doubt is 'run', never 'preview'.
 *
 * @param string $body    the text after ws_api_command_text()
 * @param array  $options ws_api_command_options()
 * @return string none | preview | run
 */
function ws_api_command_kind($body, $options)
{
    $split = ws_command_split($body);

    if ($split === null) {
        // A slash and a word that is no command: the hint, or an
        // application's command, which goes to that application.
        return (ws_command_unknown($body) === '') ? 'none' : 'run';
    }

    $rest = (string) $split['rest'];
    $list = function ($text) {
        return function_exists('ws_bulk_text_is_list') ? ws_bulk_text_is_list($text) : (strpos(trim($text), "\n") !== false);
    };

    switch ($split['command']) {
        case 'task':
        case 'price':
        case 'stock':
            return 'preview';

        // Several lines make a bulk card and nothing after the command in an
        // answer reads the answered file: both are written at once.
        case 'create':
        case 'product':
            $product = ($split['command'] === 'create') ? ws_command_parse_create($rest)['rest'] : $rest;

            if ($list($rest) || ((trim($product) === '') && ((int) ($options['parent_id'] ?? 0) > 0))) {
                return 'run';
            }

            return 'preview';

        // Fewer than two options open the form; more post the poll.
        case 'poll':
            $parts = array_filter(array_map('trim', explode('|', $rest)), 'strlen');

            return (count($parts) < 3) ? 'preview' : 'run';
    }

    return 'run';
}

/**
 * The preview a dry run answers with: the command run unconfirmed, only when
 * that run writes nothing (ws_api_command_kind() 'preview'); null otherwise,
 * without running it.
 *
 * @param array    $viewer
 * @param array    $channel
 * @param string   $body
 * @param array    $options
 * @param callable $run     ws_command_run()
 * @return array|null
 */
function ws_api_command_preview($viewer, $channel, $body, $options, $run = 'ws_command_run')
{
    if (ws_api_command_kind($body, $options) !== 'preview') {
        return null;
    }

    $options['confirm'] = false;
    $options['force'] = false;
    $answer = call_user_func($run, $viewer, $channel, $body, $options);

    if (!is_array($answer) || !in_array((string) ($answer['type'] ?? ''), array_merge(ws_api_command_preview_types(), array('error')), true)) {
        return null;
    }

    return $answer;
}

function ws_api_commands_run($params)
{
    // A command writes in the person's own name, as the panel's writing box
    // does: a decision, a scheduled message, a product's price.
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can run a / command: it writes in their own name, while an application key or an OAuth connection writes as the application.'));

    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $body = ws_api_command_text($params['text']);
    $options = ws_api_command_options($params);

    // The composer would post it as a message; here it is refused.
    if (ws_api_command_kind($body, $options) === 'none') {
        api_fail(400, 'not_a_command', lang('The text is not a / command; write it as a message instead.'), 'text');
    }

    if (api_dry_run_requested()) {
        $split = ws_command_split($body);
        $preview = ws_api_command_preview($viewer, $channel, $body, $options);

        api_dry_run_stop('run', 'workspace_command', array(
            'command' => ($split !== null) ? (string) $split['command'] : '',
            'preview' => $preview,
        ));
    }

    $command = ws_api_as_owner(function () use ($viewer, $channel, $body, $options) {
        return ws_command_run($viewer, $channel, $body, $options);
    });

    if ($command === null) {
        api_fail(400, 'not_a_command', lang('The text is not a / command; write it as a message instead.'), 'text');
    }

    api_ok(array('command' => $command));
}
