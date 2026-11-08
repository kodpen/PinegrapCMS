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
