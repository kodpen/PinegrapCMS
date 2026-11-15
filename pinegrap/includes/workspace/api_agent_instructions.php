<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API route an application agent reads its
 * instructions from with its own key (GET /workspace/agent-instructions);
 * the text itself is built in agent_instructions.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_instructions.php');

/**
 * The instructions route, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_agent_instructions_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.agent_instructions.get',
            'method'      => 'GET',
            'path'        => '/workspace/agent-instructions',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_agent_instructions',
            'mcp'         => false,
            'returns'     => 'WorkspaceAgentInstructions',
            'summary'     => 'How the calling agent works on this site',
            'description' => 'One text the agent follows: who it is, its channels and what wakes it in each, how it waits for requests behind its door (poll, webhook or routine), how it claims, answers or gives up a request, what it never does, and the rules every brief ends with. instructions_version changes whenever the text does: read the instructions again when a brief names another version. door is poll, webhook or routine. For the key of an active application agent (Workspace Settings, Agents) only, used as itself: any other caller 403 forbidden.',
            'params'      => array(),
        ),
    );
}

// What the instructions route returns.
function ws_api_agent_instructions_schema()
{
    return array(
        'handle'               => 'string',
        'name'                 => 'string',
        'role'                 => 'string',
        'site'                 => 'string',
        'door'                 => 'string',
        'instructions_version' => 'string',
        'text'                 => 'string',
    );
}

function ws_api_agent_instructions($params)
{
    $agent = ws_api_agent_caller();

    api_ok(ws_agent_instructions_present($agent, ws_agent_instructions_channels((int) $agent['id']), ws_claude_api_base()));
}
