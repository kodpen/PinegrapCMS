<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the channel commands in the external API: the registry
 * (ws_commands()) as the composer's / picker, /help and the argument popup
 * read it, for the owner of the application. Only the commands that person
 * may use are listed. The list never runs a command: what a command does has
 * its own endpoint (tasks, plan items, scheduled messages, changes).
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
